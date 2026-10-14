<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Models\Partner;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\PayrollPeriodResource;

class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        $user = auth()->user();

        return [
            Action::make('close')
                ->label('Chốt lương tháng')
                ->icon('heroicon-o-lock-closed')
                ->form([
                    Select::make('month')->label('Tháng')->required()
                        ->options(collect(range(0, 11))->mapWithKeys(fn ($i) => [now()->subMonths($i)->format('Y-m') => now()->subMonths($i)->format('m/Y')])->all())
                        ->default(now()->subMonth()->format('Y-m')),
                    Select::make('partner_id')->label('Đối tác')->visible((bool) $user?->isSuperAdmin())->required((bool) $user?->isSuperAdmin())
                        ->options(fn () => Partner::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
                ])
                ->modalDescription('Tính lương cho mọi nhân viên đang làm việc theo công, phụ cấp, giảm trừ và lượt dọn sân/tư vấn của tháng được chọn, rồi khoá lại thành các phiếu lương.')
                ->action(function (array $data) use ($user): void {
                    $partnerId = $user->isSuperAdmin() ? ($data['partner_id'] ?? null) : $user->partner_id;
                    $result = app(PayrollService::class)->close($partnerId, $data['month'], $user);

                    $result['ok']
                        ? Notification::make()->success()->title('Đã chốt lương tháng ' . $data['month'])->send()
                        : Notification::make()->warning()->title(match (true) {
                            str_contains($result['message'], 'already closed') => 'Kỳ lương này đã được chốt trước đó.',
                            str_contains($result['message'], 'not started') => 'Chưa thể chốt lương cho tháng chưa bắt đầu.',
                            default => 'Không thể chốt lương.',
                        })->send();
                }),
        ];
    }
}
