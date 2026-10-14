<?php

declare(strict_types=1);

namespace Modules\Employee\Entities;

use App\Models\Concerns\BelongsToPartner;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Một ngày chấm công của một nhân viên — xem migration create_attendance_and_payroll_tables.
class EmployeeAttendance extends Model
{
    use BelongsToPartner, LogsAuditTrail;

    public const STATUSES = [
        'present' => 'Đi làm',
        'late' => 'Đi muộn',
        'absent' => 'Nghỉ không phép',
        'leave' => 'Nghỉ phép có lương',
        'day_off' => 'Ngày nghỉ',
    ];

    protected $fillable = [
        'partner_id', 'employee_id', 'work_date', 'check_in_at', 'check_out_at', 'status', 'worked_minutes', 'note', 'source', 'created_by',
    ];

    protected $casts = [
        'work_date' => 'date:Y-m-d',
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'worked_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        // Tự tính số phút làm khi đủ giờ vào/ra và gán đối tác theo nhân viên (không tin partner_id client gửi lên).
        static::saving(function (self $a): void {
            if ($a->check_in_at && $a->check_out_at && $a->check_out_at->gt($a->check_in_at)) {
                $a->worked_minutes = (int) min(65535, $a->check_in_at->diffInMinutes($a->check_out_at));
            }
            if (! $a->partner_id && $a->employee_id) {
                $a->partner_id = Employee::withoutGlobalScopes()->whereKey($a->employee_id)->value('partner_id');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
