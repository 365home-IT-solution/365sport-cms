<?php

namespace Modules\Coach\App\Filament\Resources\CoachResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Coach\App\Filament\Resources\CoachResource;

class ListCoaches extends ListRecords
{
    protected static string $resource = CoachResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
