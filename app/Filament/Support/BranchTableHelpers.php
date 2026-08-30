<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Modules\Category\Entities\Category;

// Cột + bộ lọc "Chi nhánh" dùng chung cho mọi Filament Table có model gắn branch_id (xem
// App\Models\Concerns\BelongsToBranch) — rập khuôn PartnerTableHelpers nhưng phạm vi hẹp hơn: lọc
// theo chi nhánh THAY VÌ đối tác. Chỉ hiện bộ lọc khi thật sự có ý nghĩa lựa chọn — super_admin
// (thấy mọi chi nhánh) hoặc 1 tài khoản quản lý NHIỀU HƠN 1 chi nhánh; tài khoản chỉ quản lý đúng 1
// chi nhánh thì dữ liệu vốn đã lọc sẵn qua global scope, không cần thêm bộ lọc thừa.
class BranchTableHelpers
{
    public static function column(string $relationOrColumn = 'branch.name'): TextColumn
    {
        return TextColumn::make($relationOrColumn)
            ->label('Chi nhánh')
            ->badge()
            ->color('gray')
            ->placeholder('— Chưa gán chi nhánh —')
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function filter(string $column = 'branch_id'): SelectFilter
    {
        return SelectFilter::make($column)
            ->label('Chi nhánh')
            ->options(fn () => static::branchOptions())
            ->searchable()
            ->preload()
            ->visible(fn () => static::shouldShowFilter());
    }

    private static function branchOptions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $branchIds = $user->rootProductCategoryIds();
        if (empty($branchIds)) {
            return [];
        }

        return Category::whereIn('id', $branchIds)->orderBy('name')->pluck('name', 'id')->all();
    }

    private static function shouldShowFilter(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return count($user->rootProductCategoryIds()) > 1;
    }
}
