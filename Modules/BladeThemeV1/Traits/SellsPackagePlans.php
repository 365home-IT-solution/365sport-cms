<?php

namespace Modules\BladeThemeV1\Traits;

use App\Models\PackagePlan;
use App\Services\BookingRateLimiter;
use Illuminate\Support\Facades\Log;
use Modules\BladeThemeV1\Services\Payment\PaymentService;
use Modules\Payment\Entities\Order;
use Modules\Product\App\Models\Product;

// Mua gói nhiều buổi (PackagePlan) — logic DÙNG CHUNG giữa các nơi khách có thể mua gói: trang
// lưới nhiều sân /branch/{slug} (Book.php) và trang chi tiết 1 sân (ProductDetail::buyPackage() —
// bản CŨ viết tay riêng, giữ nguyên không đổi để tránh rủi ro động vào luồng đang chạy đúng; trait
// này CHỈ dùng cho các component MỚI thêm sau). Tạo Order order_type=package_purchase + chọn ĐÚNG
// phương thức thanh toán của đối tác (PayOS/chuyển khoản ngân hàng — KHÔNG để khách chọn, xem
// resolveEffectivePaymentMethodFor()) + điều hướng tương ứng, giống hệt cách đơn đặt sân thường
// đang xử lý (ProductDetail::resolveEffectivePaymentMethod()/processBankTransferPayment()).
trait SellsPackagePlans
{
    protected function resolveEffectivePaymentMethodFor(Product $representativeProduct): string
    {
        $method = $representativeProduct->partner?->effectivePaymentMethod() ?? 'payos';

        return $method === 'bank_transfer' ? 'bank_transfer' : 'PayOS';
    }

    /**
     * @param  Product  $representativeProduct  Sân dùng làm đại diện cho item hoá đơn + tra cứu
     *                                            đối tác/phương thức thanh toán — KHÔNG giới hạn
     *                                            phạm vi dùng gói thật (xem PackagePlan->category_id
     *                                            snapshot ở CustomerPackage::activateFromOrder()).
     */
    protected function buyPackagePlan(int $planId, Product $representativeProduct, string $buyerName, string $buyerPhone, ?string $buyerEmail)
    {
        $plan = PackagePlan::find($planId);
        if (! $plan || ! $plan->is_active) {
            $this->dispatch('notify', ['message' => 'Gói không còn tồn tại hoặc đã ngừng bán.', 'type' => 'error']);

            return null;
        }

        if (empty($buyerName) || empty($buyerPhone)) {
            $this->dispatch('notify', ['message' => 'Vui lòng nhập họ tên và số điện thoại trước khi mua gói.', 'type' => 'error']);

            return null;
        }

        // Xem App\Services\BookingRateLimiter — chặn phá hoại theo IP (đồng bộ với các luồng đặt
        // sân/mua gói khác trong hệ thống).
        $blockedReason = app(BookingRateLimiter::class)->checkBlocked(request()->ip());
        if ($blockedReason) {
            $this->dispatch('notify', ['message' => $blockedReason, 'type' => 'error']);

            return null;
        }

        if (! ($representativeProduct->partner?->hasUsablePaymentMethod() ?? true)) {
            $this->dispatch('notify', ['message' => \App\Models\Partner::PAYMENT_NOT_READY_MESSAGE, 'type' => 'error']);

            return null;
        }

        $paymentMethod = $this->resolveEffectivePaymentMethodFor($representativeProduct);

        $order = Order::create([
            'order_code'      => time() . rand(1000, 9999),
            'order_type'      => 'package_purchase',
            'package_plan_id' => $plan->id,
            'buyer_name'      => $buyerName,
            'buyer_phone'     => $buyerPhone,
            'buyer_email'     => $buyerEmail ?: null,
            'amount'          => $plan->price,
            'full_amount'     => $plan->price,
            'creator_ip'      => request()->ip(),
            'status'          => 'pending',
            'payment_method'  => $paymentMethod,
            'description'     => 'Mua goi: ' . $plan->name,
            'category_id'     => $plan->category_id ?? $representativeProduct->categories->first()?->id,
            'partner_id'      => $representativeProduct->partner_id,
        ]);

        $order->items()->create([
            // Gói cả chi nhánh (plan->product_id null) không có 1 sân cụ thể để gắn — dùng sân đại
            // diện làm item (chỉ để hiển thị/thống kê đơn hàng, KHÔNG ảnh hưởng phạm vi dùng gói
            // thật — quyền dùng gói xét theo CustomerPackage.category_id).
            'product_id' => $plan->product_id ?? $representativeProduct->id,
            'name'       => $plan->name . ' - ' . $representativeProduct->name,
            'price'      => $plan->price,
            'quantity'   => 1,
            'extra_fee'  => 0,
        ]);

        // Order::$with = ['items'] eager-load được gán lúc Order::create() TRƯỚC KHI item vừa tạo
        // ở trên tồn tại — load() lại tường minh để PaymentService thấy đúng item (cùng lý do đã
        // xử lý ở ProductDetail::buyPackage()).
        $order->load('items');

        // Chuyển khoản ngân hàng — KHÔNG gọi PayOS, chỉ gia hạn thời gian giữ đơn dài hơn (60 phút
        // thay vì 15 phút của PayOS) rồi chuyển sang trang tra cứu đơn để khách upload ảnh biên
        // lai + chờ admin duyệt (xem BankTransferConfirmationService — đã hỗ trợ kích hoạt gói qua
        // CustomerPackage::activateFromOrder()).
        if ($paymentMethod === 'bank_transfer') {
            $order->update(['expired_at' => now()->addMinutes(60)]);

            return redirect()->to(route('booking.detail', ['code' => $order->order_code]));
        }

        try {
            $paymentService = app(PaymentService::class);
            $paymentService->usePartnerCredentials($representativeProduct->partner);

            $paymentData = $paymentService->createRoomBookingPaymentData($order, [
                'productName' => $representativeProduct->name,
            ]);
            $paymentLink = $paymentService->createPaymentLink($paymentData);

            $order->update([
                'checkout_url' => $paymentLink['checkoutUrl'] ?? $paymentLink['data']['checkoutUrl'] ?? null,
                'status'       => 'pending',
                'expired_at'   => now()->addMinutes(15),
            ]);

            return redirect()->to($paymentLink['checkoutUrl'] ?? $paymentLink['data']['checkoutUrl']);
        } catch (\Throwable $th) {
            $order->update(['status' => 'failed']);
            Log::error('buyPackagePlan: PayOS error', ['error' => $th->getMessage()]);
            $this->dispatch('notify', ['message' => 'Lỗi thanh toán, vui lòng thử lại.', 'type' => 'error']);

            return null;
        }
    }
}
