<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackagePlanResource\Pages;

use App\Filament\Resources\PackagePlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePackagePlan extends CreateRecord
{
    protected static string $resource = PackagePlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // partner_id suy từ sân đã chọn — khớp cách BelongsToPartner tự gán ở các resource khác,
        // nhưng resource này KHÔNG dùng trait đó (partner_id ở đây LUÔN đi theo product, không
        // theo user đang tạo — admin đối tác A vẫn có thể (về mặt kỹ thuật) tạo gói cho sân của
        // đối tác khác nếu Select không lọc, nên set cứng theo product cho chắc).
        $product = \Modules\Product\App\Models\Product::find($data['product_id'] ?? null);
        $data['partner_id'] = $product?->partner_id;

        return $data;
    }
}
