<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Employee\Entities\Employee;
use Modules\Employee\Entities\EmployeeAttendance;
use Modules\Employee\Entities\PayrollPeriod;
use Modules\Employee\Entities\PayrollSlip;

// Tính và chốt bảng lương THEO THÁNG (khác trang "Thống kê lương" chỉ tính tháng hiện tại, không lưu).
//
//   Thực nhận = lương cơ bản (hoặc lương theo lượt dọn sân/tư vấn của CHÍNH tháng đó) + phụ cấp − giảm trừ − khấu trừ nghỉ không phép
//   Khấu trừ nghỉ không phép = lương cơ bản / STANDARD_WORK_DAYS × số ngày 'absent' (chỉ vị trí lương cố định; không vượt quá lương cơ bản).
//   Nghỉ phép có lương (leave), ngày nghỉ theo lịch (day_off), đi muộn (late) không bị trừ.
//
// Chốt kỳ lưu ẢNH CHỤP từng phiếu (PayrollSlip) nên đổi mẫu lương/phụ cấp sau đó không làm đổi lương đã chốt.
class PayrollService
{
    public const STANDARD_WORK_DAYS = 26;

    /** @return array<string, mixed> số liệu một nhân viên cho tháng $month (YYYY-MM), chưa lưu */
    public function calculate(Employee $employee, string $month): array
    {
        [$start, $end] = $this->bounds($month);
        $employee->loadMissing(['salaryType', 'allowanceTypes', 'deductionTypes']);

        $isPiece = $employee->is_piece_rate;
        $cleaningRate = (float) ($employee->salaryType->room_cleaning_rate ?? 0);
        $consultRate = (float) ($employee->salaryType->consultation_rate ?? 0);

        $cleaningCount = $isPiece ? $employee->cleaningLogs()->whereNotNull('cleaned_at')->whereBetween('cleaned_at', [$start, $end])->count() : 0;
        $consultCount = $isPiece ? $employee->consultationLogs()->whereBetween('consulted_at', [$start, $end])->count() : 0;

        $piece = (int) round($cleaningCount * $cleaningRate + $consultCount * $consultRate);
        $base = $isPiece ? 0 : (int) round((float) $employee->base_amount);

        $statusCounts = EmployeeAttendance::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $present = (int) ($statusCounts['present'] ?? 0) + (int) ($statusCounts['late'] ?? 0);
        $absent = (int) ($statusCounts['absent'] ?? 0);
        $leave = (int) ($statusCounts['leave'] ?? 0);

        $absenceDeduction = $isPiece || $base <= 0 ? 0 : (int) min($base, round($base / self::STANDARD_WORK_DAYS * $absent));
        $allowances = (int) round((float) $employee->allowanceTypes->sum('pivot.amount'));
        $deductions = (int) round((float) $employee->deductionTypes->sum('pivot.amount'));
        $final = max(0, $base + $piece + $allowances - $deductions - $absenceDeduction);

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'employee_code' => $employee->employee_code,
            'base_amount' => $base,
            'piece_rate_amount' => $piece,
            'allowances_total' => $allowances,
            'deductions_total' => $deductions,
            'absence_deduction' => $absenceDeduction,
            'days_present' => $present,
            'days_absent' => $absent,
            'days_leave' => $leave,
            'final_amount' => $final,
            'breakdown' => [
                'salary_type' => $employee->salaryType?->name,
                'is_piece_rate' => $isPiece,
                'cleaning_count' => $cleaningCount,
                'cleaning_rate' => (int) $cleaningRate,
                'consultation_count' => $consultCount,
                'consultation_rate' => (int) $consultRate,
                'standard_work_days' => self::STANDARD_WORK_DAYS,
                'allowances' => $employee->allowanceTypes->map(fn ($t) => ['name' => $t->name, 'amount' => (int) $t->pivot->amount])->values()->all(),
                'deductions' => $employee->deductionTypes->map(fn ($t) => ['name' => $t->name, 'amount' => (int) $t->pivot->amount])->values()->all(),
            ],
        ];
    }

    /** @return array{month: string, status: string, employee_count: int, total_amount: int, slips: list<array>} bảng lương tháng (đã chốt thì lấy ảnh chụp, chưa thì tính mới) */
    public function report(?string $partnerId, string $month): array
    {
        $period = PayrollPeriod::withoutGlobalScopes()->where('partner_id', $partnerId)->where('month', $month)->first();
        if ($period?->isClosed()) {
            $slips = $period->slips()->orderBy('employee_name')->get()->map(fn (PayrollSlip $s) => $s->toArray())->all();

            return ['month' => $month, 'status' => 'closed', 'employee_count' => $period->employee_count, 'total_amount' => $period->total_amount, 'slips' => $slips];
        }

        $slips = $this->employees($partnerId)->get()->map(fn (Employee $e) => $this->calculate($e, $month))->all();

        return ['month' => $month, 'status' => 'open', 'employee_count' => count($slips), 'total_amount' => (int) array_sum(array_column($slips, 'final_amount')), 'slips' => $slips];
    }

    /** @return array{ok: true, period: PayrollPeriod}|array{ok: false, message: string} */
    public function close(?string $partnerId, string $month, User|string|null $actor): array
    {
        if ($partnerId === null) {
            return ['ok' => false, 'message' => 'A partner is required to close payroll.'];
        }
        [$start] = $this->bounds($month);
        if ($start->isFuture()) {
            return ['ok' => false, 'message' => 'Payroll cannot be closed for a month that has not started yet.'];
        }

        return DB::transaction(function () use ($partnerId, $month, $actor): array {
            $period = PayrollPeriod::withoutGlobalScopes()->lockForUpdate()->firstOrCreate(['partner_id' => $partnerId, 'month' => $month], ['status' => 'open']);
            if ($period->isClosed()) {
                return ['ok' => false, 'message' => 'This payroll period is already closed.'];
            }

            $period->slips()->delete();
            $total = 0;
            $count = 0;
            foreach ($this->employees($partnerId)->get() as $employee) {
                $row = $this->calculate($employee, $month);
                $period->slips()->create($row);
                $total += $row['final_amount'];
                $count++;
            }

            $period->update([
                'status' => 'closed', 'employee_count' => $count, 'total_amount' => $total,
                'closed_at' => now(), 'closed_by' => $actor instanceof User ? (string) $actor->getKey() : ($actor ?: 'system'),
            ]);

            return ['ok' => true, 'period' => $period->fresh('slips')];
        });
    }

    /** Mở lại kỳ đã chốt để tính lại — chỉ khi chưa có phiếu nào đã trả. */
    public function reopen(PayrollPeriod $period): array
    {
        if (! $period->isClosed()) {
            return ['ok' => false, 'message' => 'This payroll period is not closed.'];
        }
        if ($period->slips()->whereNotNull('paid_at')->exists()) {
            return ['ok' => false, 'message' => 'Some payslips have already been paid; the period cannot be reopened.'];
        }

        $period->slips()->delete();
        $period->update(['status' => 'open', 'employee_count' => 0, 'total_amount' => 0, 'closed_at' => null, 'closed_by' => null]);

        return ['ok' => true, 'period' => $period->fresh()];
    }

    public function markPaid(PayrollSlip $slip, string $method): void
    {
        $slip->update(['paid_at' => now(), 'payment_method' => $method]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private function employees(?string $partnerId)
    {
        return Employee::query()->withoutGlobalScopes()->where('partner_id', $partnerId)->where('status', true)->orderBy('name');
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function bounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }
}
