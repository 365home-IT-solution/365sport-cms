<?php

declare(strict_types=1);

namespace Modules\Zns\App\Filament\Resources;

use Modules\Zns\App\Filament\Resources\ZnsNotificationResource\Forms\ZnsNotificationForm;
use Modules\Zns\App\Filament\Resources\ZnsNotificationResource\Tables\Actions\ZnsNotificationAction;
use Modules\Zns\App\Filament\Resources\ZnsNotificationResource\Tables\ZnsNotificationTable;
use Modules\Zns\App\Filament\Resources\ZnsNotificationResource\Pages;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Zns\App\Models\ZnsNotification;

class ZnsNotificationResource extends Resource
{
    protected static ?string $model = ZnsNotification::class;

    // ZnsNotification KHÔNG có partner_id/category_id riêng — gắn chi nhánh GIÁN TIẾP qua
    // order_id -> Order::category_id (Order tự lọc theo BelongsToPartner + branch qua
    // OrderResource riêng, ở đây phải tự lọc lại vì ZnsNotification không kế thừa global scope
    // của Order). TRƯỚC ĐÂY không có getEloquentQuery() nào, lộ thông báo ZNS của MỌI đối tác.
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

        return $query->whereHas('order', fn (Builder $q) => $q->whereIn('category_id', $categoryIds));
    }

    protected static ?string $navigationIcon = 'heroicon-c-bell-snooze';

    // Trùng với chức năng "Cấu hình web" — ẩn khỏi menu, giữ nguyên code/route.
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Cấu hình web';
    }

    public static function getNavigationSort(): ?int
    {
        return 13;
    }

    public static function getNavigationLabel(): string
    {
        return 'Thông báo ZNS';
    }

    public static function getModelLabel(): string
    {
        return 'Thông báo ZNS';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Thông báo ZNS';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_zns::notification') ?? false;
    }

    // public static function getNavigationBadge(): ?string
    // {
    //     return static::getModel()::count();
    // }

    public static function form(Form $form): Form
    {
        return ZnsNotificationForm::form($form);
    }

    public static function table(Table $table): Table
    {
        return ZnsNotificationTable::table($table)
        ->actions(
            ZnsNotificationAction::action()
        );
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return ZnsNotificationTable::infolist($infolist);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListZnsNotification::route('/'),
            'create' => Pages\CreateZnsNotification::route('/create'),
            'edit' => Pages\EditZnsNotification::route('/{record}/edit'),
            'view' => Pages\ViewZnsNotification::route('/{record}'),
        ];
    }
}