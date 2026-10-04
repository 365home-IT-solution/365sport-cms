<?php

namespace Modules\Coach\App\Filament\Resources\CoachResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Coach\App\Filament\Resources\CoachResource;

class EditCoach extends EditRecord
{
    protected static string $resource = CoachResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
