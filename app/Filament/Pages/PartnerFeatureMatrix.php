<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\PartnerPlanResource;
use App\Models\PartnerPlan;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

// Trang riêng để chỉnh SỐ CHI NHÁNH TỐI ĐA + BẬT/TẮT TỪNG TÍNH NĂNG của TẤT CẢ gói đối tác cùng
// lúc, dạng ma trận (hàng = tính năng, cột = gói) — thay vì phải mở từng gói ở PartnerPlanResource
// để tick/untick riêng lẻ (vẫn còn dùng được, 2 nơi cùng sửa 1 dữ liệu). Chỉ super_admin dùng,
// cùng mức nhạy cảm với PartnerPlanResource/PartnerResource.
class PartnerFeatureMatrix extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Tính năng theo gói';

    protected static ?string $title = 'Tính năng theo gói đối tác';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.partner-feature-matrix';

    // planId => ['max_branches' => int|null, 'features' => [featureKey => bool]]
    public array $matrix = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        foreach ($this->plans() as $plan) {
            $this->matrix[$plan->id] = [
                'max_branches' => $plan->max_branches,
                'features'     => collect(PartnerPlanResource::FEATURE_OPTIONS)
                    ->mapWithKeys(fn ($label, $key) => [$key => $plan->hasFeature($key)])
                    ->all(),
            ];
        }
    }

    public function plans()
    {
        return PartnerPlan::orderBy('sort_order')->get();
    }

    public function save(): void
    {
        foreach ($this->matrix as $planId => $row) {
            $features = collect($row['features'] ?? [])
                ->filter()
                ->keys()
                ->values()
                ->all();

            PartnerPlan::whereKey($planId)->update([
                'max_branches' => $row['max_branches'] !== '' && $row['max_branches'] !== null
                    ? (int) $row['max_branches']
                    : null,
                'features' => $features,
            ]);
        }

        Notification::make()
            ->title('Đã lưu tính năng theo gói')
            ->success()
            ->send();
    }
}
