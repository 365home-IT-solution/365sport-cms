<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OpenMatchResource\Pages;
use App\Models\OpenMatch;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// "Tìm bạn chơi" (App\Models\OpenMatch) — kèo do KHÁCH TỰ TẠO/quản lý qua API
// (App\Http\Controllers\Api\OpenMatchController), KHÔNG qua admin. Resource này CHỈ để admin có
// TẦM NHÌN (ai đang mở kèo, đủ người chưa) khi cần tra soát/hỗ trợ khách — không có form tạo/sửa,
// chỉ 1 action "Huỷ kèo" cho trường hợp cần can thiệp (vd khách khiếu nại/spam). Cùng triết lý với
// BookingFundResource (canCreate/canEdit đều false — quỹ nhóm cũng do khách tự quản lý).
class OpenMatchResource extends Resource
{
    use \App\Filament\Support\GatedByFeature;

    protected static string $featureFlag = 'open_match';

    protected static ?string $model = OpenMatch::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user-plus';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Tìm bạn chơi';
    protected static ?string $modelLabel       = 'Kèo ghép bạn chơi';
    protected static ?string $pluralModelLabel = 'Tìm bạn chơi';
    protected static ?int    $navigationSort   = 9;

    // Lọc theo chi nhánh đang chọn ở header (nút "Chuyển đổi chi nhánh").
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $branchIds = auth()->user()?->effectiveBranchIds() ?? [];

        return $branchIds ? $query->whereIn('open_matches.category_id', $branchIds) : $query;
    }

    public static function canViewAny(): bool
    {
        return static::featureEnabled() && (auth()->user()?->isSuperAdmin() ?? false);
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
                TextColumn::make('host.fullname')
                    ->label('Host')
                    ->searchable(),

                TextColumn::make('host.phone')
                    ->label('SĐT host')
                    ->searchable(),

                TextColumn::make('product.name')
                    ->label('Sân')
                    ->placeholder('—'),

                TextColumn::make('category.name')
                    ->label('Chi nhánh')
                    ->placeholder('—'),

                TextColumn::make('play_date')
                    ->label('Ngày chơi')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('time_range')
                    ->label('Khung giờ')
                    ->getStateUsing(function (OpenMatch $record): string {
                        if (! $record->start_time) {
                            return '—';
                        }

                        return substr($record->start_time, 0, 5) . '-' . substr((string) $record->end_time, 0, 5);
                    }),

                TextColumn::make('players_joined')
                    ->label('Đã ghép')
                    ->getStateUsing(fn (OpenMatch $record) => "{$record->players_joined}/{$record->players_needed}")
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        OpenMatch::STATUS_OPEN      => 'Đang mở',
                        OpenMatch::STATUS_FULL      => 'Đã đủ người',
                        OpenMatch::STATUS_CANCELLED => 'Đã huỷ',
                        OpenMatch::STATUS_EXPIRED   => 'Hết hạn',
                        default                     => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        OpenMatch::STATUS_OPEN      => 'success',
                        OpenMatch::STATUS_FULL      => 'info',
                        OpenMatch::STATUS_CANCELLED => 'danger',
                        OpenMatch::STATUS_EXPIRED   => 'gray',
                        default                     => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        OpenMatch::STATUS_OPEN      => 'Đang mở',
                        OpenMatch::STATUS_FULL      => 'Đã đủ người',
                        OpenMatch::STATUS_CANCELLED => 'Đã huỷ',
                        OpenMatch::STATUS_EXPIRED   => 'Hết hạn',
                    ]),
            ])
            ->actions([
                ViewAction::make()->label('Chi tiết'),
                Action::make('cancel')
                    ->label('Huỷ kèo')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (OpenMatch $record) => in_array($record->status, [OpenMatch::STATUS_OPEN, OpenMatch::STATUS_FULL], true))
                    ->action(function (OpenMatch $record): void {
                        $record->update(['status' => OpenMatch::STATUS_CANCELLED]);

                        Notification::make()
                            ->title('Đã huỷ kèo')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpenMatches::route('/'),
            'view'  => Pages\ViewOpenMatch::route('/{record}'),
        ];
    }
}
