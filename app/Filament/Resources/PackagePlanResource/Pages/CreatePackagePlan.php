<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackagePlanResource\Pages;

use App\Filament\Resources\PackagePlanResource;
use App\Filament\Resources\PackagePlanResource\Concerns\EnforcesPackagePlanScope;
use Filament\Resources\Pages\CreateRecord;

class CreatePackagePlan extends CreateRecord
{
    use EnforcesPackagePlanScope;

    protected static string $resource = PackagePlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->enforceScopeExclusivity($data);

        // partner_id suy từ PHẠM VI đã chọn (không theo user đang tạo — admin đối tác A vẫn có
        // thể, về mặt kỹ thuật, tạo gói cho sân/chi nhánh của đối tác khác nếu Select không lọc,
        // nên set cứng theo đúng chủ sở hữu thật của phạm vi cho chắc). TRƯỚC ĐÂY chỉ suy từ
        // product_id — phạm vi "Toàn bộ chi nhánh"/"Một số sân trong chi nhánh" (product_id null)
        // luôn bị bỏ trống partner_id (bug thật: sau khi PackagePlan gắn BelongsToPartner, gói
        // dạng này biến mất khỏi danh sách của MỌI đối tác, kể cả đối tác đã tạo ra nó).
        if (! empty($data['product_id'])) {
            $data['partner_id'] = \Modules\Product\App\Models\Product::find($data['product_id'])?->partner_id;
        } elseif (! empty($data['category_id'])) {
            $data['partner_id'] = \Modules\Category\Entities\Category::find($data['category_id'])?->partner_id;
        } elseif (! empty($data['product_ids'][0] ?? null)) {
            $data['partner_id'] = \Modules\Product\App\Models\Product::find($data['product_ids'][0])?->partner_id;
        }

        return $data;
    }
}
