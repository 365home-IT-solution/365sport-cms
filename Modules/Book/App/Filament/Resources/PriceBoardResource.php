<?php

declare(strict_types=1);

namespace Modules\Book\App\Filament\Resources;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Book\App\Filament\Resources\PriceBoardResource\Forms\PriceBoardForm;
use Modules\Book\App\Filament\Resources\PriceBoardResource\Pages;
use Modules\Book\App\Filament\Resources\PriceBoardResource\Tables\PriceBoardTable;
use Modules\Product\App\Models\PriceBoard;

/**
 * Port từ 365home-cms — quản lý các "bảng giá" đặt tên, có thời hạn (Tết, khuyến mãi, đối tác...)
 * bổ sung cho trang "Hệ thống giá" (BookResource/SettingBook, vẫn giữ nguyên = sửa bảng giá mặc
 * định). Xem App\Services\PriceBoardSyncService để biết cơ chế áp/khôi phục giá theo ngày hiệu lực.
 *
 * KHÁC bản home: bỏ khối "Sửa giá hàng loạt theo vị trí/khung giờ across nhiều sân trong 1 form"
 * (home: PriceBoardForm _bulk_mode/_bulk_slot_rules) — phụ thuộc trait Modules\Book\App\Filament\
 * Traits\HasRoomPricingFormFields KHÔNG tồn tại ở dự án này (SettingBook.php ở đây tự viết schema
 * trực tiếp, không tách trait dùng chung). Việc "chỉnh giá hàng loạt" cốt lõi (1 bảng áp cho NHIỀU
 * sân cùng lúc, chọn qua checklist) vẫn đầy đủ — chỉ thiếu tiện ích phụ "đổi 1 khung giờ cho mọi
 * sân trong 1 lần bấm", có thể bổ sung sau nếu cần.
 */
class PriceBoardResource extends Resource
{
    protected static ?string $model = PriceBoard::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Quản lý';
    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return 'Bảng giá';
    }

    public static function getModelLabel(): string
    {
        return 'Bảng giá';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Bảng giá';
    }

    public static function getEloquentQuery(): Builder
    {
        // Bảng mặc định (is_default=true) là dữ liệu nội bộ, đã có trang "Hệ thống giá" quản lý —
        // không hiển thị/cho sửa lẫn ở đây.
        $query = parent::getEloquentQuery()->where('is_default', false);
        $user  = auth()->user();

        if (! $user) {
            return $query;
        }

        // canViewAny() giới hạn CHỈ super_admin truy cập được — vẫn cộng thêm lọc theo "Chuyển
        // đổi chi nhánh" ở header để super_admin thu hẹp được danh sách bảng giá theo đúng chi
        // nhánh đang thao tác, giống BookResource. PriceBoard gắn sân qua many-to-many
        // price_board_items (quan hệ products()), không có branch_id/category_id trực tiếp.
        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            return $query;
        }

        $categoryIds = $user->visibleProductCategoryIds($branchIds);

        if (empty($categoryIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('products.categories', fn (Builder $q) => $q->whereIn('categories.id', $categoryIds));
    }

    // Chỉ super_admin — cùng mức hạn chế với "Sửa giá hàng loạt" ở SettingBook (đã ẩn phần
    // khung giờ/giá với non-super-admin từ trước) vì bản chất là công cụ đổi giá hàng loạt rủi ro
    // tương đương (ghi thẳng xuống hệ thống, không xem trước). Đơn giản hơn bản home (bỏ Shield
    // permission `view_any_price::board` riêng — dự án này chưa chạy `shield:generate` cho resource
    // mới, cùng cách CustomerPackageResource/BookingFundResource đang làm).
    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return PriceBoardForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return PriceBoardTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPriceBoards::route('/'),
            'create' => Pages\CreatePriceBoard::route('/create'),
            'edit'   => Pages\EditPriceBoard::route('/{record}/edit'),
        ];
    }
}
