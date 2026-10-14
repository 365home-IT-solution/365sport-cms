<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\LoyaltyTransactionResource\Pages;
use App\Models\LoyaltyTransaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

// Sổ cái điểm thưởng của khách (chỉ xem + điều chỉnh tay từ nút ở đầu trang). Khách là dữ liệu toàn hệ thống nên chỉ quản trị cấp cao có quyền.
class LoyaltyTransactionResource extends Resource
{
    protected static ?string $model = LoyaltyTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Điểm thưởng';

    protected static ?string $modelLabel = 'Giao dịch điểm';

    protected static ?string $pluralModelLabel = 'Điểm thưởng';

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

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (\Illuminate\Database\Eloquent\Builder $query) => $query->with('customer:id,fullname,phone'))
            ->columns([
                TextColumn::make('created_at')->label('Thời gian')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('customer.fullname')->label('Khách')->searchable(),
                TextColumn::make('customer.phone')->label('SĐT')->searchable(),
                TextColumn::make('type')->label('Loại')->badge()->formatStateUsing(fn ($state) => ['earn' => 'Tích điểm', 'revoke' => 'Thu hồi', 'redeem' => 'Đổi mã', 'adjust' => 'Điều chỉnh'][$state] ?? $state)
                    ->color(fn ($state) => ['earn' => 'success', 'revoke' => 'danger', 'redeem' => 'warning', 'adjust' => 'info'][$state] ?? 'gray'),
                TextColumn::make('points')->label('Điểm')->numeric()->color(fn ($state) => $state < 0 ? 'danger' : 'success'),
                TextColumn::make('balance_after')->label('Số dư sau')->numeric(),
                TextColumn::make('coupon_code')->label('Mã giảm giá')->placeholder('—'),
                TextColumn::make('note')->label('Ghi chú')->limit(50),
            ])
            ->filters([SelectFilter::make('type')->label('Loại')->options(['earn' => 'Tích điểm', 'revoke' => 'Thu hồi', 'redeem' => 'Đổi mã', 'adjust' => 'Điều chỉnh'])]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListLoyaltyTransactions::route('/')];
    }
}
