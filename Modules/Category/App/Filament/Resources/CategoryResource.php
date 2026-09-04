<?php

declare(strict_types=1);

namespace Modules\Category\App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;
use Modules\Category\App\Filament\Resources\CategoryResource\Forms\CategoryForm;
use Modules\Category\App\Filament\Resources\CategoryResource\RelationManagers\ProductsRelationManager;
use Modules\Category\App\Filament\Resources\CategoryResource\Tables\CategoryTable;
use Modules\Category\App\Filament\Resources\CategoryResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Guava\FilamentKnowledgeBase\Contracts\HasKnowledgeBase;
use Modules\Category\Entities\Category;

class CategoryResource extends Resource implements HasKnowledgeBase
{
    public static function getDocumentation(): array
    {
        return [
            'category.getting-started'
        ];
    }

    protected static ?string $model = Category::class;

    // Resource này dùng chung cho CẢ 2 loại category_type (Chi nhánh 'product' LẪN danh mục Bài
    // viết 'post', xem getEloquentQuery() bên dưới) — khoá theo tính năng 'branches' sẽ ẩn CẢ HAI
    // cùng lúc (hạn chế đã có sẵn từ trước, không phải do gate này tạo ra: PartnerRolePermissions
    // Seeder cũng chỉ cấp 1 permission 'category' chung cho cả 2 loại).
    public static function canViewAny(): bool
    {
        return parent::canViewAny() && (auth()->user()?->partnerHasFeature('branches') ?? false);
    }

    public static function getNavigationIcon(): string
    {
        return __('category::category.resource.navigation_icon');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return __('category::category.resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('category::category.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('category::category.resource.plural_model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    // Resource này chỉ còn quản lý chi nhánh/khu vực (category_type=product) — danh mục bài viết
    // (category_type=post) đã tách sang PostCategoryResource riêng, không còn lẫn ở đây nữa.
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->where('category_type', 'product');
        $user  = auth()->user();

        if (! $user) {
            return $query;
        }

        // Luôn tôn trọng "Chuyển đổi chi nhánh" ở header (App\Livewire\BranchSwitcher), KỂ CẢ
        // super_admin — cùng tinh thần OrderResource::getEloquentQuery(). Trước đây dùng
        // allowedCategoryIds() (toàn bộ quyền được CẤP, không quan tâm switcher) qua 1 method
        // riêng trùng lặp logic User::rootProductCategoryIds(); đổi sang effectiveBranchIds()
        // (chi nhánh GỐC đang hiệu lực) rồi User::visibleProductCategoryIds() để mở rộng đúng
        // cây con — nguồn dữ liệu này đã bao gồm sẵn chính chi nhánh GỐC (không chỉ con), nên
        // không lo mất category CHA của các category con đang hiển thị.
        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            // effectiveBranchIds() rỗng với super_admin là cực hiếm (hệ thống chưa có chi nhánh
            // gốc nào) — coi như không giới hạn thêm.
            if ($user->isSuperAdmin()) {
                return $query;
            }

            // Không có chi nhánh 'product' nào hợp lệ (vd chọn switcher ra ngoài phạm vi quyền).
            return $query->whereRaw('1 = 0');
        }

        $visibleProductIds = $user->visibleProductCategoryIds($branchIds);

        return $query->whereIn('id', $visibleProductIds);
    }

    public static function form(Form $form): Form
    {
        return CategoryForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return CategoryTable::table($table);
    }

    public static function getRelationManagers(): array
    {
        return [
            ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategory::route('/'),
            'edit'  => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
