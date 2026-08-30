<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPackageResource\Pages;

use App\Filament\Resources\CustomerPackageResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerPackages extends ListRecords
{
    protected static string $resource = CustomerPackageResource::class;

    protected function getHeaderActions(): array
    {
        // Không có nút "Thêm mới" — gói chỉ phát sinh qua khách tự mua hoặc action "Bán gói" ở
        // PackagePlanResource (xem ghi chú canCreate() trong Resource).
        return [];
    }
}
