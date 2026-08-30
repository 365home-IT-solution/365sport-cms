<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\DepartmentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Employee\App\Filament\Resources\DepartmentResource;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
