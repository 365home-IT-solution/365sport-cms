<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\DeductionTypeResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Employee\App\Filament\Resources\DeductionTypeResource;

class EditDeductionType extends EditRecord
{
    protected static string $resource = DeductionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
