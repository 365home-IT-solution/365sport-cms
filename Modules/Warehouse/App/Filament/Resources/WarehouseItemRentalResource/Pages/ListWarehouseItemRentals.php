<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Filament\Resources\WarehouseItemRentalResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Warehouse\App\Filament\Resources\WarehouseItemRentalResource;

class ListWarehouseItemRentals extends ListRecords
{
    protected static string $resource = WarehouseItemRentalResource::class;
}
