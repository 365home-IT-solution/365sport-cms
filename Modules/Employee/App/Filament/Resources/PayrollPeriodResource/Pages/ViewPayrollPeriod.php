<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\PayrollPeriodResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Employee\App\Filament\Resources\PayrollPeriodResource;

class ViewPayrollPeriod extends ViewRecord
{
    protected static string $resource = PayrollPeriodResource::class;
}
