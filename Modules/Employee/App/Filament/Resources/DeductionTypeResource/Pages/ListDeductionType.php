<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\DeductionTypeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\DeductionTypeResource;

class ListDeductionType extends ListRecords
{
    protected static string $resource = DeductionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
