<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Employee\App\Filament\Resources\SalaryTypeResource;

class CreateSalaryType extends CreateRecord
{
    protected static string $resource = SalaryTypeResource::class;
}
