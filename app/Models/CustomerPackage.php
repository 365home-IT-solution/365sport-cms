<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToPartner;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Category\Entities\Category;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;
use Modules\Product\App\Models\Product;

// Gói mà 1 khách CỤ THỂ đã sở hữu (đã mua/được bán) — xem ghi chú đầy đủ tại migration
// create_customer_packages_table. sessions_remaining là NGUỒN SỰ THẬT duy nhất cho "còn dùng được
// không", KHÔNG tự suy từ package_redemptions (trừ/hoàn buổi cập nhật trực tiếp cột này để tránh
// race condition khi 2 request cùng lúc redeem — xem redeem()/refundFor()). Snapshot ĐÚNG 1 TRONG
// 2 phạm vi từ PackagePlan lúc mua: product_id (dùng riêng 1 sân) HOẶC category_id (dùng cho MỌI
// sân thuộc 1 chi nhánh) — xem migration add_category_scope_to_package_tables.
class CustomerPackage extends Model
{
    use BelongsToPartner, LogsAuditTrail;

    protected $fillable = [
        'package_plan_id',
        'partner_id',
        'customer_id',
        'buyer_phone',
        'buyer_name',
        'product_id',
        'category_id',
        'product_ids',
        'name',
        'sessions_total',
        'sessions_remaining',
        'price_paid',
        'purchased_at',
        'expires_at',
        'sale_channel',
        'order_id',
        'created_by',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at'   => 'datetime',
        'product_ids'  => 'array',
    ];

