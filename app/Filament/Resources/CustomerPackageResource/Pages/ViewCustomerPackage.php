<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPackageResource\Pages;

use App\Filament\Resources\CustomerPackageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerPackage extends ViewRecord
{
    protected static string $resource = CustomerPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Sửa'),
        ];
    }
}
