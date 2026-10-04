<?php

declare(strict_types=1);

namespace App\Filament\Resources\CameraAlertResource\Pages;

use App\Filament\Resources\CameraAlertResource;
use Filament\Resources\Pages\ListRecords;

class ListCameraAlerts extends ListRecords
{
    protected static string $resource = CameraAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
