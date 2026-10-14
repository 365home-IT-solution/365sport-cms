<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerSettlementResource\Pages;

use App\Filament\Resources\PartnerSettlementResource;
use App\Models\Partner;
use App\Services\PartnerSettlementService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPartnerSettlements extends ListRecords
{
    protected static string $resource = PartnerSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Lập đối soát tháng')
                ->icon('heroicon-o-calculator')
                ->visible(fn () => (bool) auth()->user()?->isSuperAdmin())
                ->form([
                    Select::make('month')->label('Tháng')->required()
                        ->options(collect(range(1, 12))->mapWithKeys(fn ($i) => [now()->subMonths($i)->format('Y-m') => now()->subMonths($i)->format('m/Y')])->all())
                        ->default(now()->subMonth()->format('Y-m')),
                    Select::make('partner_id')->label('Đối tác (bỏ trống = tất cả đối tác đã duyệt)')
                        ->options(fn () => Partner::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
                ])
                ->modalDescription('Tính doanh thu ròng của tháng (thực thu trừ hoàn tiền) nhân tỉ lệ hoa hồng trong hợp đồng. Lập lại sẽ tính lại các bảng còn chờ thu.')
                ->action(function (array $data): void {
                    $service = app(PartnerSettlementService::class);
                    $partners = Partner::query()
                        ->when(! empty($data['partner_id']), fn ($q) => $q->whereKey($data['partner_id']), fn ($q) => $q->where('verification_status', 'approved'))
                        ->get();

                    $done = 0;
                    foreach ($partners as $partner) {
                        $done += $service->generate($partner, $data['month'], auth()->user())['ok'] ? 1 : 0;
                    }

                    Notification::make()->success()->title("Đã lập {$done} bảng đối soát tháng {$data['month']}")->send();
                }),
        ];
    }
}
