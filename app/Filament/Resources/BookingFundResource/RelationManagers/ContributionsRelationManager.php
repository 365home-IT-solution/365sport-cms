<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookingFundResource\RelationManagers;

use App\Models\BookingFundContribution;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ContributionsRelationManager extends RelationManager
{
    protected static string $relationship = 'contributions';

    protected static ?string $title = 'Các khoản góp';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.fullname')->label('Người góp'),
                TextColumn::make('amount')->label('Số tiền')->money('VND'),
                TextColumn::make('receipt_photo')
                    ->label('Ảnh biên lai')
                    ->formatStateUsing(fn () => 'Xem ảnh')
                    ->url(fn (BookingFundContribution $record) => Storage::disk('public')->url($record->receipt_photo))
                    ->openUrlInNewTab(),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'confirmed' => 'Đã xác nhận',
                        'rejected'  => 'Đã từ chối',
                        default     => 'Chờ xác nhận',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success',
                        'rejected'  => 'danger',
                        default     => 'warning',
                    }),
                TextColumn::make('created_at')->label('Ngày gửi')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
