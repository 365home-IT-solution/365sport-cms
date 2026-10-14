<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources;

use App\Filament\Support\PartnerTableHelpers;
use App\Services\PayrollService;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Employee\App\Filament\Resources\PayrollPeriodResource\Pages;
use Modules\Employee\App\Filament\Resources\PayrollPeriodResource\RelationManagers\SlipsRelationManager;
use Modules\Employee\Entities\PayrollPeriod;

// Kỳ lương theo tháng: chốt lương tạo phiếu lương cho từng nhân viên (ảnh chụp số liệu lúc chốt), sau đó đánh dấu đã trả từng phiếu.
// Không sửa tay số tiền: muốn tính lại thì mở lại kỳ (chỉ khi chưa trả phiếu nào) rồi chốt lại. Nghiệp vụ ở App\Services\PayrollService.
class PayrollPeriodResource extends Resource
{
    use \App\Filament\Support\GatedByFeature;

    protected static string $featureFlag = 'payroll';

    protected static ?string $model = PayrollPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Phân quyền';

    protected static ?string $navigationLabel = 'Kỳ lương';

    protected static ?string $modelLabel = 'Kỳ lương';

    protected static ?string $pluralModelLabel = 'Kỳ lương';

    public static function canCreate(): bool
    {
        return false; // tạo qua nút "Chốt lương tháng", không tạo tay
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('month')->label('Tháng'),
            TextEntry::make('status')->label('Trạng thái')->formatStateUsing(fn (string $state) => $state === 'closed' ? 'Đã chốt' : 'Đang mở'),
            TextEntry::make('employee_count')->label('Số nhân viên'),
            TextEntry::make('total_amount')->label('Tổng thực nhận')->money('VND'),
            TextEntry::make('closed_at')->label('Chốt lúc')->dateTime('d/m/Y H:i')->placeholder('—'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('month')->label('Tháng')->sortable()->searchable(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'closed' ? 'Đã chốt' : 'Đang mở')
                    ->color(fn (string $state) => $state === 'closed' ? 'success' : 'warning'),
                TextColumn::make('employee_count')->label('Nhân viên')->alignEnd(),
                TextColumn::make('total_amount')->label('Tổng thực nhận')->money('VND')->alignEnd(),
                TextColumn::make('closed_at')->label('Chốt lúc')->dateTime('d/m/Y H:i')->placeholder('—'),
                PartnerTableHelpers::column(),
            ])
            ->filters([PartnerTableHelpers::filter()])
            ->actions([
                ViewAction::make()->label('Xem phiếu lương'),
                Action::make('reopen')->label('Mở lại')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (PayrollPeriod $r) => $r->isClosed())
                    ->requiresConfirmation()
                    ->modalDescription('Xoá các phiếu lương của kỳ này để tính lại. Chỉ làm được khi chưa trả phiếu nào.')
                    ->action(function (PayrollPeriod $record): void {
                        $result = app(PayrollService::class)->reopen($record);
                        $result['ok']
                            ? Notification::make()->success()->title('Đã mở lại kỳ lương')->send()
                            : Notification::make()->warning()->title('Không thể mở lại: đã có phiếu lương được trả.')->send();
                    }),
            ])
            ->defaultSort('month', 'desc');
    }

    public static function getRelationManagers(): array
    {
        return [SlipsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollPeriods::route('/'),
            'view' => Pages\ViewPayrollPeriod::route('/{record}'),
        ];
    }
}
