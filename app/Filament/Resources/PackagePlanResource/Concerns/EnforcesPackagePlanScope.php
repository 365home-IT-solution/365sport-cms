<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackagePlanResource\Concerns;

// Ép ĐÚNG 1 TRONG 3 phạm vi loại trừ lẫn nhau (product_id / category_id+product_ids / category_id
// đơn) dựa trên field ẢO 'scope_type' — dùng CHUNG cho cả CreatePackagePlan lẫn EditPackagePlan,
// TẬP TRUNG về 1 chỗ duy nhất thay vì để từng field tự ->dehydrateStateUsing() riêng lẻ (đã CHỨNG
// MINH không đáng tin cậy: gặp thật product_id CŨ không được xoá dù category_id/product_ids MỚI đã
// lưu đúng — xem ghi chú dài tại field 'scope_type' trong PackagePlanResource::form()).
trait EnforcesPackagePlanScope
{
    protected function enforceScopeExclusivity(array $data): array
    {
        $scope = $data['scope_type'] ?? 'product';
        unset($data['scope_type']);

        $data['product_id']  = $scope === 'product' ? ($data['product_id'] ?? null) : null;
        $data['category_id'] = in_array($scope, ['category', 'selected'], true) ? ($data['category_id'] ?? null) : null;
        $data['product_ids'] = $scope === 'selected' ? array_values($data['product_ids'] ?? []) : null;

        return $data;
    }
}
