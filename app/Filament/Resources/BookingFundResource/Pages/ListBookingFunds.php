<?php

declare(strict_types=1);

namespace App\Filament\Resources\BookingFundResource\Pages;

use App\Filament\Resources\BookingFundResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingFunds extends ListRecords
{
    protected static string $resource = BookingFundResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
