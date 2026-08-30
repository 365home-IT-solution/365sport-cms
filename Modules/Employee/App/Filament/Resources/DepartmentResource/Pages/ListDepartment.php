<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\DepartmentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\DepartmentResource;

class ListDepartment extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
