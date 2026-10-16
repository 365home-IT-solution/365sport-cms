<?php

declare(strict_types=1);

namespace Modules\Book\App\Filament\Resources;

use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Book\App\Filament\Resources\PriceBoardResource\Forms\PriceBoardSheetsForm;
use Modules\Book\App\Filament\Resources\PriceBoardResource\Pages;
use Modules\Book\App\Filament\Resources\PriceBoardResource\Tables\PriceBoardTable;
use Modules\Product\App\Models\PriceBoard;

/**
 * Port từ 365home-cms — quản lý các "bảng giá" đặt tên, có thời hạn (Tết, khuyến mãi, đối tác...)
 * bổ sung cho trang "Hệ thống giá" (BookResource/SettingBook, vẫn giữ nguyên = sửa bảng giá mặc
 * định). Xem App\Services\PriceBoardSyncService để biết cơ chế áp/khôi phục giá theo ngày hiệu lực.
 * Bảng giá thuộc đối tác (partner_id) — xem thêm PriceBoardSheetsForm (bộ giá, phạm vi sân/chi nhánh/đối tác).
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
        // Đối tác chỉ thấy bảng giá của mình; super_admin (chưa chọn đối tác) thấy tất cả.
        $query = parent::getEloquentQuery()->where('is_default', false)->with('scopeBranches:id,name');
        $user  = auth()->user();

        return $user instanceof User && ! $user->seesAllPartners()
            ? $query->where('partner_id', $user->partner_id)
            : $query;
    }

    // Mặc định CHỈ super_admin — bản chất là công cụ đổi giá hàng loạt (ghi thẳng xuống hệ thống, không
    // xem trước). Vẫn OR thêm quyền Shield riêng của resource này (view_any_price::board...,
    // `php artisan shield:generate --resource=PriceBoardResource`) — để mở cho vai trò đối tác cụ thể
    // chỉ cần vào "Vai trò" tick quyền đó, KHÔNG cần sửa code. Bảng giá thuộc đối tác (partner_id) và
    // getEloquentQuery() đã giới hạn đối tác chỉ thấy bảng của mình.
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() || ($user?->can('view_any_price::board') ?? false);
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() || ($user?->can('create_price::board') ?? false);
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() || ($user?->can('update_price::board') ?? false);
    }

    public static function canDelete($record): bool
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() || ($user?->can('delete_price::board') ?? false);
    }

    public static function form(Form $form): Form
    {
        return PriceBoardSheetsForm::form($form);
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
