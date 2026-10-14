<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\PartnerSettlementResource\Pages;
use App\Models\Partner;
use App\Models\PartnerSettlement;
use App\Services\PartnerSettlementService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Đối soát hoa hồng theo tháng giữa nền tảng và đối tác. Super admin lập bảng (doanh thu ròng × tỉ lệ hợp đồng), điều chỉnh và xác nhận đã thu;
// đối tác chỉ XEM bảng của chính mình. Nghiệp vụ ở App\Services\PartnerSettlementService.
class PartnerSettlementResource extends Resource
{
    use \App\Filament\Support\GatedByFeature;

    protected static string $featureFlag = 'settlement';

    protected static ?string $model = PartnerSettlement::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Đối soát hoa hồng';

    protected static ?string $modelLabel = 'Đối soát hoa hồng';

    protected static ?string $pluralModelLabel = 'Đối soát hoa hồng';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user && ! $user->isSuperAdmin() ? $query->where('partner_id', $user->partner_id) : $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('partner.name')->label('Đối tác'),
            TextEntry::make('month')->label('Tháng'),
            TextEntry::make('order_count')->label('Số đơn'),
            TextEntry::make('gross_revenue')->label('Doanh thu thực thu')->money('VND'),
            TextEntry::make('refunds')->label('Hoàn tiền cho khách')->money('VND'),
            TextEntry::make('net_revenue')->label('Doanh thu ròng')->money('VND'),
            TextEntry::make('commission_rate')->label('Tỉ lệ hoa hồng')->suffix('%'),
            TextEntry::make('commission_amount')->label('Hoa hồng')->money('VND'),
            TextEntry::make('adjustment_amount')->label('Điều chỉnh')->money('VND'),
            TextEntry::make('adjustment_note')->label('Ghi chú điều chỉnh')->placeholder('—'),
            TextEntry::make('amount_due')->label('Phải thu')->money('VND')->weight('bold'),
            TextEntry::make('status')->label('Trạng thái')->formatStateUsing(fn (string $state) => $state === 'paid' ? 'Đã thu' : 'Chờ thu'),
            TextEntry::make('paid_at')->label('Ngày thu')->dateTime('d/m/Y H:i')->placeholder('—'),
            TextEntry::make('payment_reference')->label('Mã tham chiếu')->placeholder('—'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        $isSuper = (bool) auth()->user()?->isSuperAdmin();
        $money = fn ($state) => number_format((float) $state, 0, ',', '.') . 'đ';

        return $table
            ->columns([
                TextColumn::make('month')->label('Tháng')->sortable()->searchable(),
                TextColumn::make('partner.name')->label('Đối tác')->searchable()->visible($isSuper),
                TextColumn::make('order_count')->label('Số đơn')->alignEnd(),
                TextColumn::make('net_revenue')->label('Doanh thu ròng')->formatStateUsing($money)->alignEnd(),
                TextColumn::make('commission_rate')->label('Tỉ lệ')->suffix('%')->alignEnd(),
                TextColumn::make('commission_amount')->label('Hoa hồng')->formatStateUsing($money)->alignEnd(),
                TextColumn::make('adjustment_amount')->label('Điều chỉnh')->formatStateUsing($money)->alignEnd(),
                TextColumn::make('amount_due')->label('Phải thu')->formatStateUsing($money)->weight('bold')->alignEnd(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'paid' ? 'Đã thu' : 'Chờ thu')
                    ->color(fn (string $state) => $state === 'paid' ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Trạng thái')->options(['pending' => 'Chờ thu', 'paid' => 'Đã thu']),
                SelectFilter::make('partner_id')->label('Đối tác')->options(fn () => Partner::query()->orderBy('name')->pluck('name', 'id'))->searchable()->visible($isSuper),
            ])
            ->actions([
                ViewAction::make()->label('Chi tiết'),
                Action::make('adjust')->label('Điều chỉnh')->icon('heroicon-o-adjustments-horizontal')->color('gray')
                    ->visible(fn (PartnerSettlement $r) => $isSuper && ! $r->isPaid())
                    ->form([
                        TextInput::make('adjustment_amount')->label('Số tiền điều chỉnh (+ tăng, − giảm)')->numeric()->required()->default(fn (PartnerSettlement $r) => $r->adjustment_amount),
                        Textarea::make('adjustment_note')->label('Lý do')->maxLength(500)->default(fn (PartnerSettlement $r) => $r->adjustment_note),
                    ])
                    ->action(function (array $data, PartnerSettlement $record): void {
                        $result = app(PartnerSettlementService::class)->adjust($record, (int) $data['adjustment_amount'], $data['adjustment_note'] ?? null);
                        $result['ok']
                            ? Notification::make()->success()->title('Đã điều chỉnh')->send()
                            : Notification::make()->warning()->title('Bảng đã thu, không điều chỉnh được.')->send();
                    }),
                Action::make('pay')->label('Đã thu')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (PartnerSettlement $r) => $isSuper && ! $r->isPaid())
                    ->form([
                        Select::make('method')->label('Hình thức')->options(['transfer' => 'Chuyển khoản', 'cash' => 'Tiền mặt'])->default('transfer')->required(),
                        TextInput::make('reference')->label('Mã giao dịch / ghi chú')->maxLength(100),
                    ])
                    ->action(function (array $data, PartnerSettlement $record): void {
                        $result = app(PartnerSettlementService::class)->markPaid($record, $data['method'], $data['reference'] ?? null);
                        $result['ok']
                            ? Notification::make()->success()->title('Đã ghi nhận thu hoa hồng')->send()
                            : Notification::make()->warning()->title('Bảng này đã được thu trước đó.')->send();
                    }),
            ])
            ->defaultSort('month', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPartnerSettlements::route('/'),
            'view' => Pages\ViewPartnerSettlement::route('/{record}'),
        ];
    }
}
