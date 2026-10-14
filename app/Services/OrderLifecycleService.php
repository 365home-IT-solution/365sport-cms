<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Product\App\Models\TimeSlot;

// Vòng đời một đơn đặt sân: nhận sân (check-in), trả sân (check-out) và đổi giờ. Logic dùng CHUNG cho Filament (action trên bảng
// đơn), API admin và API khách — mỗi nơi chỉ lo hiển thị message theo ngôn ngữ riêng dựa trên 'code' trả về.
//
// Quy tắc:
//  - Chỉ đơn ĐÃ THANH TOÁN (paid/deposit), chưa huỷ, chưa đánh dấu không đến, chưa hoàn tiền do thời tiết mới nhận sân được.
//  - Nhận sân được từ CHECKIN_EARLY_MINUTES phút trước giờ chơi đầu tiên đến hết giờ chơi cuối cùng. Nhân viên có thể ép (force)
//    để sửa sai sót; khách quét mã thì luôn phải trong khung giờ.
//  - Đổi giờ giữ nguyên SÂN và độ dài từng khung (dịch cả đơn theo một khoảng thời gian), khung mới phải có trong danh mục khung giờ
//    của sân, còn trống và (với khách) không đắt hơn khung cũ. Khách tự đổi tối đa MAX_CUSTOMER_RESCHEDULES lần, tối thiểu
//    CUSTOMER_RESCHEDULE_MIN_HOURS giờ trước giờ chơi.
class OrderLifecycleService
{
    public const CHECKIN_EARLY_MINUTES = 30;
    public const MAX_CUSTOMER_RESCHEDULES = 1;
    public const CUSTOMER_RESCHEDULE_MIN_HOURS = 6;

    public function __construct(private readonly OrderRealtimeService $realtime) {}

    /** @return array{ok: true}|array{ok: false, code: string, message: string} */
    public function checkIn(Order $order, User|string|null $actor, bool $force = false): array
    {
        $result = DB::transaction(function () use ($order, $actor, $force): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->load('items');

            if ($blocked = $this->blockedReason($locked)) {
                return $blocked;
            }
            if (in_array($locked->order_status, ['checked_in', 'staying'], true)) {
                return $this->fail('already_checked_in', 'This order has already been checked in.');
            }
            if ($locked->order_status === 'checked_out') {
                return $this->fail('already_checked_out', 'This order has already been checked out.');
            }

            if (! $force) {
                $start = $locked->items->pluck('checkin_date')->filter()->min();
                $end = $locked->items->pluck('checkout_date')->filter()->max();
                if ($start && now()->lt($start->copy()->subMinutes(self::CHECKIN_EARLY_MINUTES))) {
                    return $this->fail('too_early', 'Check-in opens ' . self::CHECKIN_EARLY_MINUTES . ' minutes before the booked time.');
                }
                if ($end && now()->gt($end)) {
                    return $this->fail('too_late', 'The booked time has already ended.');
                }
            }

            $locked->update([
                'order_status' => 'checked_in',
                'checked_in_at' => now(),
                'checked_in_by' => $this->actorId($actor),
            ]);

            return ['ok' => true];
        });

        if ($result['ok']) {
            $order->refresh();
            $this->realtime->broadcastCheckin((string) $order->order_code, 'checkin', $order->checked_in_at?->toIso8601String());
        }

