<?php

declare(strict_types=1);

namespace Modules\Dashboard\App\Filament\Widgets;

use App\Settings\GeneralSettings;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;

// Phân bố booking theo giờ trong ngày (group theo giờ của checkin_date), 90 ngày gần nhất —
// giúp đối tác biết khung giờ nào đông khách nhất để lên kế hoạch nhân sự/khuyến mãi.
class PeakHourWidget extends ChartWidget
{
    protected static ?string $heading = 'Giờ cao điểm (90 ngày gần nhất)';

    public function mount(): void
    {
        Carbon::setLocale('vi');
    }

    protected function getData(): array
    {
        $theme        = app(GeneralSettings::class)->site_theme;
        $primaryColor = $theme['primary'] ?? '#4CAF50';

        $orderIds = $this->branchFilteredOrderQuery()
            ->where('created_at', '>=', now()->subDays(90))
            ->pluck('id');

        $counts = $orderIds->isEmpty() ? collect() : OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('HOUR(checkin_date) as h, COUNT(*) as c')
            ->groupBy('h')
            ->pluck('c', 'h');

        $labels = [];
        $data   = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $data[]   = (int) ($counts[$h] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Số lượt đặt',
                    'data'            => $data,
                    'backgroundColor' => $primaryColor,
                ],
            ],
            'labels' => $labels,
        ];
    }

    private function branchFilteredOrderQuery(): Builder
    {
        $query = Order::query()->whereIn('status', ['paid', 'completed']);
        $user  = auth()->user();

        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }

        $allCategoryIds = $user->allowedCategoryIds();
        if (empty($allCategoryIds)) {
            return $query;
        }

        return $query->whereIn('category_id', $allCategoryIds);
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
