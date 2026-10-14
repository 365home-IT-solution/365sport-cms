<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Resources\TournamentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTournament extends CreateRecord
{
    protected static string $resource = TournamentResource::class;
}
