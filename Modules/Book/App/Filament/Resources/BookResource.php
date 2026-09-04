<?php

declare(strict_types=1);

namespace Modules\Book\App\Filament\Resources;

use Modules\Book\App\Filament\Resources\BookResource\Forms\BookForm;
use Modules\Book\App\Filament\Resources\BookResource\Tables\BookTable;
use Modules\Book\App\Filament\Resources\BookResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Product\App\Models\RoomTimeSlot;

class BookResource extends Resource
{
    protected static ?string $model = RoomTimeSlot::class;
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationGroup = 'Quản lý';
    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Hệ thống giá';
    }

    public static function getModelLabel(): string
    {
        return 'Book';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Book';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_any_book') ?? false) && ($user?->partnerHasFeature('bookings') ?? false);
    }

    // RoomTimeSlot KHÔNG có branch_id/category_id trực tiếp — gắn chi nhánh GIÁN TIẾP qua
    // room_id -> Product::categories() (morphToMany categorizables). Trước đây KHÔNG override
    // getEloquentQuery() nào cả, lộ toàn bộ khung giờ/giá của MỌI đối tác (Product tự lọc theo
    // BelongsToActiveBranchCategories nhưng RoomTimeSlot không kế thừa global scope của Product).
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

    public static function form(Form $form): Form
    {
        return BookForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return BookTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\SettingBook::route('/'),
        ];
    }
}
