<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\AllowanceTypeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Employee\App\Filament\Resources\AllowanceTypeResource;

class EditAllowanceType extends EditRecord
{
    protected static string $resource = AllowanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
