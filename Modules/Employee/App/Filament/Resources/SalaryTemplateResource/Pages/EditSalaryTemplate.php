<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Employee\App\Filament\Resources\SalaryTemplateResource;

class EditSalaryTemplate extends EditRecord
{
    protected static string $resource = SalaryTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
