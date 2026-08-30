<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerPlanResource\Pages;

use App\Filament\Resources\PartnerPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartnerPlan extends EditRecord
{
    protected static string $resource = PartnerPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
