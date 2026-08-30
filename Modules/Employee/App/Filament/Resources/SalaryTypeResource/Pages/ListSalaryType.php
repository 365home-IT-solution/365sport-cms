<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTypeResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\SalaryTypeResource;

class ListSalaryType extends ListRecords
{
    protected static string $resource = SalaryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
