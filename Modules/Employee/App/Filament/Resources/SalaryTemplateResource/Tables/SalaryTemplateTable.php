<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Tables;

use App\Filament\Support\PartnerTableHelpers;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalaryTemplateTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên mẫu lương')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('salaryType.name')
                    ->label('Loại lương'),
                TextColumn::make('base_amount')
                    ->label('Lương cơ bản')
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
