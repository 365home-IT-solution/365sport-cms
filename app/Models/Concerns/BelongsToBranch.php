<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\AdminPanelContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Category\Entities\Category;

// Áp dụng vào các model kho (warehouse_items/stock_ins/stock_outs/stock_checks) cần tách riêng
// theo CHI NHÁNH bên trong 1 đối tác — khác BelongsToPartner (lọc theo cả đối tác), trait này lọc
// hẹp hơn theo branch_id (trỏ tới category gốc, category_type='product', parent_id NULL). Rập
// khuôn đúng cấu trúc/lý do của BelongsToPartner (xem file đó) — chỉ chạy trong admin panel, dùng
// chung nguồn xác thực chi nhánh User::rootProductCategoryIds() (đã dùng ở AuthController,
// WarehouseRoomOptions...).
trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            // Cùng lý do như BelongsToPartner::bootBelongsToPartner() — chỉ lọc theo chi nhánh bên
            // trong admin panel Filament, không rò rỉ ra route phía khách hàng.
            if (! AdminPanelContext::isActive()) {
                return;
            }

            $user = auth()->user();

            if (! $user instanceof User || $user->isSuperAdmin()) {
                return;
            }

            $branchIds = $user->rootProductCategoryIds();

            // whereIn với mảng rỗng tự trả về 0 dòng (đúng ý muốn: user không quản lý chi nhánh
            // nào thì không thấy dữ liệu kho nào) — không cần xử lý riêng trường hợp rỗng.
            $builder->whereIn($builder->getModel()->getTable() . '.branch_id', $branchIds);
        });

        static::creating(function ($model) {
            if (! empty($model->branch_id) || ! AdminPanelContext::isActive()) {
                return;
            }

            $user = auth()->user();
            if (! $user instanceof User) {
                return;
            }

            // Chỉ tự gán khi user quản lý ĐÚNG 1 chi nhánh — quản lý nhiều chi nhánh thì bắt buộc
            // tự chọn qua field trên form (không đoán chi nhánh nào đúng).
            $branchIds = $user->rootProductCategoryIds();
            if (count($branchIds) === 1) {
                $model->branch_id = $branchIds[0];
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'branch_id');
    }
}
