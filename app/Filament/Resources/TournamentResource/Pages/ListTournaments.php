<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Resources\TournamentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTournaments extends ListRecords
{
    protected static string $resource = TournamentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
