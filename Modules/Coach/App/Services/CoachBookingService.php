<?php

namespace Modules\Coach\App\Services;

use Illuminate\Validation\ValidationException;
use Modules\Category\Entities\Category;
use Modules\Coach\App\Models\Coach;
use Modules\Coach\App\Models\OrderCoach;
use Modules\Payment\Entities\Order;
use Modules\Product\App\Models\Product;

class CoachBookingService
{
    public function quote(?int $coachId, Product $room, array $items): array
    {
        if (! $coachId) return [0, null, []];

        $branchId = $this->branchId($room);
        $coach = Coach::query()->whereKey($coachId)->where('category_id', $branchId)->where('is_active', true)->first();
        if (! $coach) {
            throw ValidationException::withMessages(['coach_id' => ['HLV không thuộc chi nhánh của sân hoặc đã ngừng nhận lịch.']]);
        }

        $rows = [];
        foreach ($items as $item) {
            if (empty($item['checkin_date']) || empty($item['checkout_date'])) continue;
            $start = \Carbon\Carbon::parse($item['checkin_date']);
            $end = \Carbon\Carbon::parse($item['checkout_date']);
            $minutes = $start->diffInMinutes($end);
            $rows[] = [
                'coach_id' => $coach->id, 'coach_name' => $coach->name,
                'hourly_rate' => $coach->hourly_rate, 'minutes' => $minutes,
                'subtotal' => (int) ceil($coach->hourly_rate * $minutes / 60),
                'starts_at' => $start, 'ends_at' => $end,
            ];
        }

        return [array_sum(array_column($rows, 'subtotal')), $coach, $rows];
    }

    public function reserve(Order $order, Coach $quotedCoach, array $rows): void
    {
        $coach = Coach::query()->lockForUpdate()->whereKey($quotedCoach->id)->where('is_active', true)->first();
        if (! $coach || $coach->category_id !== $order->category_id) {
            throw ValidationException::withMessages(['coach_id' => ['HLV không còn khả dụng tại chi nhánh này.']]);
        }

        foreach ($rows as $row) {
            $conflict = OrderCoach::query()->where('coach_id', $coach->id)
                ->where('starts_at', '<', $row['ends_at'])->where('ends_at', '>', $row['starts_at'])
                ->whereHas('order', fn ($q) => self::scopeHoldingOrders($q))
                ->lockForUpdate()->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['coach_id' => ['HLV vừa được đặt trong một khung giờ đã chọn. Vui lòng chọn HLV khác.']]);
            }
            $order->coachBookings()->create($row);
        }
    }

    // Đơn còn hiệu lực đang giữ lịch HLV — dùng chung cho mọi nơi kiểm tra trùng lịch HLV.
    public static function scopeHoldingOrders($query): void
    {
        $query->whereNull('cancelled_at')->whereIn('status', ['pending', 'deposit', 'paid', 'shipped']);
    }

    /**
     * Trong số $coachIds, HLV nào ĐÃ có lịch (ở bất kỳ sân nào) trùng ít nhất 1 khoảng trong
     * $periods ([[Carbon $start, Carbon $end], ...]) — 1 HLV chỉ đứng được 1 sân tại 1 thời điểm.
     */
    public static function busyCoachIds(array $coachIds, array $periods): array
    {
        if (empty($coachIds) || empty($periods)) {
            return [];
        }

        return OrderCoach::query()
            ->whereIn('coach_id', $coachIds)
            ->where(function ($q) use ($periods) {
                foreach ($periods as [$start, $end]) {
                    $q->orWhere(fn ($p) => $p->where('starts_at', '<', $end)->where('ends_at', '>', $start));
                }
            })
            ->whereHas('order', fn ($q) => self::scopeHoldingOrders($q))
            ->distinct()
            ->pluck('coach_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function branchId(Product $room): ?int
    {
        $category = $room->categories()->first();
        for ($i = 0; $i < 5 && $category?->parent_id; $i++) {
            $category = Category::find($category->parent_id) ?? $category;
        }
        return $category?->id;
    }
}
