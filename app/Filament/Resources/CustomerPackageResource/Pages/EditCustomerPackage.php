<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPackageResource\Pages;

use App\Filament\Resources\CustomerPackageResource;
use Filament\Resources\Pages\EditRecord;

class EditCustomerPackage extends EditRecord
{
    protected static string $resource = CustomerPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
