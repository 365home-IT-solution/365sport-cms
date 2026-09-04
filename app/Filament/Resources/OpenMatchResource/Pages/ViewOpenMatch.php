<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenMatchResource\Pages;

use App\Filament\Resources\OpenMatchResource;
use App\Models\OpenMatch;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOpenMatch extends ViewRecord
{
    protected static string $resource = OpenMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('host.fullname')->label('Host'),
            TextEntry::make('host.phone')->label('SĐT host'),
            TextEntry::make('host.skill_level')->label('Trình độ host')->placeholder('—'),
            TextEntry::make('product.name')->label('Sân')->placeholder('—'),
            TextEntry::make('category.name')->label('Chi nhánh')->placeholder('—'),
            TextEntry::make('play_date')->label('Ngày chơi')->date('d/m/Y'),
            TextEntry::make('start_time')->label('Giờ bắt đầu')->placeholder('—'),
            TextEntry::make('end_time')->label('Giờ kết thúc')->placeholder('—'),
            TextEntry::make('players_needed')->label('Cần thêm'),
            TextEntry::make('players_joined')->label('Đã ghép'),
            TextEntry::make('min_skill_level')->label('Trình độ tối thiểu')->placeholder('—'),
            TextEntry::make('max_skill_level')->label('Trình độ tối đa')->placeholder('—'),
            TextEntry::make('price_per_player')->label('Giá dự kiến/người')->money('VND')->placeholder('—'),
            TextEntry::make('note')->label('Ghi chú')->placeholder('—'),
            TextEntry::make('status')->label('Trạng thái')
                ->formatStateUsing(fn (string $state) => match ($state) {
                    OpenMatch::STATUS_OPEN      => 'Đang mở',
                    OpenMatch::STATUS_FULL      => 'Đã đủ người',
                    OpenMatch::STATUS_CANCELLED => 'Đã huỷ',
                    OpenMatch::STATUS_EXPIRED   => 'Hết hạn',
                    default                     => $state,
                }),
            TextEntry::make('booking_fund_id')->label('Quỹ ghép tiền')->placeholder('— (chưa tạo)'),

            RepeatableEntry::make('activeParticipants')
                ->label('Người đã ghép')
                ->schema([
                    TextEntry::make('customer.fullname')->label('Tên'),
                    TextEntry::make('customer.skill_level')->label('Trình độ')->placeholder('—'),
                    TextEntry::make('created_at')->label('Tham gia lúc')->dateTime('d/m/Y H:i'),
                ])
                ->columns(3),
        ])->columns(2);
    }
}
