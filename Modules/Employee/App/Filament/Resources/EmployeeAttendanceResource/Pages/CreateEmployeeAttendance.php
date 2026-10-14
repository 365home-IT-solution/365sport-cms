<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament\Resources\EmployeeAttendanceResource\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Employee\App\Filament\Resources\EmployeeAttendanceResource;
use Modules\Employee\Entities\EmployeeAttendance;

class CreateEmployeeAttendance extends CreateRecord
{
    protected static string $resource = EmployeeAttendanceResource::class;

    // Mỗi nhân viên chỉ có một dòng cho một ngày: nhập lại ngày đã có thì ghi đè dòng đó (giống API) thay vì báo lỗi trùng khoá.
    protected function handleRecordCreation(array $data): Model
    {
        $data['source'] = 'manual';
        $data['created_by'] = (string) auth()->id();

        return EmployeeAttendance::withoutGlobalScopes()->updateOrCreate(
            ['employee_id' => $data['employee_id'], 'work_date' => $data['work_date']],
            collect($data)->except(['employee_id', 'work_date'])->all()
        );
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()->success()->title('Đã lưu chấm công');
    }
}
