<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RoomRatingResource\Pages;
use App\Models\RoomRating;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Product\App\Models\Product;

// Kiểm duyệt đánh giá sân: ẩn/hiện đánh giá vi phạm (không tính vào điểm trung bình) và trả lời công khai. Đối tác chỉ thấy đánh giá sân của mình.
class RoomRatingResource extends Resource
{
    protected static ?string $model = RoomRating::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Đánh giá sân';

    protected static ?string $modelLabel = 'Đánh giá';

    protected static ?string $pluralModelLabel = 'Đánh giá sân';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user && ! $user->isSuperAdmin()
            ? $query->whereIn('room_id', Product::query()->withoutGlobalScopes()->where('partner_id', $user->partner_id)->select('id'))
            : $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer:id,fullname,phone', 'room:id,name']))
            ->columns([
                TextColumn::make('room.name')->label('Sân')->searchable(),
                TextColumn::make('customer.fullname')->label('Khách')->searchable(),
                TextColumn::make('star')->label('Sao')->badge()->color(fn ($state) => $state >= 4 ? 'success' : ($state >= 3 ? 'warning' : 'danger'))->sortable(),
                TextColumn::make('comment')->label('Nội dung')->limit(60)->wrap()->searchable(),
                TextColumn::make('reply')->label('Phản hồi')->limit(40)->placeholder('Chưa trả lời'),
                IconColumn::make('is_hidden')->label('Đang ẩn')->boolean(),
                TextColumn::make('created_at')->label('Ngày')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_hidden')->label('Ẩn'),
                TernaryFilter::make('replied')->label('Đã trả lời')->queries(
                    true: fn (Builder $query) => $query->whereNotNull('reply'),
                    false: fn (Builder $query) => $query->whereNull('reply'),
                ),
            ])
            ->actions([
                Action::make('reply')->label('Trả lời')->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->visible(fn () => (bool) auth()->user()?->can('update_room::rating'))
                    ->form([Textarea::make('reply')->label('Nội dung trả lời công khai')->required()->maxLength(1000)])
                    ->fillForm(fn (RoomRating $record) => ['reply' => $record->reply])
                    ->action(function (RoomRating $record, array $data): void {
                        $record->update(['reply' => trim($data['reply']), 'replied_at' => now(), 'replied_by' => (string) auth()->id()]);
                        Notification::make()->success()->title('Đã lưu phản hồi')->send();
                    }),
                Action::make('toggle')->label(fn (RoomRating $record) => $record->is_hidden ? 'Hiện lại' : 'Ẩn')
                    ->icon(fn (RoomRating $record) => $record->is_hidden ? 'heroicon-o-eye' : 'heroicon-o-eye-slash')
                    ->color(fn (RoomRating $record) => $record->is_hidden ? 'success' : 'danger')
                    ->visible(fn () => (bool) auth()->user()?->can('update_room::rating'))
                    ->requiresConfirmation()
                    ->action(function (RoomRating $record): void {
                        $record->update(['is_hidden' => ! $record->is_hidden]);
                        RoomRating::recalcRoomScore((string) $record->room_id);
                        Notification::make()->success()->title($record->is_hidden ? 'Đã ẩn đánh giá' : 'Đã hiện lại đánh giá')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRoomRatings::route('/')];
    }
}
