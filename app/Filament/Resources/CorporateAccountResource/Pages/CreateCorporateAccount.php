<?php

declare(strict_types=1);

namespace App\Filament\Resources\CorporateAccountResource\Pages;

use App\Filament\Resources\CorporateAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCorporateAccount extends CreateRecord
{
    protected static string $resource = CorporateAccountResource::class;

    // hours_used_current_cycle KHÔNG có trong form lúc tạo (chưa dùng gì cả) — luôn khởi tạo 0,
    // tránh phụ thuộc default() cột DB (an toàn ngay cả nếu ai đó sau này đổi default migration).
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['hours_used_current_cycle'] = 0;
        $data['status'] = $data['status'] ?? \App\Models\CorporateAccount::STATUS_ACTIVE;

        return $data;
    }
}
