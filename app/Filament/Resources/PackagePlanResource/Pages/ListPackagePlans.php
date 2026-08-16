<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackagePlanResource\Pages;

use App\Filament\Resources\PackagePlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackagePlans extends ListRecords
{
    protected static string $resource = PackagePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Thêm gói'),
        ];
    }
}
