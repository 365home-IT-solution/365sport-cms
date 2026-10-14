<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\PayrollPeriodResource\RelationManagers;

use App\Services\PayrollService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Employee\Entities\PayrollSlip;

class SlipsRelationManager extends RelationManager
{
    protected static string $relationship = 'slips';

    protected static ?string $title = 'Phiếu lương';

    public function table(Table $table): Table
    {
        $money = fn ($state) => number_format((float) $state, 0, ',', '.') . 'đ';

        return $table
            ->columns([
                TextColumn::make('employee_name')->label('Nhân viên')->searchable(),
                TextColumn::make('employee_code')->label('Mã NV')->placeholder('—'),
                TextColumn::make('base_amount')->label('Lương cơ bản')->formatStateUsing($money)->alignEnd(),
                TextColumn::make('piece_rate_amount')->label('Theo lượt')->formatStateUsing($money)->alignEnd(),
                TextColumn::make('allowances_total')->label('Phụ cấp')->formatStateUsing($money)->color('success')->alignEnd(),
                TextColumn::make('deductions_total')->label('Giảm trừ')->formatStateUsing(fn ($state) => '-' . $money($state))->color('danger')->alignEnd(),
                TextColumn::make('absence_deduction')->label('Trừ nghỉ không phép')->formatStateUsing(fn ($state) => '-' . $money($state))->color('danger')->alignEnd(),
                TextColumn::make('days_present')->label('Công')->alignEnd(),
                TextColumn::make('days_absent')->label('Nghỉ KP')->alignEnd(),
                TextColumn::make('final_amount')->label('Thực nhận')->formatStateUsing($money)->weight('bold')->alignEnd(),
                TextColumn::make('paid_at')->label('Đã trả')->dateTime('d/m/Y H:i')->placeholder('Chưa trả'),
            ])
            ->actions([
                Action::make('pay')->label('Đánh dấu đã trả')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (PayrollSlip $r) => $r->paid_at === null)
                    ->form([Select::make('method')->label('Hình thức')->options(['cash' => 'Tiền mặt', 'transfer' => 'Chuyển khoản'])->default('transfer')->required()])
                    ->action(function (array $data, PayrollSlip $record): void {
                        app(PayrollService::class)->markPaid($record, $data['method']);
                        Notification::make()->success()->title('Đã đánh dấu đã trả')->send();
                    }),
            ])
            ->headerActions([]);
    }
}
