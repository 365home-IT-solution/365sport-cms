<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CustomerPackage;
use App\Models\PackageRedemption;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\Payment\Entities\Order;

// Khách TỰ huỷ đơn/xin hoàn tiền online (trước đây chỉ admin huỷ tay qua điện thoại/hotline,
// không có luồng tự phục vụ nào — xem yêu cầu rà soát nghiệp vụ đặt sân). Dùng cancelled_at làm
// NGUỒN SỰ THẬT DUY NHẤT cho "đơn còn giữ chỗ hay không" (xem Order::canBeCancelledByCustomer()) —
// KHÔNG đổi cột `status` (giữ nguyên 'paid'/'deposit' để báo cáo doanh thu không sai lệch). Số tiền
// hoàn tái dùng CHÍNH XÁC cơ chế extra_refund_amount/method/paid_at đã có sẵn
// (ExtraChargeService::recordPendingRefund()/markRefundAsDone()) — admin xử lý hoàn tiền qua panel
// "Hoàn tiền chưa xử lý" đã có trong OrderForm, không cần xây thêm màn hình riêng.
class OrderCancellationService
{
    public function __construct(
        private readonly ExtraChargeService $extraChargeService,
        private readonly OrderRealtimeService $orderRealtimeService,
    ) {}

    /**
     * @return array{ok: true, refund_amount: int}|array{ok: false, error: string}
     */
    public function cancelByCustomer(Order $order, ?string $reason = null): array
    {
        $result = DB::transaction(function () use ($order, $reason): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->load('items');

            if (! $locked->canBeCancelledByCustomer()) {
                return ['ok' => false, 'error' => 'Đơn này không thể huỷ (đã huỷ trước đó, chưa thanh toán, hoặc đã tới/qua giờ nhận sân).'];
            }

            $paidByPackage = PackageRedemption::where('order_id', $locked->id)
                ->whereNull('refunded_at')->exists();
            $refundAmount = $paidByPackage ? 0 : $locked->computeCustomerCancelRefundAmount();

            $locked->update([
                'cancelled_at'  => now(),
                'cancelled_by'  => 'customer',
                'cancel_reason' => $reason,
            ]);

            if ($paidByPackage) {
                CustomerPackage::refundRedemptionsForOrder($locked);
            } elseif ($refundAmount > 0) {
                $this->extraChargeService->recordPendingRefund($locked, $refundAmount);
            }

            return ['ok' => true, 'refund_amount' => $refundAmount];
        });

        if (! $result['ok']) {
            return $result;
        }

        $refundAmount = $result['refund_amount'];
        $order->refresh()->load('items');

        // Giải phóng khung giờ/ngày real-time — khách khác đang xem đúng sân này thấy trống lại
        // ngay, không cần đợi tải lại trang (cùng cơ chế dùng khi XOÁ đơn, xem
        // Order::broadcastSlotsReleasedByProduct()).
        $itemsByProduct = $order->items->whereNotNull('product_id')->groupBy('product_id');
        Order::broadcastSlotsReleasedByProduct($itemsByProduct);
        Order::notifyWaitlistByProduct($itemsByProduct);

        // Báo cho bất kỳ ai đang mở trang tra cứu đơn (booking-detail.blade.php) — admin đang xem
        // cùng lúc, hoặc chính khách mở 2 tab.
        $this->orderRealtimeService->broadcastOrderUpdate($order->order_code, [
            'status'        => $order->status,
            'cancelled_at'  => $order->cancelled_at->toIso8601String(),
            'refund_amount' => $refundAmount,
        ], $order->customer_id);

        Log::info('Order cancelled by customer', [
            'order_id'      => $order->id,
            'order_code'    => $order->order_code,
            'refund_amount' => $refundAmount,
            'reason'        => $reason,
        ]);

        return ['ok' => true, 'refund_amount' => $refundAmount];
    }
}
