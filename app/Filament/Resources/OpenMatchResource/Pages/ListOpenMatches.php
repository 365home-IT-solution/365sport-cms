<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpenMatchResource\Pages;

use App\Filament\Resources\OpenMatchResource;
use Filament\Resources\Pages\ListRecords;

class ListOpenMatches extends ListRecords
{
    protected static string $resource = OpenMatchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