        return $result;
    }

    /** @return array{ok: true}|array{ok: false, code: string, message: string} */
    public function checkOut(Order $order, User|string|null $actor): array
    {
        $result = DB::transaction(function () use ($order, $actor): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->order_status === 'checked_out') {
                return $this->fail('already_checked_out', 'This order has already been checked out.');
            }
            if (! in_array($locked->order_status, ['checked_in', 'staying'], true)) {
                return $this->fail('not_checked_in', 'The order must be checked in before it can be checked out.');
            }

            $locked->update([
                'order_status' => 'checked_out',
                'checked_out_at' => now(),
                'checked_out_by' => $this->actorId($actor),
            ]);

            return ['ok' => true];
        });

        if ($result['ok']) {
            $order->refresh();
            $this->realtime->broadcastCheckin((string) $order->order_code, 'checkout', $order->checked_out_at?->toIso8601String());
        }

        return $result;
    }

    /**
     * Đổi giờ: dịch TOÀN BỘ các khung của đơn sao cho khung đầu tiên bắt đầu lúc $newStart (các khung sau giữ nguyên khoảng cách).
     *
     * @return array{ok: true, new_start: string, new_end: string}|array{ok: false, code: string, message: string}
     */
    public function reschedule(Order $order, Carbon $newStart, User|string|null $actor, bool $byCustomer, bool $allowPriceDifference = false): array
    {
        $oldGroups = null;

        $result = DB::transaction(function () use ($order, $newStart, $actor, $byCustomer, $allowPriceDifference, &$oldGroups): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->load('items');

            if ($blocked = $this->blockedReason($locked)) {
                return $blocked;
            }
            if (in_array($locked->order_status, ['checked_in', 'staying', 'checked_out'], true)) {
                return $this->fail('already_started', 'This order has already started and can no longer be moved.');
            }

            $items = $locked->items->filter(fn ($i) => $i->checkin_date && $i->checkout_date)->sortBy('checkin_date')->values();
            if ($items->isEmpty()) {
                return $this->fail('no_slots', 'This order has no time slots to move.');
            }

            $oldStart = $items->first()->checkin_date->copy();
            if ($byCustomer) {
                if ((int) $locked->rescheduled_count >= self::MAX_CUSTOMER_RESCHEDULES) {
                    return $this->fail('limit_reached', 'You have already used the maximum number of reschedules for this order.');
                }
                if (now()->diffInHours($oldStart, false) < self::CUSTOMER_RESCHEDULE_MIN_HOURS) {
                    return $this->fail('too_late', 'Orders can only be moved at least ' . self::CUSTOMER_RESCHEDULE_MIN_HOURS . ' hours before the booked time.');
                }
            }
            if ($newStart->lte(now())) {
                return $this->fail('past', 'The new time must be in the future.');
            }
            if ($newStart->equalTo($oldStart)) {
                return $this->fail('same_time', 'The new time is the same as the current time.');
            }

            $deltaSeconds = $oldStart->diffInSeconds($newStart, false);
            $moves = [];
            $oldTotal = 0;
            $newTotal = 0;

            foreach ($items as $item) {
                $start = $item->checkin_date->copy()->addSeconds($deltaSeconds);
                $end = $item->checkout_date->copy()->addSeconds($deltaSeconds);

                $oldCatalog = $this->catalogPrice((string) $item->product_id, $item->checkin_date, $item->checkout_date);
                $newCatalog = $this->catalogPrice((string) $item->product_id, $start, $end);
                if ($newCatalog === null) {
                    return $this->fail('slot_unavailable', 'That time is not offered for this court.');
                }
                if ($this->hasConflict((string) $item->product_id, $start, $end, $locked->id)) {
                    return $this->fail('slot_taken', 'That time slot is already booked.');
                }

                $qty = max(1, (int) ($item->quantity ?? 1));
                $oldTotal += ($oldCatalog ?? (int) $item->price) * $qty;
                $newTotal += $newCatalog * $qty;
                $moves[] = [$item, $start, $end];
            }

            if ($newTotal > $oldTotal && ! $allowPriceDifference) {
                return $this->fail('price_higher', 'The new time costs more than the current time. Please book a new slot or contact the venue.');
            }

            $oldGroups = $locked->items->whereNotNull('product_id')->groupBy('product_id');

            foreach ($moves as [$item, $start, $end]) {
                $item->update(['checkin_date' => $start, 'checkout_date' => $end, 'checkin_datetime' => $start, 'checkout_datetime' => $end, 'checkin_notified' => false, 'checkout_notified' => false, 'expiry_notified' => false]);
            }

            $locked->update([
                'rescheduled_count' => (int) $locked->rescheduled_count + 1,
                'rescheduled_at' => now(),
            ]);

            return [
                'ok' => true,
                'new_start' => $items->first()->fresh()->checkin_date->toIso8601String(),
                'new_end' => $items->last()->fresh()->checkout_date->toIso8601String(),
            ];
        });

        if (! $result['ok']) {
            return $result;
        }

        $order->refresh()->load('items');
        if ($oldGroups) {
            Order::broadcastSlotsReleasedByProduct($oldGroups);
            Order::notifyWaitlistByProduct($oldGroups);
        }
        $this->realtime->broadcastOrderUpdate((string) $order->order_code, [
            'status' => $order->status,
            'rescheduled_at' => $order->rescheduled_at?->toIso8601String(),
            'new_checkin_date' => $result['new_start'],
            'new_checkout_date' => $result['new_end'],
        ], $order->customer_id);

        Log::info('Order rescheduled', ['order_id' => $order->id, 'order_code' => $order->order_code, 'by_customer' => $byCustomer, 'actor' => $this->actorId($actor)]);

        return $result;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    /** @return array{ok: false, code: string, message: string}|null */
    private function blockedReason(Order $order): ?array
    {
        if ($order->isCancelled()) {
            return $this->fail('cancelled', 'This order has been cancelled.');
        }
        if (! in_array($order->status, ['paid', 'deposit'], true)) {
            return $this->fail('not_paid', 'Only paid or deposit orders can be used.');
        }
        if ($order->isNoShow()) {
            return $this->fail('no_show', 'This order was marked as no-show.');
        }
        if ($order->isWeatherAffected() && $order->weather_resolution === 'refunded') {
            return $this->fail('refunded', 'This order was refunded because of the weather.');
        }

        return null;
    }

    /** Giá niêm yết của khung [start, end) cho sân (ưu tiên giá riêng theo ngày), null nếu sân không cung cấp khung này. */
    private function catalogPrice(string $productId, Carbon $start, Carbon $end): ?int
    {
        $slot = TimeSlot::query()
            ->where('start_time', $start->format('H:i:s'))
            ->where('end_time', $end->format('H:i:s'))
            ->pluck('id');
        if ($slot->isEmpty()) {
            return null;
        }

        $rows = RoomTimeSlot::query()
            ->where('room_id', $productId)
            ->whereIn('timeslot_id', $slot)
            ->where('status', 'available')
            ->where(fn ($q) => $q->whereNull('date')->orWhereDate('date', $start->toDateString()))
            ->orderByRaw('date IS NULL')
            ->get();

        foreach ($rows as $row) {
            $blocked = (array) ($row->settings['blocked_dates'] ?? []);
            if (in_array($start->toDateString(), $blocked, true)) {
                continue;
            }

            return (int) $row->price;
        }

        return null;
    }

    private function hasConflict(string $productId, Carbon $start, Carbon $end, int $excludeOrderId): bool
    {
        return OrderItem::query()
            ->where('product_id', $productId)
            ->whereNotNull('checkin_date')
            ->whereNotNull('checkout_date')
            ->where('checkin_date', '<', $end)
            ->where('checkout_date', '>', $start)
            ->where('order_id', '!=', $excludeOrderId)
            ->whereHas('order', fn ($q) => $q->holdingSlot()->whereIn('status', ['pending', 'paid', 'deposit', 'shipped']))
            ->exists();
    }

    private function actorId(User|string|null $actor): string
    {
        return $actor instanceof User ? (string) $actor->getKey() : ($actor ?: 'system');
    }

    /** @return array{ok: false, code: string, message: string} */
    private function fail(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message];
    }
}
