<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\AllowanceTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Employee\App\Filament\Resources\AllowanceTypeResource;

class CreateAllowanceType extends CreateRecord
{
    protected static string $resource = AllowanceTypeResource::class;
}
