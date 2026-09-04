<?php

namespace Modules\Payment\App\Filament\Resources;

use Illuminate\Database\Eloquent\Builder;
use Modules\Payment\App\Filament\Resources\OrderResource\Forms\OrderForm;
use Modules\Payment\App\Filament\Resources\OrderResource\Tables\OrderTable;
use Modules\Payment\App\Filament\Resources\OrderResource\Pages;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Modules\Payment\Entities\Order;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getNavigationGroup(): ?string
    {
        return 'Quản lý';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationIcon(): string
    {
        return __('payment::order.resource.navigation_icon');
    }

    public static function getNavigationLabel(): string
    {
        return __('payment::order.resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('payment::order.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('payment::order.resource.plural_model_label');
    }


    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_any_order') ?? false) && ($user?->partnerHasFeature('orders') ?? false);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create_order') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_order') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_order') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('delete_any_order') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        // Nhân viên đối tác NỀN TẢNG (vd 365home) đặt sân hộ mọi đối tác khác — họ cần xem lại
        // đúng những đơn CHÍNH HỌ đã tạo (created_by), CỘNG với đơn của đối tác mình, nhưng KHÔNG
        // được thấy đơn của đối tác khác do NHÂN VIÊN CỦA ĐỐI TÁC ĐÓ tự tạo. Phải gỡ hẳn global
        // scope 'partner' của Order để tự viết lại điều kiện lọc (partner_id CỦA MÌNH OR
        // created_by CỦA MÌNH), vì scope mặc định sẽ chỉ giữ đúng 1 vế partner_id. Trường hợp
        // đặc thù này giữ nguyên, KHÔNG áp thêm lọc switcher (đơn "đặt hộ" có category_id thuộc
        // đối tác KHÁC, không nằm trong cây chi nhánh của chính nhân viên nền tảng).
        //
        // "BOOKING" chỉ quản lý đơn ĐẶT SÂN thật (order_type='booking') — đơn MUA GÓI nhiều buổi
        // (order_type='package_purchase') có hẳn 1 Resource RIÊNG với Form/Table/trang Sửa RIÊNG,
        // không dùng chung form đặt sân (không có khung giờ/check-in/check-out) — xem
        // Modules\Payment\App\Filament\Resources\PackageOrderResource. Loại hẳn ở đây (không chỉ
        // ẩn khỏi danh sách) để route Sửa/Xem của OrderResource cũng không mở nhầm đơn mua gói —
        // PackageOrderResource có route/trang Sửa riêng cho đúng loại đơn này.
        if ($user && $user->belongsToPlatformPartner()) {
            return Order::withoutGlobalScope('partner')
                ->where(function (Builder $q) use ($user) {
                    $q->where('partner_id', $user->partner_id)
                        ->orWhere('created_by', $user->id);
                })
                ->where('order_type', 'booking');
        }

        $query = parent::getEloquentQuery()->where('order_type', 'booking');

        if (! $user) {
            return $query;
        }

        // Luôn tôn trọng "Chuyển đổi chi nhánh" ở header (App\Livewire\BranchSwitcher), KỂ CẢ
        // super_admin. TRƯỚC ĐÂY super_admin return parent::getEloquentQuery() ngay ở trên, bỏ
        // qua hẳn lựa chọn chi nhánh trên switcher — chọn riêng vài chi nhánh xong danh sách
        // Booking vẫn hiện đủ mọi chi nhánh khác (bug đã gặp). Đồng thời đổi user thường từ
        // allowedCategoryIds() (toàn bộ quyền được CẤP, không quan tâm switcher) sang
        // effectiveBranchIds() (chi nhánh ĐANG CHỌN, giao với quyền được cấp) — cùng nguồn dữ
        // liệu mà BelongsToActiveBranchCategories đang dùng cho Product/Coupon, để mọi nơi lọc
        // theo chi nhánh nhất quán với nhau.
        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            return $user->isSuperAdmin() ? $query : $query->whereRaw('1 = 0');
        }

        $categoryIds = $user->visibleProductCategoryIds($branchIds);

        if (empty($categoryIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('category_id', $categoryIds);
    }

    public static function form(Form $form): Form
    {
        return OrderForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return OrderTable::table($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrder::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
