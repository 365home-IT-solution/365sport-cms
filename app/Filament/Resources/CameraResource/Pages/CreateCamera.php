<?php

declare(strict_types=1);

namespace App\Filament\Resources\CameraResource\Pages;

use App\Filament\Resources\CameraResource;
use App\Models\Camera;
use Filament\Resources\Pages\CreateRecord;

class CreateCamera extends CreateRecord
{
    protected static string $resource = CameraResource::class;

    protected function afterCreate(): void
    {
        /** @var Camera $camera */
        $camera = $this->record;
        CameraResource::syncGo2Rtc($camera);
    }
}
