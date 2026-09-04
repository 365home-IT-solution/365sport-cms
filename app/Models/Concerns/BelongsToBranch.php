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
// khuôn đúng cấu trúc/lý do của BelongsToPartner (xem file đó) — chỉ chạy trong admin panel.
//
// Dùng User::effectiveBranchIds() (KHÔNG phải rootProductCategoryIds() trần) — tôn trọng lựa chọn
// tạm thời qua nút "Chuyển đổi chi nhánh" ở header (App\Livewire\BranchSwitcher) khi user thu hẹp
// lại còn 1/vài chi nhánh trong số được phép; mặc định (chưa bấm) = mọi chi nhánh được phép, kể cả
// super_admin — nhờ vậy super_admin mặc định vẫn thấy hết như trước, chỉ bị lọc khi CHỦ ĐỘNG chọn.
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

            if (! $user instanceof User) {
                return;
            }

            $branchIds = $user->effectiveBranchIds();

            // Không xác định được chi nhánh nào (vd tài khoản chưa gán đối tác) — không lọc thêm
            // để tránh vô tình ẩn hết dữ liệu; scope partner_id (BelongsToPartner) vẫn áp dụng
            // song song.
            if (empty($branchIds)) {
                return;
            }

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

            // Chỉ tự gán khi tài khoản CHỈ quản lý (hoặc đang thu hẹp còn) ĐÚNG 1 chi nhánh — nếu
            // quản lý nhiều hơn 1, bắt buộc phải tự chọn qua field trên form, không đoán bừa 1
            // trong nhiều chi nhánh.
            $branchIds = $user->effectiveBranchIds();
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
