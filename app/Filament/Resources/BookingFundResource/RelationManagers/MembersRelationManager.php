<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookingFundResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Thành viên';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.fullname')->label('Tên'),
                TextColumn::make('customer.phone')->label('SĐT'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'approved' => 'Đã duyệt',
                        'rejected' => 'Đã từ chối',
                        default    => 'Chờ duyệt',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default    => 'warning',
                    }),
                TextColumn::make('requested_at')->label('Ngày xin tham gia')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
