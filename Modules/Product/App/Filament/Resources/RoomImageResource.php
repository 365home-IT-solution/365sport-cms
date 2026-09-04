<?php

declare(strict_types=1);

namespace Modules\Product\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Product\App\Filament\Resources\RoomImageResource\Forms\RoomImageForm;
use Modules\Product\App\Filament\Resources\RoomImageResource\Pages;
use Modules\Product\App\Filament\Resources\RoomImageResource\Tables\RoomImageTable;
use Modules\Product\App\Models\RoomImage;

class RoomImageResource extends Resource
{
    protected static ?string $model = RoomImage::class;

    // Trước đây isSuperAdmin() hardcode ở cả 5 hàm — bỏ qua RoomImagePolicy (đã đúng, kiểm tra
    // *_room::image), khiến tick/bỏ tick quyền này ở Roles & Permissions vô tác dụng.
    public static function canViewAny(): bool { return auth()->user()?->can('view_any_room::image') ?? false; }
    public static function canCreate(): bool { return auth()->user()?->can('create_room::image') ?? false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->can('update_room::image') ?? false; }
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool { return auth()->user()?->can('delete_room::image') ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->can('delete_any_room::image') ?? false; }

    // RoomImage KHÔNG có partner_id/branch_id riêng — gắn chi nhánh GIÁN TIẾP qua room_id ->
    // Product::categories(). TRƯỚC ĐÂY không có getEloquentQuery() nào, lộ ảnh sân của MỌI đối
    // tác cho bất kỳ ai có permission Shield chung (view_any_room::image không tách theo đối tác).
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if (! $user) {
            return $query;
        }

        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            return $user->isSuperAdmin() ? $query : $query->whereRaw('1 = 0');
        }

        $categoryIds = $user->visibleProductCategoryIds($branchIds);

        if (empty($categoryIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('room.categories', fn (Builder $q) => $q->whereIn('categories.id', $categoryIds));
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-photo';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function getNavigationLabel(): string
    {
        return 'Ảnh Sân';
    }

    public static function getModelLabel(): string
    {
        return 'Ảnh sân';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Ảnh Sân';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return RoomImageForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return RoomImageTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoomImage::route('/'),
            'create' => Pages\CreateRoomImage::route('/create'),
            'edit'   => Pages\EditRoomImage::route('/{record}/edit'),
        ];
    }
}
