<?php

declare(strict_types=1);

namespace App\Filament\Resources\TeamResource\Pages;

use App\Filament\Resources\TeamResource;
use App\Models\Team;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewTeam extends ViewRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            TextEntry::make('code')->label('Mã nhóm'),
            TextEntry::make('name')->label('Tên nhóm'),
            TextEntry::make('description')->label('Mô tả')->placeholder('—'),
            TextEntry::make('leader.fullname')->label('Trưởng nhóm'),
            TextEntry::make('leader.phone')->label('SĐT trưởng nhóm'),
            TextEntry::make('status')->label('Trạng thái')
                ->formatStateUsing(fn (string $state) => $state === Team::STATUS_DISBANDED ? 'Đã giải tán' : 'Đang hoạt động'),
            TextEntry::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i'),
            TextEntry::make('disbanded_at')->label('Ngày giải tán')->dateTime('d/m/Y H:i')->placeholder('—'),
        ])->columns(2);
    }
}
