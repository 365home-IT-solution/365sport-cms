<?php

declare(strict_types=1);

namespace Modules\Dashboard\App\Filament\Widgets;

use App\Settings\GeneralSettings;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Modules\Payment\Entities\OrderItem;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomTimeSlot;

// Tỷ lệ lấp đầy 14 ngày gần nhất = (số OrderItem đã 'paid'/'completed' check-in đúng ngày) /
// (tổng số khung giờ CỐ ĐỊNH hàng tuần của các sân trong phạm vi đối tác, RoomTimeSlot có
// date IS NULL — xem ghi chú tại Order::broadcastSlotsReleasedByProduct()). Đây là ước lượng
// dựa trên lịch cố định, KHÔNG trừ các ngày sân bị block riêng lẻ (RoomTimeSlot::isBlockedOn())
// — đủ để nhìn xu hướng lấp đầy, không phải số liệu kế toán chính xác tuyệt đối.
class OccupancyRateWidget extends ChartWidget
{
    protected static ?string $heading = 'Tỷ lệ lấp đầy (14 ngày gần nhất)';

    public function mount(): void
    {
        Carbon::setLocale('vi');
    }

    protected function getData(): array
    {
        $theme        = app(GeneralSettings::class)->site_theme;
        $primaryColor = $theme['primary'] ?? '#4CAF50';

        $productIds = $this->scopedProductIds();
        $totalCapacityPerDay = empty($productIds)
            ? 0
            : RoomTimeSlot::whereIn('room_id', $productIds)->whereNull('date')->count();

        $days  = collect(range(13, 0))->map(fn ($i) => now()->subDays($i));
        $start = $days->first()->copy()->startOfDay();
        $end   = $days->last()->copy()->endOfDay();

        $booked = empty($productIds) ? collect() : OrderItem::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'completed']))
            ->whereBetween('checkin_date', [$start, $end])
            ->selectRaw('DATE(checkin_date) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $data   = [];
        foreach ($days as $day) {
            $bookedCount = (int) ($booked[$day->toDateString()] ?? 0);
            $rate        = $totalCapacityPerDay > 0 ? round(($bookedCount / $totalCapacityPerDay) * 100, 1) : 0;
            $labels[]    = $day->isoFormat('DD/MM');
            $data[]      = $rate;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Tỷ lệ lấp đầy (%)',
                    'data'            => $data,
                    'backgroundColor' => $primaryColor,
                ],
            ],
            'labels' => $labels,
        ];
    }

    // Sân trong phạm vi được xem: Product tự lọc theo partner (BelongsToPartner, giống Order/
    // Coupon), thu hẹp thêm theo chi nhánh (category) được phép nếu không phải superadmin.
    private function scopedProductIds(): array
    {
        $user = auth()->user();

        if (! $user || $user->isSuperAdmin()) {
            return Product::query()->pluck('id')->toArray();
        }

        $allCategoryIds = $user->allowedCategoryIds();
        if (empty($allCategoryIds)) {
            return Product::query()->pluck('id')->toArray();
        }

        return Product::whereHas('categories', fn ($q) => $q->whereIn('categories.id', $allCategoryIds))
            ->pluck('id')
            ->toArray();
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max'         => 100,
                    'ticks'       => ['precision' => 0],
                ],
            ],
        ];
    }

    public function getColumnSpan(): int|string|array
    {
        return 2;
    }
}
