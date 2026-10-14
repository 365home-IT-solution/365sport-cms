<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;
use App\Models\User;

// Chính sách hoãn/hoàn tiền do THỜI TIẾT cho đơn đặt sân NGOÀI TRỜI (xem room_types.is_outdoor,
// suy ra qua Product::isOutdoor()) —
// trước đây trời mưa làm khách không thể chơi phải xử lý tay ngoài hệ thống (nhắn tin/gọi điện),
// không có luồng tự phục vụ nào và KHÔNG lưu dấu vết gì trên đơn (xem yêu cầu rà soát nghiệp vụ đặt
// sân — "chính sách hoãn/hoàn tiền do thời tiết cho sân ngoài trời bị mưa"). Dùng weather_affected_at
// làm NGUỒN SỰ THẬT DUY NHẤT cho "đơn này đã được xử lý do thời tiết chưa" (xem Order::
// isWeatherAffected()) — KHÔNG đổi cột `status` (giữ nguyên 'paid'/'deposit' để báo cáo doanh thu
// không sai lệch, tương tự OrderCancellationService). Chiều hoàn tiền tái dùng CHÍNH XÁC cơ chế
// extra_refund_amount/method/paid_at đã có sẵn (ExtraChargeService::recordPendingRefund()) — admin
// xử lý hoàn tiền thật qua panel "Hoàn tiền chưa xử lý" đã có, không xây thêm màn hình riêng.
class WeatherPolicyService
{
    // Chỉ áp dụng hoãn/hoàn do thời tiết cho đơn có ngày nhận sân trong vòng N ngày tới (kể từ hôm
    // nay) — đơn "hôm nay hoặc sắp tới" mới thật sự có rủi ro thời tiết CẦN xử lý ngay; đơn đã xa
    // trong tương lai chưa cần quyết định vội, đơn đã lùi xa trong quá khứ thì trận đấu đã diễn ra/
    // đã lỡ hẹn từ lâu, "do mưa" lúc này không còn ý nghĩa (dùng huỷ đơn thường nếu cần).
    public const NEAR_FUTURE_DAYS = 3;

    public function __construct(
        private readonly ExtraChargeService $extraChargeService,
        private readonly OrderRealtimeService $orderRealtimeService,
    ) {}

    /**
     * Hoàn tiền TOÀN BỘ cho đơn bị hoãn do mưa — không giữ chỗ nữa, khách không tự chọn lại ngày
     * (khác reschedule() bên dưới). Chỉ áp dụng cho sân ngoài trời (room_types.is_outdoor), đơn đã
     * thanh toán (cọc/đủ),
     * CHƯA từng xử lý thời tiết/huỷ, và có ngày nhận sân trong khoảng "gần đây" (xem
     * NEAR_FUTURE_DAYS) — không cho áp dụng cho đơn đã cũ, đã qua từ lâu.
     *
     * @return array{ok: true, refund_amount: int}|array{ok: false, error: string}
     */
    public function refund(Order $order, ?User $admin = null): array
    {
        $result = DB::transaction(function () use ($order): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->load('items.product.roomType');

            $eligibility = $this->checkEligibility($locked);
            if (! $eligibility['ok']) {
                return $eligibility;
            }

            // Đơn deposit: số ĐÃ thu thật là 'amount' (tiền cọc); đơn paid: 'full_amount' (đã trả
            // đủ) — cùng công thức với Order::computeCustomerCancelRefundAmount(), chỉ khác TỶ LỆ
            // hoàn (100% ở đây vì lỗi KHÔNG phải do khách, khác 70% khi khách tự huỷ).
            $paidByPackage = $locked->isPaidByPackage();
            $refundAmount = $paidByPackage
                ? $locked->packageCashAmount() // dùng gói: trả lại buổi vào gói (bên dưới), chỉ hoàn phần tiền mặt đã trả (nếu có)
                : ($locked->status === 'deposit'
                    ? (int) ($locked->amount ?? 0)
                    : (int) ($locked->full_amount ?? $locked->amount ?? 0));

            $locked->update([
                'weather_affected_at' => now(),
                'weather_resolution'  => 'refunded',
            ]);

            if ($paidByPackage) {
                \App\Models\CustomerPackage::refundRedemptionsForOrder($locked);
            }
            if ($refundAmount > 0) {
                $this->extraChargeService->recordPendingRefund($locked, $refundAmount);
            }

            return ['ok' => true, 'refund_amount' => $refundAmount];
        });

        if (! $result['ok']) {
            return $result;
        }

        $order->refresh()->load('items');

        // Giải phóng khung giờ/ngày real-time — cùng cơ chế dùng khi khách TỰ huỷ đơn (xem
        // OrderCancellationService::cancelByCustomer()), sân khác đang xem thấy trống lại ngay.
        $itemsByProduct = $order->items->whereNotNull('product_id')->groupBy('product_id');
        Order::broadcastSlotsReleasedByProduct($itemsByProduct);
        Order::notifyWaitlistByProduct($itemsByProduct);

