<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookingFundResource\Pages;

use App\Filament\Resources\BookingFundResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewBookingFund extends ViewRecord
{
    protected static string $resource = BookingFundResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('invite_code')->label('Mã mời'),
            TextEntry::make('title')->label('Tên quỹ'),
            TextEntry::make('note')->label('Ghi chú')->placeholder('—'),
            TextEntry::make('leader.fullname')->label('Trưởng nhóm'),
            TextEntry::make('leader.phone')->label('SĐT trưởng nhóm'),
            TextEntry::make('target_amount')->label('Mục tiêu')->money('VND'),
            TextEntry::make('collected')->label('Đã góp (đã xác nhận)')
                ->getStateUsing(fn ($record) => number_format($record->collectedAmount(), 0, ',', '.') . 'đ'),
            TextEntry::make('bank_account_holder')->label('Chủ tài khoản nhận tiền'),
            TextEntry::make('bank_account_number')->label('Số tài khoản'),
            TextEntry::make('status')->label('Trạng thái')
                ->formatStateUsing(fn (string $state) => $state === 'closed' ? 'Đã đóng' : 'Đang gom quỹ'),
        ])->columns(2);
    }
}
