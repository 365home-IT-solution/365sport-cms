<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\SalaryTemplateResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\SalaryTemplateResource;

class ListSalaryTemplate extends ListRecords
{
    protected static string $resource = SalaryTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
