<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\PackageOrderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Payment\App\Filament\Resources\PackageOrderResource;

class ListPackageOrders extends ListRecords
{
    protected static string $resource = PackageOrderResource::class;
}
