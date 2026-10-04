<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Filament\Resources\TeamResource\RelationManagers\MembersRelationManager;
use App\Models\Team;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

// Nhóm (đội) do KHÁCH TỰ TẠO (App\Models\Team) — không thuộc riêng đối tác nào (không dùng BelongsToPartner), nên
// giống BookingFundResource: CHỈ super_admin xem để tra soát khi có khiếu nại (ai là trưởng nhóm, ai trong nhóm,
// quỹ nào gắn với nhóm). Thuần XEM — khách tự quản lý nhóm trong app.
class TeamResource extends Resource
{
    protected static ?string $model = Team::class;

    protected static ?string $navigationIcon   = 'heroicon-o-users';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Đội nhóm';
    protected static ?string $modelLabel       = 'Nhóm';
    protected static ?string $pluralModelLabel = 'Đội nhóm';
    protected static ?int    $navigationSort   = 7;

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
                TextColumn::make('code')->label('Mã nhóm')->searchable()->fontFamily('mono'),
                TextColumn::make('name')->label('Tên nhóm')->searchable(),
                TextColumn::make('leader.fullname')->label('Trưởng nhóm')->searchable(),
                TextColumn::make('leader.phone')->label('SĐT trưởng nhóm')->searchable(),
                TextColumn::make('members_count')
                    ->label('Thành viên')
                    ->getStateUsing(fn (Team $record) => $record->memberCount()),
                TextColumn::make('funds_count')
                    ->label('Quỹ')
                    ->counts('funds'),
                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === Team::STATUS_DISBANDED ? 'Đã giải tán' : 'Đang hoạt động')
                    ->color(fn (string $state) => $state === Team::STATUS_DISBANDED ? 'gray' : 'success'),
                TextColumn::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i')->sortable(),
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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'view'  => Pages\ViewTeam::route('/{record}'),
        ];
    }
}