    public function packagePlan()
    {
        return $this->belongsTo(PackagePlan::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function redemptions()
    {
        return $this->hasMany(PackageRedemption::class);
    }

    public function isValid(): bool
    {
        return $this->sessions_remaining > 0
            && (is_null($this->expires_at) || $this->expires_at->isFuture());
    }

    // Cùng logic PackagePlan::appliesToProduct() — CustomerPackage snapshot NGUYÊN VẸN phạm vi của
    // plan tại thời điểm mua (product_id/category_id/product_ids), nên kiểm tra y hệt cách plan
    // gốc kiểm tra, chỉ khác nguồn dữ liệu đọc từ chính bản ghi đã mua thay vì bản ghi plan đang
    // bán. Dùng ở Admin\BookingController::store() (đặt hộ + trừ gói) — xem thêm
    // CustomerPackage::findValidForPhoneAndProduct() cho phiên bản tra theo SĐT (không theo instance).
    public function appliesToProduct(string $productId, array $productCategoryIds = []): bool
    {
        if ($this->product_id !== null) {
            return $this->product_id === $productId;
        }

        if (! empty($this->product_ids)) {
            return in_array($productId, $this->product_ids, true);
        }

        return $this->category_id !== null && in_array($this->category_id, $productCategoryIds, true);
    }

    /**
     * Kích hoạt gói cho 1 Order order_type=package_purchase VỪA THANH TOÁN XONG — DÙNG CHUNG cho
     * cả 2 đường xác nhận tiền: PayOS (webhook/checkPaymentStatus, xem
     * PaymentController::handlePackagePurchasePaid()) VÀ chuyển khoản ngân hàng thủ công/tự động
     * (xem BankTransferConfirmationService::confirm()) — trước đây chỉ có nhánh PayOS gọi logic
     * này, thêm đường chuyển khoản mà không tách ra dùng chung sẽ tạo ra CustomerPackage lệch
     * nhau giữa 2 đường (bug thật cần tránh: khách chuyển khoản xong, admin xác nhận "đã nhận
     * tiền" nhưng KHÔNG có gói nào được kích hoạt vì logic tạo gói chỉ nằm trong nhánh PayOS).
     * Idempotent qua kiểm tra order_id đã có CustomerPackage chưa — an toàn khi gọi lại nhiều lần
     * (webhook retry, hoặc order vừa xác nhận qua chuyển khoản rồi lỡ tay bấm confirm PayOS nữa).
     */
    public static function activateFromOrder(Order $order): ?self
    {
        if (self::where('order_id', $order->id)->exists()) {
            return self::where('order_id', $order->id)->first();
        }

        $plan = PackagePlan::find($order->package_plan_id);
        if (! $plan) {
            return null;
        }

        return self::create([
            'package_plan_id'    => $plan->id,
            // Đơn được tạo TỪ TRANG KHÁCH (không chạy trong admin panel) nên BelongsToPartner
            // KHÔNG tự gán partner_id được (hook creating() chỉ hoạt động trong context Filament
            // admin) — set thủ công tại đây, ưu tiên partner_id của plan, fallback qua order nếu
            // plan cũ chưa có (trước migration add_partner_id_to_customer_packages_table).
            'partner_id'         => $plan->partner_id ?? $order->partner_id,
            'buyer_phone'        => $order->buyer_phone,
            'buyer_name'         => $order->buyer_name,
            'product_id'         => $plan->product_id,
            'category_id'        => $plan->category_id,
            // Snapshot NGUYÊN VẸN tập sân được phép dùng tại THỜI ĐIỂM MUA — nếu sau này admin sửa
            // lại PackagePlan (thêm/bớt sân), gói khách ĐÃ MUA không bị đổi phạm vi theo sau lưng.
            'product_ids'        => $plan->product_ids,
            'name'               => $plan->name,
            'sessions_total'     => $plan->sessions_count,
            'sessions_remaining' => $plan->sessions_count,
            'price_paid'         => (int) $order->amount,
            'purchased_at'       => now(),
            'expires_at'         => $plan->validity_days ? now()->addDays($plan->validity_days) : null,
            'sale_channel'       => 'online',
            'order_id'           => $order->id,
        ]);
    }

    /**
     * Tra cứu gói CÒN DÙNG ĐƯỢC của khách cho ĐÚNG 1 sân — theo SỐ ĐIỆN THOẠI (dự án này gần như
     * không có khách đăng ký tài khoản, xem ghi chú migration make_customer_id_nullable...), khớp
     * ĐÚNG cách AccountPage::phoneQuery() tra cứu đơn hàng. Dùng ở ProductDetail (hiện lựa chọn
     * "Dùng gói" lúc đặt sân) — 1 khách có thể sở hữu NHIỀU gói khớp (mua thêm khi gói cũ gần hết,
     * hoặc vừa có gói riêng sân này VỪA có gói cả chi nhánh), trả về gói SẮP HẾT HẠN NHẤT trước để
     * dùng hết trước khi mua gói mới hết hạn theo sau. $categoryIds (chi nhánh sân này thuộc về,
     * xem Product::categories()) — TÌM CẢ gói riêng sân này (product_id khớp) LẪN gói cả chi nhánh
     * (category_id khớp 1 trong các chi nhánh), ưu tiên gói nào hết hạn sớm hơn bất kể loại nào.
     */
    public static function findValidForPhoneAndProduct(string $phone, string $productId, array $categoryIds = []): ?self
    {
        return self::where('buyer_phone', $phone)
            ->where('sessions_remaining', '>', 0)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(function ($q) use ($productId, $categoryIds) {
                $q->where('product_id', $productId);

                if (! empty($categoryIds)) {
                    // Gói cả chi nhánh khớp category_id — NHƯNG nếu gói bị thu hẹp lại chỉ áp dụng
                    // cho MỘT SỐ sân cụ thể (product_ids snapshot lúc mua, xem migration
                    // add_product_ids_to_package_tables), phải khớp ĐÚNG sân đang xét, không được
                    // coi là dùng được cho MỌI sân trong chi nhánh nữa (bug thật cần tránh: gói chỉ
                    // bán cho sân 1+3 lại dùng lấn sang được cả sân 2 nếu không lọc thêm điều kiện
                    // này).
                    $q->orWhere(function ($q2) use ($productId, $categoryIds) {
                        $q2->whereIn('category_id', $categoryIds)
                            ->where(function ($q3) use ($productId) {
                                $q3->whereNull('product_ids')
                                    ->orWhereJsonLength('product_ids', 0)
                                    ->orWhereJsonContains('product_ids', $productId);
                            });
                    });
                }
            })
            ->orderByRaw('expires_at IS NULL, expires_at ASC')
            ->first();
    }

    /**
     * Trừ 1 buổi cho 1 order_item cụ thể — gọi khi khách chọn "Dùng gói" thay vì trả tiền lúc đặt
     * sân (xem ProductDetail::confirmBooking()). Dùng lockForUpdate() qua $this->refresh() bên
     * ngoài đã tự khoá row trong transaction (caller chịu trách nhiệm mở transaction) để tránh 2
     * request cùng lúc trừ vượt quá sessions_remaining.
     */
    public function redeem(OrderItem $orderItem, Order $order): PackageRedemption
    {
        if (! $this->isValid()) {
            throw new \RuntimeException('Gói đã hết buổi hoặc hết hạn.');
        }

        $this->decrement('sessions_remaining');

        return $this->redemptions()->create([
            'order_item_id' => $orderItem->id,
            'order_id'      => $order->id,
            'redeemed_at'   => now(),
        ]);
    }

    /**
     * Hoàn lại buổi đã trừ khi đơn dùng gói đó bị huỷ (xem OrderCancellationService) — chỉ hoàn
     * các lượt CHƯA từng hoàn trước đó (refunded_at null), tránh hoàn lặp nếu gọi lại nhiều lần.
     */
    public static function refundRedemptionsForOrder(Order $order): int
    {
        return DB::transaction(function () use ($order): int {
            $redemptions = PackageRedemption::where('order_id', $order->id)
                ->whereNull('refunded_at')
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($redemptions as $redemption) {
                $package = self::query()->lockForUpdate()->find($redemption->customer_package_id);
                if (! $package) {
                    continue;
                }

                $package->increment('sessions_remaining');
                $redemption->update(['refunded_at' => now()]);
                $count++;
            }

            return $count;
        });
    }
}
