<?php

declare(strict_types=1);

namespace Modules\User\App\Filament\Resources\UserResource\Pages;

use Modules\User\App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Illuminate\Database\Eloquent\Builder;
use JoseEspinal\RecordNavigation\Traits\HasRecordsList;

class ListUsers extends ListRecords
{
    use ExposesTableToWidgets;
    use HasRecordsList;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return static::$resource::getWidgets();
    }

    // Tabs chỉ hiện với super_admin
    public function getTabs(): array
    {
        if (! auth()->user()?->isSuperAdmin()) {
            return [];
        }

        // Đếm theo ĐÚNG cùng nguồn dữ liệu với bảng (UserResource::getEloquentQuery(), có tôn
        // trọng "Chuyển đổi chi nhánh" — trước đây đếm thẳng từ User::query() không lọc gì, badge
        // hiện sai lệch cả về chi nhánh lẫn partner so với bảng thực tế hiển thị bên dưới).
        $base = $this->getTableQuery();

        $adminCount    = (clone $base)->whereHas('roles')->count();
        $customerCount = (clone $base)->whereDoesntHave('roles')->count();

        return [
            'admins' => Tab::make('Quản trị viên')
                ->icon('heroicon-o-shield-check')
                ->badge($adminCount)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('roles')),

            'customers' => Tab::make('Khách hàng')
                ->icon('heroicon-o-users')
                ->badge($customerCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('roles')),
        ];
    }

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        // BẮT BUỘC đi qua UserResource::getEloquentQuery() — trước đây method này tự dựng query
        // MỚI TOANH từ Model (`new User)::query()`), hoàn toàn bỏ qua getEloquentQuery() của
        // Resource, nên switcher/lọc partner sửa ở Resource KHÔNG có tác dụng gì với bảng thật sự
        // hiển thị (bug đã gặp: sửa Resource xong, trang /admin/users vẫn hiện đủ mọi đối tác).
        $query = UserResource::getEloquentQuery()
            ->with('roles')
            ->where('id', '!=', $user->id);

        if ($user->isSuperAdmin()) {
            return $query;
        }

        // partner_id đã được UserResource::getEloquentQuery() lọc sẵn — chỉ còn thu hẹp thêm phần
        // đặc thù riêng của trang danh sách: không hiện "khách hàng" (whereDoesntHave roles) và
        // không hiện tài khoản super_admin khác (dù cùng partner_id null thì cũng không lọt qua vì
        // getEloquentQuery() đã ép partner_id = $user->partner_id).
        return $query
            ->whereHas('roles')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', config('filament-shield.super_admin.name')));
    }
}
