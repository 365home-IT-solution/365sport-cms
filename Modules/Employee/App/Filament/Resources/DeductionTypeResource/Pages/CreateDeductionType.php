<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\DeductionTypeResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Employee\App\Filament\Resources\DeductionTypeResource;

class CreateDeductionType extends CreateRecord
{
    protected static string $resource = DeductionTypeResource::class;
}
