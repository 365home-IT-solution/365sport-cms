<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Warehouse\App\Filament\Resources\WarehouseItemRentalResource\Pages;
use Modules\Warehouse\App\Models\WarehouseItemRental;
use Modules\Warehouse\App\Services\WarehouseRentalService;

// Chỉ XEM + xử lý (trả/báo mất) — rental được tạo TỰ ĐỘNG khi khách chọn dịch vụ cho thuê dụng cụ
// lúc đặt sân (xem WarehouseRentalService::rent(), gọi từ OrderService::created()), không có
// action "Tạo mới" thủ công ở đây.
class WarehouseItemRentalResource extends Resource
{
    protected static ?string $model = WarehouseItemRental::class;

    protected static ?int $navigationSort = 11;

    public static function canViewAny(): bool
    {
        return parent::canViewAny() && (auth()->user()?->partnerHasFeature('warehouse') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    // WarehouseItemRental không có cột partner_id/branch_id riêng — mượn theo scope của chính vật
    // tư liên quan (WarehouseItem đã tự lọc qua BelongsToPartner/BelongsToBranch), đảm bảo mỗi đối
    // tác/chi nhánh chỉ thấy đúng lượt thuê dụng cụ của mình.
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('warehouseItem');
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-arrow-path-rounded-square';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationLabel(): string
    {
        return 'Thuê dụng cụ';
    }

    public static function getModelLabel(): string
    {
        return 'Lượt thuê dụng cụ';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Thuê dụng cụ';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('status', WarehouseItemRental::STATUS_RENTED)->count();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('warehouseItem.name')
                    ->label('Dụng cụ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('orderService.order.order_code')
                    ->label('Mã đơn')
                    ->searchable(),

                TextColumn::make('orderService.order.buyer_name')
                    ->label('Khách')
                    ->searchable(),

                TextColumn::make('quantity')
                    ->label('Số lượng')
                    ->numeric(maxDecimalPlaces: 2),

                TextColumn::make('rented_at')
                    ->label('Mượn lúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('returned_at')
                    ->label('Trả lúc')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        WarehouseItemRental::STATUS_RENTED => 'Đang mượn',
                        WarehouseItemRental::STATUS_RETURNED => 'Đã trả',
                        WarehouseItemRental::STATUS_LOST => 'Báo mất',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        WarehouseItemRental::STATUS_RENTED => 'warning',
                        WarehouseItemRental::STATUS_RETURNED => 'success',
                        WarehouseItemRental::STATUS_LOST => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        WarehouseItemRental::STATUS_RENTED => 'Đang mượn',
                        WarehouseItemRental::STATUS_RETURNED => 'Đã trả',
                        WarehouseItemRental::STATUS_LOST => 'Báo mất',
                    ]),
            ])
            ->defaultSort('rented_at', 'desc')
            ->actions([
                Action::make('markReturned')
                    ->label('Đánh dấu đã trả')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (WarehouseItemRental $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->action(fn (WarehouseItemRental $record) => app(WarehouseRentalService::class)
                        ->releaseForOrderService($record->orderService, WarehouseItemRental::STATUS_RETURNED)),

                Action::make('markLost')
                    ->label('Báo mất')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn (WarehouseItemRental $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalDescription('Dụng cụ sẽ được đánh dấu là mất — số lượng "Đang sử dụng" sẽ KHÔNG tự trả lại, cần vào trang Vật tư để trừ thủ công nếu không thu hồi được.')
                    ->action(fn (WarehouseItemRental $record) => app(WarehouseRentalService::class)
                        ->releaseForOrderService($record->orderService, WarehouseItemRental::STATUS_LOST)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouseItemRentals::route('/'),
        ];
    }
}
