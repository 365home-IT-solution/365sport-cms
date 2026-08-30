<?php

declare(strict_types=1);

namespace Modules\Product\App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;
use Modules\Product\App\Filament\Resources\ProductResource\Forms\ProductForm;
use Modules\Product\App\Filament\Resources\ProductResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Product\App\Filament\Resources\ProductResource\Tables\ProductTable;
use Modules\Product\App\Models\Product;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    // Sân bị khoá theo gói dịch vụ đối tác (Partner::hasFeature('products')) — chỉ THÊM điều
    // kiện, vẫn giữ nguyên check quyền Shield gốc qua parent::canViewAny() (policy tự sinh).
    public static function canViewAny(): bool
    {
        return parent::canViewAny() && (auth()->user()?->partnerHasFeature('products') ?? false);
    }

    public static function getNavigationIcon(): string
    {
        return __('product::product.resource.navigation_icon');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('product::product.resource.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('product::product.resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('product::product.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('product::product.resource.plural_model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }

        $allCategoryIds = $user->allowedCategoryIds();

        // Không còn chặn cứng khi chưa gán quyền chi nhánh cụ thể — Product đã tự lọc theo
        // partner_id qua BelongsToPartner (chỉ thấy sân của đối tác mình). allowedCategoryIds
        // chỉ dùng để thu hẹp thêm (vd giới hạn 1 nhân viên chỉ thấy 1 vài chi nhánh).
        if (empty($allCategoryIds)) {
            return $query;
        }

        // Lọc sân thuộc các chi nhánh/khu vực được phép (qua bảng categorizables)
        return $query->whereHas('categories', function (Builder $q) use ($allCategoryIds) {
            $q->whereIn('categories.id', $allCategoryIds);
        });
    }

    public static function form(Form $form): Form
    {
        return ProductForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return ProductTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProduct::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
