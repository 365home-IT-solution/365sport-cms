<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ExtraChargeService;
use App\Services\NotificationFcmService;
use App\Services\OrderLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AuditLog\Services\AuditLogger;
use Modules\Payment\Entities\Order;

// Vòng đời đơn cho nhân viên/chủ sân: nhận sân (check-in, có thể quét mã QR của khách), trả sân (check-out), đổi giờ, và xử lý
// hoàn tiền chờ xử lý (cùng nguồn dữ liệu với màn Filament "Hoàn tiền chờ xử lý"). Toàn bộ nghiệp vụ nằm ở
// App\Services\OrderLifecycleService / ExtraChargeService — controller này chỉ kiểm tra quyền, validate và định dạng JSON.
class OrderLifecycleController extends Controller
{
    public function __construct(private readonly OrderLifecycleService $lifecycle) {}

    // POST /api/admin/orders/{order_code}/check-in — body: code? (mã nhận sân của khách), force? (nhân viên sửa sai sót, bỏ qua khung giờ)
    public function checkIn(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->visibleOrder($request->user(), $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $data = $request->validate(['code' => 'sometimes|nullable|string|max:40', 'force' => 'sometimes|boolean']);

        if (! empty($data['code']) && ! hash_equals($order->checkInCode(), strtoupper(trim($data['code'])))) {
            return response()->json(['message' => 'The check-in code does not match this order.'], 422);
        }

        return $this->respond($order, $this->lifecycle->checkIn($order, $request->user(), (bool) ($data['force'] ?? false)));
    }

    // POST /api/admin/orders/check-in-scan — body: payload (chuỗi trong mã QR của khách: SPORT365:{order_code}:{code})
    public function checkInScan(Request $request): JsonResponse
    {
        $data = $request->validate(['payload' => 'required|string|max:100']);

        if (! preg_match('/^SPORT365:([A-Za-z0-9]+):([A-Za-z0-9]{8})$/', trim($data['payload']), $m)) {
            return response()->json(['message' => 'This is not a valid check-in code.'], 422);
        }

        $order = $this->visibleOrder($request->user(), $m[1]);
        if (! $order || ! hash_equals($order->checkInCode(), strtoupper($m[2]))) {
            return response()->json(['message' => 'Order not found or the code does not match.'], 404);
        }

        return $this->respond($order, $this->lifecycle->checkIn($order, $request->user(), false));
    }

    // POST /api/admin/orders/{order_code}/check-out
    public function checkOut(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->visibleOrder($request->user(), $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        return $this->respond($order, $this->lifecycle->checkOut($order, $request->user()));
    }

    // POST /api/admin/orders/{order_code}/reschedule — body: new_start (Y-m-d H:i), allow_price_difference?
    public function reschedule(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->visibleOrder($request->user(), $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $data = $request->validate(['new_start' => 'required|date_format:Y-m-d H:i', 'allow_price_difference' => 'sometimes|boolean']);

        $result = $this->lifecycle->reschedule($order, Carbon::createFromFormat('Y-m-d H:i', $data['new_start']), $request->user(), false, (bool) ($data['allow_price_difference'] ?? false));
        if ($result['ok'] && $order->customer_id && ($customer = \App\Models\Customer::find($order->customer_id))) {
            try {
                app(NotificationFcmService::class)->sendToCustomer($customer, 'Lịch đặt sân đã được đổi', 'Đơn #' . $order->order_code . ' đã được chuyển sang ' . Carbon::parse($result['new_start'])->format('H:i d/m/Y') . '.', 'booking', ['subtype' => 'order_rescheduled', 'order_code' => (string) $order->order_code]);
            } catch (\Throwable) {
                // Gửi thông báo thất bại không được làm hỏng thao tác đã ghi.
            }
        }

        return $this->respond($order, $result);
    }

    // GET /api/admin/refunds — ?status=pending|done|all (mặc định pending), ?search= (mã đơn, tên, SĐT)
    public function refunds(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $status = (string) $request->query('status', 'pending');

        $query = Order::query()->where('extra_refund_amount', '>', 0)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('partner_id', $user->partner_id))
            ->when($status === 'pending', fn ($q) => $q->whereNull('extra_refund_paid_at'))
            ->when($status === 'done', fn ($q) => $q->whereNotNull('extra_refund_paid_at'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim((string) $request->query('search')) . '%';
                $q->where(fn ($w) => $w->where('order_code', 'like', $s)->orWhere('buyer_name', 'like', $s)->orWhere('buyer_phone', 'like', $s));
            })
            ->orderByDesc('cancelled_at')
            ->orderByDesc('id');

        $page = $query->paginate(min(100, max(1, $request->integer('per_page', 20))));
        $page->through(fn (Order $o) => $this->refundItem($o));

        return response()->json($page);
    }

    // POST /api/admin/refunds/{order_code}/complete — body: method (cash|transfer)
    public function completeRefund(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->visibleOrder($request->user(), $orderCode);
        if (! $order || (int) $order->extra_refund_amount <= 0) {
            return response()->json(['message' => 'No refund found for this order.'], 404);
        }
        if ($order->extra_refund_paid_at) {
            return response()->json(['message' => 'This refund has already been completed.'], 422);
        }

        $data = $request->validate(['method' => 'required|in:cash,transfer']);
        $amount = (int) $order->extra_refund_amount;

        app(ExtraChargeService::class)->markRefundAsDone($order, $amount, $data['method']);
        AuditLogger::log('update', 'Order', $order, ['extra_refund_paid_at' => null],
            ['extra_refund_paid_at' => now()->toDateTimeString(), 'extra_refund_method' => $data['method'], 'extra_refund_amount' => $amount], 'Đơn #' . $order->order_code);

        return response()->json(['message' => 'Refund marked as completed.', 'data' => $this->refundItem($order->fresh())]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private function visibleOrder(User $user, string $orderCode): ?Order
    {
        $order = Order::where('order_code', $orderCode)->where('order_type', '!=', 'package_purchase')->first();

        return $order && ($user->isSuperAdmin() || $order->partner_id === $user->partner_id) ? $order : null;
    }

    private function respond(Order $order, array $result): JsonResponse
    {
        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?? ($result['error'] ?? 'Not allowed.'), 'code' => $result['code'] ?? null], 422);
        }

        $order = $order->fresh();

        return response()->json(['data' => [
            'order_code' => $order->order_code,
            'order_status' => $order->order_status,
            'checked_in_at' => $order->checked_in_at?->toIso8601String(),
            'checked_out_at' => $order->checked_out_at?->toIso8601String(),
            'rescheduled_count' => (int) $order->rescheduled_count,
            'checkin_date' => $order->items->min('checkin_date')?->toIso8601String(),
            'checkout_date' => $order->items->max('checkout_date')?->toIso8601String(),
        ]]);
    }

    private function refundItem(Order $o): array
    {
        return [
            'order_code' => $o->order_code,
            'buyer_name' => $o->buyer_name,
            'buyer_phone' => $o->buyer_phone,
            'refund_amount' => (int) $o->extra_refund_amount,
            'cancel_reason' => $o->cancel_reason,
            'cancelled_at' => $o->cancelled_at?->toIso8601String(),
            'refund_method' => $o->extra_refund_method,
            'refunded_at' => $o->extra_refund_paid_at?->toIso8601String(),
            'status' => $o->extra_refund_paid_at ? 'done' : 'pending',
        ];
    }
}
