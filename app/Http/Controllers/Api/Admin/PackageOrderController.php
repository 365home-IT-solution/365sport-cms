<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPackage;
use App\Models\User;
use App\Services\BankTransferConfirmationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AuditLog\Services\AuditLogger;
use Modules\Payment\Entities\Order;

/**
 * Đơn MUA GÓI nhiều buổi (Order.order_type='package_purchase') — tương đương Admin API cho
 * Modules\Payment\App\Filament\Resources\PackageOrderResource. Đơn dạng này KHÔNG có
 * checkin_date/checkout_date/khung giờ/sân (khác đơn đặt sân "booking" ở OrderController/
 * BookingController) — chỉ là phương tiện thu tiền để kích hoạt App\Models\CustomerPackage (xem
 * CustomerPackage::activateFromOrder()). Package orders vẫn là bản ghi Order (bảng orders) nên
 * DELETE/duyệt-chuyển-khoản dùng LẠI ĐÚNG endpoint chung của Orders API — không định nghĩa lại ở
 * đây (xem docblock index()):
 *   - DELETE /api/admin/orders/{order_code}                    (xoá hẳn — OrderController::destroy())
 *   - POST   /api/admin/orders/{order_code}/approve-bank-transfer
 *   - POST   /api/admin/orders/{order_code}/reject-bank-transfer
 * 3 endpoint trên tra theo order_code không lọc order_type nên áp dụng nguyên vẹn cho đơn mua gói,
 * approve-bank-transfer đã tự nhánh đúng qua BankTransferConfirmationService::confirm() (kích hoạt
 * CustomerPackage khi order_type='package_purchase' thay vì cấp mã truy cập như đơn đặt sân).
 *
 * KHÔNG có POST tạo mới (store()) — đơn mua gói chỉ phát sinh khi khách tự mua trên web
 * (ProductDetail::buyPackage()/Book::buyPackage()), giống hệt PackageOrderResource::canCreate()
 * luôn trả false.
 */
class PackageOrderController extends Controller
{
    /**
     * GET /api/admin/package-orders
     * Danh sách đơn mua gói, lọc theo đối tác của tài khoản đang đăng nhập — CÙNG quy ước lọc với
     * GET /api/admin/orders (OrderController::index()): users.partner_id, super_admin xem hết.
     * Chỉ khác đúng 1 điều kiện luôn áp: order_type='package_purchase' (đơn đặt sân "booking" xem ở
     * GET /api/admin/orders) — để 2 API không bao giờ trả trùng 1 bản ghi nào.
     *
     * Filter (đọc CẢ ?filter[key]=... LẪN ?key=... phẳng, giống OrderController::index()):
     *  - filter[status]         : pending|paid|failed|cancelled_payment|refunded
     *  - filter[payment_method] : PayOS|bank_transfer|cod
     *  - filter[search]         : order_code / buyer_name / buyer_phone
     *  - filter[from], filter[to] : lọc theo ngày TẠO đơn (created_at, yyyy-mm-dd)
     *  - filter[package_plan_id] : lọc theo gói đang mua
     *  - per_page (không nằm trong filter) : mặc định 10
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Order::query()
            ->where('order_type', 'package_purchase')
            ->with(['packagePlan:id,name,sessions_count,price', 'customer:id,fullname,phone']);

        if (! $user->isSuperAdmin()) {
            if (empty($user->partner_id)) {
                return response()->json($query->whereRaw('1 = 0')->paginate($request->integer('per_page', 10)));
            }

            $query->where('partner_id', $user->partner_id);
        }

        $f = fn (string $key) => $request->input("filter.{$key}", $request->input($key));

        $query
            ->when(filled($f('status')), fn ($q) => $q->where('status', $f('status')))
            ->when(filled($f('payment_method')), fn ($q) => $q->where('payment_method', $f('payment_method')))
            ->when(filled($f('package_plan_id')), fn ($q) => $q->where('package_plan_id', (int) $f('package_plan_id')))
            ->when(filled($f('search')), function ($q) use ($f) {
                $search = $f('search');
                $q->where(function ($q2) use ($search) {
                    $q2->where('order_code', 'like', "%{$search}%")
                        ->orWhere('buyer_name', 'like', "%{$search}%")
                        ->orWhere('buyer_phone', 'like', "%{$search}%");
                });
            })
            ->when(filled($f('from')), fn ($q) => $q->whereDate('created_at', '>=', $f('from')))
            ->when(filled($f('to')), fn ($q) => $q->whereDate('created_at', '<=', $f('to')))
            ->orderByDesc('created_at');

        $orders = $query->paginate($request->integer('per_page', 10));

        $orders->getCollection()->transform(fn (Order $order) => $this->toListItem($order));

        return response()->json($orders);
    }

    /**
     * GET /api/admin/package-orders/{order_code}
     * Chi tiết 1 đơn mua gói — cùng phạm vi truy cập với index() (404 nếu không tồn tại hoặc không
     * thuộc đối tác của tài khoản, không trả 403, tránh lộ sự tồn tại của đơn ngoài phạm vi). Kèm
     * 'activated_package' — thông tin App\Models\CustomerPackage ĐÃ kích hoạt từ đơn này (null nếu
     * đơn chưa thanh toán/chưa kích hoạt gói).
     */
    public function show(Request $request, string $orderCode): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = Order::query()
            ->where('order_type', 'package_purchase')
            ->with(['packagePlan', 'customer:id,fullname,phone', 'creator:id,fullname'])
            ->where('order_code', $orderCode)
            ->first();

