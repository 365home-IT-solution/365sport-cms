<?php

declare(strict_types=1);

namespace App\Filament\Resources\TeamResource\RelationManagers;

use App\Models\TeamMember;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Thành viên & yêu cầu';

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
                        TeamMember::STATUS_APPROVED => 'Thành viên',
                        TeamMember::STATUS_INVITED  => 'Đã mời, chờ đồng ý',
                        TeamMember::STATUS_REJECTED => 'Bị từ chối',
                        TeamMember::STATUS_DECLINED => 'Từ chối lời mời',
                        default                     => 'Chờ trưởng nhóm duyệt',
                    })
                    ->color(fn (string $state) => match ($state) {
                        TeamMember::STATUS_APPROVED => 'success',
                        TeamMember::STATUS_REJECTED, TeamMember::STATUS_DECLINED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('inviter.fullname')->label('Người mời')->placeholder('—'),
                TextColumn::make('requested_at')->label('Ngày yêu cầu/mời')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
