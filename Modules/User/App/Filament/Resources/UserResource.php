<?php

declare(strict_types=1);

namespace Modules\User\App\Filament\Resources;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\User\App\Filament\Resources\UserResource\Forms\UserForm;
use Modules\User\App\Filament\Resources\UserResource\Tables\UserTable;
use Modules\User\App\Filament\Resources\UserResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Support\Htmlable;
use JulioMotol\FilamentPasswordConfirmation\RequiresPasswordConfirmation;

class UserResource extends Resource
{
    use RequiresPasswordConfirmation;
    
    protected static ?string $model = User::class;

    protected static int $globalSearchResultsLimit = 20;

    protected static ?string $recordTitleAttribute = 'name';

    // Tài khoản nhân viên bị khoá theo gói dịch vụ đối tác — không ảnh hưởng super_admin (luôn
    // true, xem User::partnerHasFeature()) nên super_admin vẫn quản lý User bình thường.
    public static function canViewAny(): bool
    {
        return parent::canViewAny() && (auth()->user()?->partnerHasFeature('staff_accounts') ?? false);
    }

    public static function getNavigationIcon(): string
    {
        return __('user::user.resource.navigation_icon');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('user::user.resource.navigation_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('user::user.resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('user::user.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('user::user.resource.plural_model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if (! $user) {
            return $query;
        }

        // TRƯỚC ĐÂY super_admin return thẳng $query ở đây, bỏ qua HẲN cả lọc partner lẫn "Chuyển
        // đổi chi nhánh" ở header — chọn riêng vài chi nhánh trên switcher xong danh sách "Thành
        // Viên" vẫn hiện tài khoản của MỌI đối tác (bug đã gặp, giống hệt OrderResource trước khi
        // sửa). User thường cũng chỉ lọc theo partner_id, không quan tâm switcher.
        //
        // User không có category_id/branch_id trực tiếp — chỉ gắn chi nhánh GIÁN TIẾP qua 1 trong 2
        // đường: (1) partner_id của TÀI KHOẢN (chủ đối tác + nhân viên không bị giới hạn chi nhánh
        // cụ thể — xem User::allowedBranchIds() rỗng nghĩa là "không giới hạn, thấy hết chi nhánh
        // của đối tác mình"), hoặc (2) UserBranchPermission.category_id (nhân viên bị giới hạn chỉ
        // xem/làm việc ở 1 vài chi nhánh cụ thể trong đối tác). Phải khớp ĐÚNG 1 TRONG 2 đường đó
        // với tập chi nhánh đang hiệu lực (effectiveBranchIds()), không thể whereIn thẳng 1 cột.
        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            $query = $user->isSuperAdmin() ? $query : $query->whereRaw('1 = 0');
        } else {
            $categoryIds = $user->visibleProductCategoryIds($branchIds);

            if (empty($categoryIds)) {
                $query = $query->whereRaw('1 = 0');
            } else {
                $partnerIds = \Modules\Category\Entities\Category::whereIn('id', $categoryIds)
                    ->whereNotNull('partner_id')
                    ->pluck('partner_id')
                    ->unique()
                    ->values()
                    ->all();

                $query = $query->whereIn('partner_id', $partnerIds)
                    ->where(function (Builder $q) use ($categoryIds) {
                        $q->doesntHave('branchPermissions')
                            ->orWhereHas('branchPermissions', fn (Builder $bp) => $bp->whereIn('category_id', $categoryIds));
                    });
            }
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Mỗi đối tác chỉ thấy tài khoản (chủ đối tác + nhân viên) thuộc partner_id của mình.
        return $query->where('partner_id', $user->partner_id);
    }

    public static function form(Form $form): Form
    {
        return UserForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return UserTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->email;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['email', 'fullname',];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'name' => $record->fullname,
        ];
    }

}
