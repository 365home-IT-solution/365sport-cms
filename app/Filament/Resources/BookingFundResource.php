<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BookingFundResource\Pages;
use App\Filament\Resources\BookingFundResource\RelationManagers\ContributionsRelationManager;
use App\Filament\Resources\BookingFundResource\RelationManagers\MembersRelationManager;
use App\Models\BookingFund;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Quỹ nhóm góp tiền đặt sân do KHÁCH TỰ TẠO (App\Models\BookingFund) — tiền không đi qua hệ
// thống (chuyển thẳng vào STK cá nhân trưởng nhóm), nên KHÔNG thuộc riêng đối tác nào (KHÔNG dùng
// BelongsToPartner). Resource này CHỈ để super_admin tra soát khi có khiếu nại (ai đã góp bao
// nhiêu, đã duyệt chưa) — thuần XEM, không có action sửa/xoá số tiền hay trạng thái.
class BookingFundResource extends Resource
{
    protected static ?string $model = BookingFund::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user-group';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Quỹ nhóm góp tiền';
    protected static ?string $modelLabel       = 'Quỹ nhóm';
    protected static ?string $pluralModelLabel = 'Quỹ nhóm góp tiền';

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

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
        return $table
            ->columns([
                TextColumn::make('invite_code')
                    ->label('Mã mời')
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('title')
                    ->label('Tên quỹ')
                    ->searchable(),

                TextColumn::make('leader.fullname')
                    ->label('Trưởng nhóm')
                    ->searchable(),

                TextColumn::make('leader.phone')
                    ->label('SĐT trưởng nhóm')
                    ->searchable(),

                TextColumn::make('target_amount')
                    ->label('Mục tiêu')
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('collected')
                    ->label('Đã góp')
                    ->getStateUsing(fn (BookingFund $record) => number_format($record->collectedAmount(), 0, ',', '.') . 'đ'),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'closed' ? 'Đã đóng' : 'Đang gom quỹ')
                    ->color(fn (string $state) => $state === 'closed' ? 'gray' : 'success'),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make()->label('Chi tiết'),
            ])
            ->bulkActions([]);
    }

    public static function getRelationManagers(): array
    {
        return [
            MembersRelationManager::class,
            ContributionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookingFunds::route('/'),
            'view'  => Pages\ViewBookingFund::route('/{record}'),
        ];
    }
}
