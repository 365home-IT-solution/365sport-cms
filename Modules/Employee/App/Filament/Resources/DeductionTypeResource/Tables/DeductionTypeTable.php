<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\DeductionTypeResource\Tables;

use App\Filament\Support\PartnerTableHelpers;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeductionTypeTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên loại giảm trừ')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('calc_type')
                    ->label('Cách tính')
                    ->formatStateUsing(fn (string $state) => $state === 'percentage' ? 'Theo % lương' : 'Số tiền cố định')
                    ->badge(),
                TextColumn::make('default_amount')
                    ->label('Mặc định')
                    ->money('VND'),
                PartnerTableHelpers::column(),
                IconColumn::make('status')
                    ->label('Hoạt động')
                    ->boolean(),
            ])
            ->filters([
                PartnerTableHelpers::filter(),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
