<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\AllowanceTypeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\AllowanceTypeResource;

class ListAllowanceType extends ListRecords
{
    protected static string $resource = AllowanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
