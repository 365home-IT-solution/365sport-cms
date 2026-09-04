<?php

declare(strict_types=1);

namespace Modules\Product\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Product\App\Filament\Resources\RoomSpecialResource\Forms\RoomSpecialForm;
use Modules\Product\App\Filament\Resources\RoomSpecialResource\Pages;
use Modules\Product\App\Filament\Resources\RoomSpecialResource\Tables\RoomSpecialTable;
use Modules\Product\App\Models\RoomSpecial;

class RoomSpecialResource extends Resource
{
    protected static ?string $model = RoomSpecial::class;

    // RoomSpecial không có cột partner_id riêng — lọc qua partner_id của Product cha. CỘNG THÊM
    // lọc theo "Chuyển đổi chi nhánh" ở header qua Product::categories() — trước đây chỉ lọc
    // partner, bỏ qua switcher hoàn toàn.
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if (! $user) {
            return $query;
        }

        if (! $user->isSuperAdmin()) {
            $query->whereHas('product', fn (Builder $q) => $q->where('partner_id', $user->partner_id));
        }

        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            return $user->isSuperAdmin() ? $query : $query->whereRaw('1 = 0');
        }

        $categoryIds = $user->visibleProductCategoryIds($branchIds);

        if (empty($categoryIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('product.categories', fn (Builder $q) => $q->whereIn('categories.id', $categoryIds));
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-star';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    public static function getNavigationLabel(): string
    {
        return 'Điểm Đặc Biệt';
    }

    public static function getModelLabel(): string
    {
        return 'Điểm đặc biệt';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Điểm Đặc Biệt';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    // Trước đây hardcode isSuperAdmin() — bỏ qua RoomSpecialPolicy (đã đúng, kiểm tra
    // view_any_room::special), khiến tick/bỏ tick quyền này ở Roles & Permissions vô tác dụng.
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_any_room::special') ?? false) && ($user?->partnerHasFeature('room_specials') ?? false);
    }

    public static function form(Form $form): Form
    {
        return RoomSpecialForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return RoomSpecialTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoomSpecial::route('/'),
            'create' => Pages\CreateRoomSpecial::route('/create'),
            'edit'   => Pages\EditRoomSpecial::route('/{record}/edit'),
        ];
    }
}
