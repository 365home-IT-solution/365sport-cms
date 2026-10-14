<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\EmployeeAttendanceResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Employee\App\Filament\Resources\EmployeeAttendanceResource;

class ListEmployeeAttendances extends ListRecords
{
    protected static string $resource = EmployeeAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
