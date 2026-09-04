<?php

declare(strict_types=1);

namespace Modules\Product\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Product\App\Filament\Resources\RoomServiceResource\Forms\RoomServiceForm;
use Modules\Product\App\Filament\Resources\RoomServiceResource\Pages;
use Modules\Product\App\Filament\Resources\RoomServiceResource\Tables\RoomServiceTable;
use Modules\Product\App\Models\RoomService;

class RoomServiceResource extends Resource
{
    protected static ?string $model = RoomService::class;

    // RoomService không có cột partner_id riêng (đã gắn trực tiếp product_id) — lọc qua
    // partner_id của Product cha (Product tự lọc theo BelongsToPartner rồi). CỘNG THÊM lọc theo
    // "Chuyển đổi chi nhánh" ở header qua Product::categories() — trước đây chỉ lọc partner, bỏ
    // qua switcher hoàn toàn.
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
        return 'heroicon-o-shopping-bag';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 6;
    }

    public static function getNavigationLabel(): string
    {
        return 'Dịch vụ Sân';
    }

    public static function getModelLabel(): string
    {
        return 'Dịch vụ';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Dịch vụ Sân';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    // Trước đây hardcode isSuperAdmin() — bỏ qua RoomServicePolicy (đã đúng, kiểm tra
    // view_any_room::service), khiến tick/bỏ tick quyền này ở Roles & Permissions vô tác dụng.
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_any_room::service') ?? false) && ($user?->partnerHasFeature('room_services') ?? false);
    }

    public static function form(Form $form): Form
    {
        return RoomServiceForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return RoomServiceTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoomService::route('/'),
            'create' => Pages\CreateRoomService::route('/create'),
            'edit'   => Pages\EditRoomService::route('/{record}/edit'),
        ];
    }
}