        if (! $order || (! $user->isSuperAdmin() && $order->partner_id !== $user->partner_id)) {
            return response()->json(['message' => 'Package order not found.'], 404);
        }

        return response()->json(['order' => $this->toDetailItem($order)]);
    }

    /**
     * PUT|POST /api/admin/package-orders/{order_code}
     * Sửa đơn mua gói — mọi field đều tuỳ chọn, chỉ gửi field nào cần sửa. KHÔNG dùng để đổi 'status'
     * sang 'paid' (xem lỗi 422 bên dưới) — dùng POST .../mark-paid hoặc
     * POST /api/admin/orders/{order_code}/approve-bank-transfer để kích hoạt CustomerPackage đúng
     * cách, tránh đơn 'paid' nhưng không có gói nào được tạo.
     *
     *   - buyer_name, buyer_phone, buyer_email : thông tin người mua
     *   - note_for_admin                       : ghi chú nội bộ
     *   - package_plan_id : đổi gói đang mua — CHỈ áp dụng khi đơn CHƯA 'paid' (đổi sau khi đã kích
     *     hoạt không hồi tố CustomerPackage đã snapshot tên/số buổi lúc kích hoạt) — 422 nếu đơn đã
     *     'paid'. Sửa số buổi/hạn dùng THẬT của gói đã kích hoạt qua cp_sessions_remaining/
     *     cp_expires_at bên dưới.
     *   - status : pending|failed|cancelled_payment|refunded (KHÔNG nhận 'paid' ở đây)
     *   - cp_sessions_remaining, cp_expires_at : CHỈ có tác dụng khi đơn đã kích hoạt gói (đã tồn tại
     *     1 CustomerPackage với order_id này) — sửa tay số buổi còn lại/hạn dùng THẬT của gói khách
     *     đang dùng, KHÔNG map cột nào của Order.
     */
    public function update(Request $request, string $orderCode): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $order = Order::query()
            ->where('order_type', 'package_purchase')
            ->where('order_code', $orderCode)
            ->first();

        if (! $order || (! $admin->isSuperAdmin() && $order->partner_id !== $admin->partner_id)) {
            return response()->json(['message' => 'Package order not found.'], 404);
        }

        $data = $request->validate([
            'buyer_name'            => 'sometimes|nullable|string|max:100',
            'buyer_phone'           => 'sometimes|nullable|string|max:20',
            'buyer_email'           => 'sometimes|nullable|email|max:255',
            'note_for_admin'        => 'sometimes|nullable|string|max:500',
            'package_plan_id'       => 'sometimes|nullable|integer|exists:package_plans,id',
            'status'                => 'sometimes|in:pending,failed,cancelled_payment,refunded',
            'cp_sessions_remaining' => 'sometimes|integer|min:0',
            'cp_expires_at'         => 'sometimes|nullable|date',
        ]);

        // 'paid' phải đi qua mark-paid/approve-bank-transfer để kích hoạt CustomerPackage đúng cách
        // — chặn ở đây để không lỡ tay set status='paid' bằng update() thường (bug thật cần tránh,
        // xem PackageOrderResource::form() -> Select 'status' cố tình dehydrated(false)).
        if ($request->input('status') === 'paid') {
            return response()->json([
                'message' => "To mark this order as paid, call POST /api/admin/package-orders/{$orderCode}/mark-paid instead — updating 'status' directly does not activate the customer's package.",
            ], 422);
        }

        if (array_key_exists('package_plan_id', $data) && $order->status === 'paid') {
            return response()->json([
                'message' => 'This order has already been paid — the package plan can no longer be changed here. Adjust the activated package via cp_sessions_remaining/cp_expires_at instead.',
            ], 422);
        }

        $updates = [];

        foreach (['buyer_name', 'buyer_phone', 'buyer_email', 'note_for_admin', 'package_plan_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field];
            }
        }

        if (array_key_exists('status', $data)) {
            $oldStatus         = $order->status;
            $updates['status'] = $data['status'];

            AuditLogger::log(
                'update', 'Order', $order,
                ['status' => $oldStatus],
                ['status' => $data['status']],
                'Package order #' . $order->order_code . ' (via admin API)'
            );
        }

        if (! empty($updates)) {
            $order->update($updates);
        }

        $cp = CustomerPackage::where('order_id', $order->id)->first();

        if ($cp && (array_key_exists('cp_sessions_remaining', $data) || array_key_exists('cp_expires_at', $data))) {
            $cpUpdates = [];
            if (array_key_exists('cp_sessions_remaining', $data)) {
                $cpUpdates['sessions_remaining'] = (int) $data['cp_sessions_remaining'];
            }
            if (array_key_exists('cp_expires_at', $data)) {
                $cpUpdates['expires_at'] = $data['cp_expires_at'];
            }
            $cp->update($cpUpdates);
        }

        return response()->json(['order' => $this->toDetailItem($order->fresh(['packagePlan', 'customer', 'creator']))]);
    }

    /**
     * POST /api/admin/package-orders/{order_code}/mark-paid
     * Xác nhận thanh toán tay cho MỌI phương thức (PayOS, tiền mặt, chuyển khoản khác kênh...) —
     * tương đương PackageOrderResource::markPaidAction() ở Filament. Dùng khi khách đã trả tiền
     * nhưng hệ thống chưa tự cập nhật (vd webhook PayOS lỗi). Kích hoạt CustomerPackage NGAY LẬP TỨC
     * cho khách qua BankTransferConfirmationService::confirm() — dùng CHUNG với luồng duyệt chuyển
     * khoản (POST /api/admin/orders/{order_code}/approve-bank-transfer) để không viết lại logic kích
     * hoạt gói lần thứ 2 ở đây (tránh lệch nhau). 422 nếu đơn đã 'paid' hoặc 'refunded'.
     */
    public function markPaid(Request $request, string $orderCode): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $order = Order::where('order_type', 'package_purchase')
            ->where('order_code', $orderCode)
            ->first();

        if (! $order || (! $admin->isSuperAdmin() && $order->partner_id !== $admin->partner_id)) {
            return response()->json(['message' => 'Package order not found.'], 404);
        }

        if (in_array($order->status, ['paid', 'refunded'], true)) {
            return response()->json(['message' => 'This order is already paid or refunded — nothing to confirm.'], 422);
        }

        try {
            app(BankTransferConfirmationService::class)->confirm($order, (string) $admin->id);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to confirm payment: ' . $e->getMessage()], 500);
        }

        return response()->json(['order' => $this->toDetailItem($order->fresh(['packagePlan', 'customer', 'creator']))]);
    }

    private function toListItem(Order $order): array
    {
        return [
            'id'             => $order->id,
            'order_code'     => $order->order_code,
            'status'         => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'full_amount'    => (int) $order->full_amount,
            'buyer_name'     => $order->buyer_name,
            'buyer_phone'    => $order->buyer_phone,
            'buyer_email'    => $order->buyer_email,
            'customer'       => $order->customer ? [
                'id'       => $order->customer->id,
                'fullname' => $order->customer->fullname,
                'phone'    => $order->customer->phone,
            ] : null,
            'package_plan' => $order->packagePlan ? [
                'id'             => $order->packagePlan->id,
                'name'           => $order->packagePlan->name,
                'sessions_count' => $order->packagePlan->sessions_count,
                'price'          => (int) $order->packagePlan->price,
            ] : null,
            'created_at' => $order->created_at,
        ];
    }

    private function toDetailItem(Order $order): array
    {
        $cp = CustomerPackage::where('order_id', $order->id)->first();

        return $this->toListItem($order) + [
            'note_for_admin'             => $order->note_for_admin,
            'checkout_url'               => $order->checkout_url,
            'qr_code'                    => $order->qr_code,
            'expired_at'                 => $order->expired_at,
            'bank_transfer_status'       => $order->bank_transfer_status,
            'payment_proof_url'          => $order->payment_proof_url,
            'bank_transfer_confirmed_at' => $order->bank_transfer_confirmed_at,
            'paid_at'                    => $order->paid_at,
            'created_at'                 => $order->created_at,
            'creator'                    => $order->creator ? [
                'id'       => $order->creator->id,
                'fullname' => $order->creator->fullname,
            ] : null,
            // Gói ĐÃ kích hoạt (App\Models\CustomerPackage::activateFromOrder()) — null nếu đơn
            // chưa 'paid'/chưa kích hoạt. sessions_total/name là SNAPSHOT lúc mua, có thể khác với
            // package_plan hiện tại nếu admin đã sửa PackagePlan sau đó.
            'activated_package' => $cp ? [
                'id'                 => $cp->id,
                'name'               => $cp->name,
                'sessions_total'     => $cp->sessions_total,
                'sessions_remaining' => $cp->sessions_remaining,
                'purchased_at'       => $cp->purchased_at,
                'expires_at'         => $cp->expires_at,
                'is_valid'           => $cp->isValid(),
            ] : null,
        ];
    }
}
