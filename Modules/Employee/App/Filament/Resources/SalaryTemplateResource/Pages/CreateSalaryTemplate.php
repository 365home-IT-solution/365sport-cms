<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Employee\App\Filament\Resources\SalaryTemplateResource;

class CreateSalaryTemplate extends CreateRecord
{
    protected static string $resource = SalaryTemplateResource::class;
}
