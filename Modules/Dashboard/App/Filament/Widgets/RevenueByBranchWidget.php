<?php

declare(strict_types=1);

namespace Modules\Dashboard\App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Modules\Category\Entities\Category;
use Modules\Payment\Entities\Order;

// Doanh thu theo chi nhánh (category_id trên Order) trong tháng hiện tại — chỉ hiện khi đối tác
// quản lý NHIỀU HƠN 1 chi nhánh (canView()), vì với đối tác 1 chi nhánh biểu đồ này vô nghĩa.
class RevenueByBranchWidget extends ChartWidget
{
    protected static ?string $heading = 'Doanh thu theo chi nhánh (tháng này)';

    private const PALETTE = [
        '#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336',
        '#00BCD4', '#8BC34A', '#FFC107', '#3F51B5', '#E91E63',
    ];

    public function mount(): void
    {
        Carbon::setLocale('vi');
    }

    public static function canView(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        return method_exists($user, 'allowedBranchIds') && count($user->allowedBranchIds()) > 1;
    }

    protected function getData(): array
    {
        $rows = $this->branchFilteredOrderQuery()
            ->whereNotNull('category_id')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categoryNames = Category::whereIn('id', $rows->keys())->pluck('name', 'id');

        $labels = [];
        $data   = [];
        $colors = [];
        $i      = 0;
        foreach ($rows as $categoryId => $total) {
            $labels[] = $categoryNames[$categoryId] ?? 'Không xác định';
            $data[]   = (float) $total;
            $colors[] = self::PALETTE[$i % count(self::PALETTE)];
            $i++;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Doanh thu (VNĐ)',
                    'data'            => $data,
                    'backgroundColor' => $colors,
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
        return 'doughnut';
    }

    public function getColumnSpan(): int|string|array
    {
        return 1;
    }
}
