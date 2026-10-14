<?php

declare(strict_types=1);

namespace App\Filament\Resources\InvoiceRequestResource\Pages;

use App\Filament\Resources\InvoiceRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceRequests extends ListRecords
{
    protected static string $resource = InvoiceRequestResource::class;
}
