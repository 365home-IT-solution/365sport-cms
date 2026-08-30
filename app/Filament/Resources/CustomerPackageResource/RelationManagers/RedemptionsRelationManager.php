<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPackageResource\RelationManagers;

use App\Models\PackageRedemption;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Lịch sử từng lượt trừ buổi (App\Models\PackageRedemption) — chỉ để XEM, không sửa/xoá ở đây
// (hoàn buổi khi huỷ đơn đã có sẵn App\Models\CustomerPackage::refundRedemptionsForOrder(), không
// làm tay qua UI để tránh lệch sessions_remaining).
class RedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptions';

    protected static ?string $title = 'Lịch sử dùng gói';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('redeemed_at')
                    ->label('Thời gian dùng')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('orderItem.name')
                    ->label('Sân / khung giờ')
                    ->placeholder('—'),

                TextColumn::make('order.order_code')
                    ->label('Mã đơn')
                    ->placeholder('—'),

                IconColumn::make('refunded_at')
                    ->label('Đã hoàn')
                    ->boolean()
                    ->getStateUsing(fn (PackageRedemption $record) => ! is_null($record->refunded_at)),
            ])
            ->defaultSort('redeemed_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