        $this->orderRealtimeService->broadcastOrderUpdate($order->order_code, [
            'status'               => $order->status,
            'weather_affected_at'  => $order->weather_affected_at->toIso8601String(),
            'weather_resolution'   => $order->weather_resolution,
            'refund_amount'        => $result['refund_amount'],
        ], $order->customer_id);

        Log::info('Order weather refund', [
            'order_id'      => $order->id,
            'order_code'    => $order->order_code,
            'refund_amount' => $result['refund_amount'],
            'admin_id'      => $admin?->id,
        ]);

        return $result;
    }

    /**
     * Dời khung giờ/ngày của đơn sang ngày mới do mưa. Có thể thu thêm PHÍ ĐỔI LỊCH ($fee, VND):
     * cộng vào extra_charge_amount của đơn (khoản phát sinh chưa thu — admin xác nhận tiền mặt/
     * chuyển khoản/PayOS qua luồng phát sinh sẵn có, xem ExtraChargeService). $fee = 0 thì đổi
     * lịch thuần tuý. Chỉ dời được nếu khung giờ mới CÒN TRỐNG (dùng đúng kiểu kiểm tra trùng
     * lịch như lúc tạo đơn — xem BuildsRoomBooking::buildDailyItems()/buildSlotItems()).
     *
     * Đơn giản hoá cho MVP: dời TOÀN BỘ items của đơn sang cùng 1 khoảng [newCheckin, newCheckout)
     * (hệ thống hiện giới hạn 1 sân/đơn — xem ghi chú "chỉ giới hạn 1 sân/đơn" ở OrderTable.php),
     * giữ nguyên khoảng cách giờ giữa các item nếu có nhiều item (vd nhiều khung giờ cùng ngày) sẽ
     * KHÔNG áp dụng — MVP chỉ xử lý đúng 1 khoảng ngày/giờ mới cho toàn đơn.
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function reschedule(
        Order $order,
        string $newCheckinDate,
        string $newCheckoutDate,
        ?User $admin = null,
        int $fee = 0,
        ?string $slotLabel = null,
    ): array {
        $fee = max(0, $fee);

        $newCheckin  = Carbon::parse($newCheckinDate);
        $newCheckout = Carbon::parse($newCheckoutDate);

        if ($newCheckout->lte($newCheckin)) {
            return ['ok' => false, 'error' => 'New checkout time must be after the new checkin time.'];
        }

        $result = DB::transaction(function () use ($order, $newCheckin, $newCheckout, $fee, $slotLabel): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->load('items.product.roomType');

            $eligibility = $this->checkEligibility($locked);
            if (! $eligibility['ok']) {
                return $eligibility;
            }

            $productIds = $locked->items->pluck('product_id')->filter()->unique();

            // Cùng kiểu kiểm tra trùng lịch dùng lúc tạo đơn (xem BuildsRoomBooking::
            // buildDailyItems()/buildSlotItems()) — loại trừ CHÍNH đơn đang dời (excludeOrderId)
            // vì items cũ của nó vẫn còn nguyên trong DB tới khi update() bên dưới chạy xong.
            foreach ($productIds as $productId) {
                $conflict = OrderItem::where('product_id', $productId)
                    ->whereNotNull('checkin_date')
                    ->whereNotNull('checkout_date')
                    ->where('checkin_date', '<', $newCheckout)
                    ->where('checkout_date', '>', $newCheckin)
                    ->where('order_id', '!=', $locked->id)
                    ->whereHas('order', fn ($q) => $q->whereIn('status', ['pending', 'paid', 'deposit', 'shipped']))
                    ->exists();

                if ($conflict) {
                    return ['ok' => false, 'error' => 'The new time slot is already booked for this court.'];
                }
            }

            // Giữ nguyên "chiều dài" của từng item (không co giãn theo khoảng mới) — chỉ dịch mốc
            // bắt đầu của item ĐẦU TIÊN đúng bằng $newCheckin, các item sau nối tiếp theo đúng thứ
            // tự và độ dài cũ. Đủ dùng cho MVP: đa số đơn sân ngoài trời chỉ có 1 item (1 khung giờ/
            // 1 ngày) — xem ghi chú "chỉ giới hạn 1 sân/đơn" ở OrderTable.php.
            $itemsSorted = $locked->items->sortBy('checkin_date')->values();
            $cursor      = $newCheckin->copy();

            foreach ($itemsSorted as $item) {
                if (! $item->checkin_date || ! $item->checkout_date) {
                    continue;
                }

                $durationMinutes = $item->checkin_date->diffInMinutes($item->checkout_date);
                $itemNewCheckin  = $cursor->copy();
                $itemNewCheckout = $cursor->copy()->addMinutes($durationMinutes);

                $itemUpdate = [
                    'checkin_date'  => $itemNewCheckin,
                    'checkout_date' => $itemNewCheckout,
                ];

                // Đơn 1 khung giờ: cập nhật nhãn khung giờ hiển thị cho khớp khung giờ mới.
                if ($slotLabel !== null && $itemsSorted->count() === 1) {
                    $itemUpdate['slot_label'] = $slotLabel;
                }

                $item->update($itemUpdate);

                $cursor = $itemNewCheckout->copy();
            }

            $oldItemsByProduct = $locked->items->whereNotNull('product_id')->groupBy('product_id');

            $orderUpdate = [
                'weather_affected_at' => now(),
                'weather_resolution'  => 'rescheduled',
            ];

            // Phí đổi lịch → khoản phát sinh chưa thu; xoá dấu "đã thu" cũ (nếu có) để khoản mới
            // hiện là còn phải thu.
            if ($fee > 0) {
                $orderUpdate['extra_charge_amount']  = (int) ($locked->extra_charge_amount ?? 0) + $fee;
                $orderUpdate['extra_charge_paid_at'] = null;
            }

            $locked->update($orderUpdate);

            return ['ok' => true, 'items_by_product' => $oldItemsByProduct];
        });

        if (! $result['ok']) {
            return $result;
        }

        $order->refresh()->load('items');

        // Giải phóng khung giờ/ngày CŨ real-time — khung giờ vừa được dời đi giờ trống lại, khách
        // khác đang xem đúng sân này thấy trống ngay (cùng cơ chế OrderCancellationService dùng).
        Order::broadcastSlotsReleasedByProduct($result['items_by_product']);
        Order::notifyWaitlistByProduct($result['items_by_product']);

        $this->orderRealtimeService->broadcastOrderUpdate($order->order_code, [
            'status'              => $order->status,
            'weather_affected_at' => $order->weather_affected_at->toIso8601String(),
            'weather_resolution'  => $order->weather_resolution,
            'new_checkin_date'    => $order->items->min('checkin_date')?->toIso8601String(),
            'new_checkout_date'   => $order->items->max('checkout_date')?->toIso8601String(),
            'reschedule_fee'      => $fee,
        ], $order->customer_id);

        Log::info('Order weather reschedule', [
            'order_id'   => $order->id,
            'order_code' => $order->order_code,
            'new_checkin_date'  => $newCheckin->toIso8601String(),
            'new_checkout_date' => $newCheckout->toIso8601String(),
            'reschedule_fee'    => $fee,
            'admin_id'   => $admin?->id,
        ]);

        return ['ok' => true];
    }

    /**
     * Điều kiện dùng chung cho refund()/reschedule(): sân NGOÀI TRỜI (room_types.is_outdoor), đơn
     * đã thanh toán (cọc/đủ),
     * CHƯA từng huỷ/xử lý thời tiết trước đó, và ngày nhận sân SỚM NHẤT nằm trong khoảng "hôm nay
     * tới NEAR_FUTURE_DAYS ngày tới" — không áp dụng cho đơn đã cũ/đã qua từ lâu (xem hằng số
     * NEAR_FUTURE_DAYS ở trên để biết lý do).
     *
     * @return array{ok: true}|array{ok: false, error: string}
     */
    private function checkEligibility(Order $order): array
    {
        if ($order->isCancelled()) {
            return ['ok' => false, 'error' => 'This order has already been cancelled.'];
        }

        if (! is_null($order->weather_affected_at)) {
            return ['ok' => false, 'error' => 'This order has already been processed for a weather issue.'];
        }

        if (! in_array($order->status, ['paid', 'deposit'], true)) {
            return ['ok' => false, 'error' => 'This order must be paid or deposited to apply the weather policy.'];
        }

        $isOutdoor = $order->items->contains(fn ($item) => $item->product?->isOutdoor() ?? false);
        if (! $isOutdoor) {
            return ['ok' => false, 'error' => 'The weather policy only applies to outdoor courts.'];
        }

        $earliestCheckin = $order->items->pluck('checkin_date')->filter()->min();
        if (! $earliestCheckin) {
            return ['ok' => false, 'error' => 'This order has no check-in date to evaluate.'];
        }

        $today          = Carbon::today();
        $nearFutureEnd  = Carbon::today()->addDays(self::NEAR_FUTURE_DAYS)->endOfDay();

        if ($earliestCheckin->lt($today) || $earliestCheckin->gt($nearFutureEnd)) {
            return ['ok' => false, 'error' => 'The weather policy only applies to bookings for today or the next ' . self::NEAR_FUTURE_DAYS . ' days.'];
        }

        return ['ok' => true];
    }
}
