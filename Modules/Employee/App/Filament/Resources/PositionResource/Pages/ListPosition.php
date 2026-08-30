<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\PositionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\PositionResource;

class ListPosition extends ListRecords
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
