<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\PositionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Employee\App\Filament\Resources\PositionResource;

class CreatePosition extends CreateRecord
{
    protected static string $resource = PositionResource::class;
}
