<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\EmployeeAttendanceResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Employee\App\Filament\Resources\EmployeeAttendanceResource;

class EditEmployeeAttendance extends EditRecord
{
    protected static string $resource = EmployeeAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
