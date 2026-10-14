<?php

declare(strict_types=1);

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Resources\TournamentResource;
use Filament\Resources\Pages\EditRecord;

class EditTournament extends EditRecord
{
    protected static string $resource = TournamentResource::class;
}
