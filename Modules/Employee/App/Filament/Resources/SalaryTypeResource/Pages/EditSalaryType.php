<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTypeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Employee\App\Filament\Resources\SalaryTypeResource;

class EditSalaryType extends EditRecord
{
    protected static string $resource = SalaryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
