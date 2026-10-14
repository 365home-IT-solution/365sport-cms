<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\OrderCancellationService;
use App\Services\OrderLifecycleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Entities\Order;

// Khách tự phục vụ trên đơn của mình: xem trước mức hoàn tiền, tự huỷ (hoàn tiền theo chính sách của chi nhánh), tự đổi giờ và lấy mã
// nhận sân (QR). Cùng nghiệp vụ với web (AccountPage::cancelOrder) vì dùng chung OrderCancellationService / OrderLifecycleService.
class OrderSelfServiceController extends Controller
{
    // GET /api/orders/{order_code}/cancel-preview
    public function cancelPreview(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->ownOrder($request, $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $canCancel = $order->canBeCancelledByCustomer();

        return response()->json(['data' => [
            'order_code' => $order->order_code,
            'can_cancel' => $canCancel,
            // Đơn trả bằng gói: không hoàn tiền mặt, huỷ thì buổi được trả lại vào gói.
            'paid_by_package' => $order->isPaidByPackage(),
            'fully_paid_by_package' => $order->isFullyPaidByPackage(),
            'package_cash_amount' => $order->packageCashAmount(),
            'sessions_to_restore' => $canCancel ? $order->packageSessionsToRestore() : 0,
            'refund_percent' => $canCancel && ! $order->isFullyPaidByPackage() ? $order->customerCancelRefundPercent() : 0,
            'refund_amount' => $canCancel ? $order->computeCustomerCancelRefundAmount() : 0,
            'policy' => $this->policy($order),
            'reason' => $canCancel ? null : $this->cannotCancelReason($order),
        ]]);
    }

    // POST /api/orders/{order_code}/cancel — body: reason?
    public function cancel(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->ownOrder($request, $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);

        // Khách chỉ GỬI YÊU CẦU huỷ — admin duyệt/từ chối (xem OrderCancelRequestService), kết quả được
        // báo lại qua thông báo đẩy + cancel_request_status của đơn.
        $percent = $order->canBeCancelledByCustomer() && ! $order->isFullyPaidByPackage() ? $order->customerCancelRefundPercent() : 0;
        $result = app(\App\Services\OrderCancelRequestService::class)->request($order, $data['reason'] ?? null);

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'message' => 'Cancellation request submitted. It is pending admin approval.',
            'data' => [
                'order_code'            => $order->order_code,
                'cancel_request_status' => 'pending',
                'refund_percent_if_approved' => $percent,
                'refund_amount_if_approved'  => $order->computeCustomerCancelRefundAmount(),
            ],
        ], 202);
    }

    // POST /api/orders/{order_code}/reschedule — body: new_start (Y-m-d H:i)
    public function reschedule(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->ownOrder($request, $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $data = $request->validate(['new_start' => 'required|date_format:Y-m-d H:i']);

        $result = app(OrderLifecycleService::class)->reschedule($order, Carbon::createFromFormat('Y-m-d H:i', $data['new_start']), 'customer', true);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message'], 'code' => $result['code']], 422);
        }

        return response()->json(['message' => 'Order rescheduled.', 'data' => [
            'order_code' => $order->order_code,
            'checkin_date' => $result['new_start'],
            'checkout_date' => $result['new_end'],
        ]]);
    }

    // GET /api/orders/{order_code}/check-in-code — mã nhận sân để nhân viên quét/nhập
    public function checkInCode(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->ownOrder($request, $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        if (! in_array($order->status, ['paid', 'deposit'], true) || $order->isCancelled()) {
            return response()->json(['message' => 'The check-in code is only available for paid, active orders.'], 422);
        }

        $start = $order->items->pluck('checkin_date')->filter()->min();
        $end = $order->items->pluck('checkout_date')->filter()->max();

        return response()->json(['data' => [
            'order_code' => $order->order_code,
            'code' => $order->checkInCode(),
            'qr_payload' => $order->checkInPayload(),
            'order_status' => $order->order_status,
            'valid_from' => $start?->copy()->subMinutes(OrderLifecycleService::CHECKIN_EARLY_MINUTES)->toIso8601String(),
            'valid_until' => $end?->toIso8601String(),
        ]]);
    }

    private function ownOrder(Request $request, string $orderCode): ?Order
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return Order::with(['items', 'category'])
            ->where('order_code', $orderCode)
            ->where('customer_id', $customer->id)
            ->where('order_type', '!=', 'package_purchase')
            ->first();
    }

    /** Các mốc hoàn tiền áp dụng cho chi nhánh của đơn (mặc định 70% khi chi nhánh chưa cấu hình). */
    private function policy(Order $order): array
    {
        $tiers = $order->category?->cancellation_tiers;
        if (! is_array($tiers) || $tiers === []) {
            return [['hours_before' => 0, 'refund_percent' => (int) round(Order::CUSTOMER_CANCEL_REFUND_RATE * 100)]];
        }

        return collect($tiers)
            ->map(fn ($t) => ['hours_before' => (float) ($t['hours_before'] ?? 0), 'refund_percent' => (int) ($t['refund_percent'] ?? 0)])
            ->sortByDesc('hours_before')->values()->all();
    }

    private function cannotCancelReason(Order $order): string
    {
        return match (true) {
            $order->isCancelled() => 'already cancelled',
            ! in_array($order->status, ['paid', 'deposit'], true) => 'not paid yet',
            default => 'the booked time has already started',
        };
    }
}
