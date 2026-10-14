<?php

declare(strict_types=1);

namespace Modules\Employee\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Phiếu lương một nhân viên trong một kỳ — ảnh chụp số liệu lúc chốt (tên, mã, các khoản), xem PayrollPeriod.
class PayrollSlip extends Model
{
    protected $fillable = [
        'payroll_period_id', 'employee_id', 'employee_name', 'employee_code', 'base_amount', 'piece_rate_amount', 'allowances_total',
        'deductions_total', 'absence_deduction', 'days_present', 'days_absent', 'days_leave', 'final_amount', 'breakdown', 'paid_at', 'payment_method',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'paid_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
