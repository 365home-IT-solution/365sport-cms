<?php

declare(strict_types=1);

namespace App\Filament\Resources\PartnerLeadResource\Pages;

use App\Filament\Resources\PartnerLeadResource;
use Filament\Resources\Pages\ListRecords;

class ListPartnerLeads extends ListRecords
{
    protected static string $resource = PartnerLeadResource::class;
}
