<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\PositionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Employee\App\Filament\Resources\PositionResource;

class EditPosition extends EditRecord
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
