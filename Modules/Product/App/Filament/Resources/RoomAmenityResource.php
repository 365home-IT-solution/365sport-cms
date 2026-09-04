<?php

declare(strict_types=1);

namespace Modules\Product\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Product\App\Filament\Resources\RoomAmenityResource\Forms\RoomAmenityForm;
use Modules\Product\App\Filament\Resources\RoomAmenityResource\Pages;
use Modules\Product\App\Filament\Resources\RoomAmenityResource\Tables\RoomAmenityTable;
use Modules\Product\App\Models\RoomAmenity;

// Tiện ích được lọc theo partner_id qua BelongsToPartner (global scope trên RoomAmenity model) —
// KHÔNG cần override cho phần đó. Nhưng RoomAmenity dùng CHUNG cho nhiều sân qua many-to-many
// (products()) nên vẫn cần CỘNG THÊM lọc theo "Chuyển đổi chi nhánh" ở header — 1 tiện ích của
// đối tác có thể gắn cho sân ở NHIỀU chi nhánh khác nhau của cùng đối tác đó.
class RoomAmenityResource extends Resource
{
    protected static ?string $model = RoomAmenity::class;

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

        // Tiện ích không gắn TRỰC TIẾP cho sân nào cũng vẫn hiển thị (danh mục tiện ích dùng
        // chung, có thể chưa gán sân) — chỉ loại các tiện ích ĐÃ gắn sân nhưng KHÔNG có sân nào
        // thuộc chi nhánh đang chọn. QUAN TRỌNG: doesntHave('products') PHẢI bỏ qua global scope
        // của Product (BelongsToActiveBranchCategories) qua withoutGlobalScopes() trong callback —
        // nếu không, "not exists" cũng tự động bị lọc theo cùng $categoryIds, khiến 1 tiện ích ĐÃ
        // gắn sân nhưng sân đó KHÔNG thuộc chi nhánh đang chọn bị hiểu nhầm thành "chưa gắn sân
        // nào" (vì dưới góc nhìn đã lọc, không tìm thấy sân nào) rồi vẫn lọt qua nhánh doesntHave —
        // bug thật đã phát hiện khi test qua tinker (baseline 6, chọn switcher vẫn ra 6).
        return $query->where(function (Builder $q) use ($categoryIds) {
            $q->doesntHave('products', callback: fn (Builder $q3) => $q3->withoutGlobalScopes())
                ->orWhereHas('products.categories', fn (Builder $q2) => $q2->whereIn('categories.id', $categoryIds));
        });
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-sparkles';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationLabel(): string
    {
        return 'Tiện ích Sân';
    }

    public static function getModelLabel(): string
    {
        return 'Tiện ích';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tiện ích Sân';
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    // Trước đây hardcode isSuperAdmin() — bỏ qua RoomAmenityPolicy (đã đúng, kiểm tra
    // view_any_room::amenity), khiến tick/bỏ tick quyền này ở Roles & Permissions vô tác dụng.
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_any_room::amenity') ?? false) && ($user?->partnerHasFeature('room_amenities') ?? false);
    }

    public static function form(Form $form): Form
    {
        return RoomAmenityForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return RoomAmenityTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoomAmenity::route('/'),
            'create' => Pages\CreateRoomAmenity::route('/create'),
            'edit'   => Pages\EditRoomAmenity::route('/{record}/edit'),
        ];
    }
}
