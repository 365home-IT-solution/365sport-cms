<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Employee\Entities\Employee;
use Modules\Employee\Entities\EmployeeAttendance;

// Chấm công nhân viên. Quản lý (có quyền employee::attendance) xem/nhập/sửa/xoá chấm công của nhân viên thuộc đối tác mình; nhân viên bất kỳ
// có hồ sơ nhân sự liên kết với tài khoản (employees.user_id) tự chấm công vào/ra và xem công của chính mình qua /me, /check-in, /check-out
// mà không cần quyền quản lý (xem AdminApiAuth).
class AttendanceController extends Controller
{
    // GET /api/admin/attendance — ?employee_id=, ?month=YYYY-MM, ?date_from=, ?date_to=, ?status=, ?per_page=
    public function index(Request $request): JsonResponse
    {
        $query = $this->visible($request->user())->with('employee:id,name,employee_code')
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('month'), function ($q) use ($request) {
                $m = (string) $request->query('month');
                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) {
                    $q->whereBetween('work_date', ["$m-01", date('Y-m-t', strtotime("$m-01"))]);
                }
            })
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('work_date', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('work_date', '<=', $request->query('date_to')))
            ->orderByDesc('work_date')->orderBy('employee_id');

        return response()->json($query->paginate(min(200, max(1, $request->integer('per_page', 31)))));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->with('employee:id,name,employee_code')->find($id);

        return $row ? response()->json(['data' => $row]) : response()->json(['message' => 'Attendance record not found.'], 404);
    }

    // POST /api/admin/attendance — tạo hoặc ghi đè chấm công của 1 nhân viên 1 ngày (employee_id + work_date là khoá)
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $employee = $this->visibleEmployees($request->user())->find($data['employee_id']);
        if (! $employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        $row = EmployeeAttendance::withoutGlobalScopes()->updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $data['work_date']],
            $this->fields($data) + ['partner_id' => $employee->partner_id, 'source' => 'manual', 'created_by' => (string) $request->user()->getKey()]
        );

        return response()->json(['data' => $row->fresh('employee:id,name,employee_code')], $row->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->find($id);
        if (! $row) {
            return response()->json(['message' => 'Attendance record not found.'], 404);
        }

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(EmployeeAttendance::STATUSES))],
            'check_in_at' => 'sometimes|nullable|date', 'check_out_at' => 'sometimes|nullable|date',
            'note' => 'sometimes|nullable|string|max:500',
        ]);
        $row->fill($data)->save();

        return response()->json(['data' => $row->fresh('employee:id,name,employee_code')]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->find($id);
        if (! $row) {
            return response()->json(['message' => 'Attendance record not found.'], 404);
        }
        $row->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Nhân viên tự chấm công ───────────────────────────────────────────────────────────────

    // GET /api/admin/attendance/me?month=YYYY-MM
    public function mine(Request $request): JsonResponse
    {
        $employee = $this->ownEmployee($request->user());
        if (! $employee) {
            return response()->json(['message' => 'Your account is not linked to an employee profile.'], 404);
        }

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) ? (string) $request->query('month') : now()->format('Y-m');
        $rows = EmployeeAttendance::withoutGlobalScopes()->where('employee_id', $employee->id)
            ->whereBetween('work_date', ["$month-01", date('Y-m-t', strtotime("$month-01"))])->orderBy('work_date')->get();

        return response()->json(['data' => $rows, 'meta' => ['month' => $month, 'employee_id' => $employee->id]]);
    }

    // POST /api/admin/attendance/check-in
    public function checkIn(Request $request): JsonResponse
    {
        $employee = $this->ownEmployee($request->user());
        if (! $employee) {
            return response()->json(['message' => 'Your account is not linked to an employee profile.'], 404);
        }

        $row = EmployeeAttendance::withoutGlobalScopes()->firstOrNew(['employee_id' => $employee->id, 'work_date' => today()->toDateString()]);
        if ($row->check_in_at) {
            return response()->json(['message' => 'You have already checked in today.'], 422);
        }
        $row->fill(['partner_id' => $employee->partner_id, 'check_in_at' => now(), 'status' => 'present', 'source' => 'self', 'created_by' => (string) $request->user()->getKey()])->save();

        return response()->json(['data' => $row->fresh()], 201);
    }

    // POST /api/admin/attendance/check-out
    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->ownEmployee($request->user());
        if (! $employee) {
            return response()->json(['message' => 'Your account is not linked to an employee profile.'], 404);
        }

        $row = EmployeeAttendance::withoutGlobalScopes()->where('employee_id', $employee->id)->whereDate('work_date', today())->first();
        if (! $row || ! $row->check_in_at) {
            return response()->json(['message' => 'You have not checked in today.'], 422);
        }
        if ($row->check_out_at) {
            return response()->json(['message' => 'You have already checked out today.'], 422);
        }
        $row->update(['check_out_at' => now()]);

        return response()->json(['data' => $row->fresh()]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => 'required|integer',
            'work_date' => 'required|date_format:Y-m-d',
            'status' => ['required', Rule::in(array_keys(EmployeeAttendance::STATUSES))],
            'check_in_at' => 'sometimes|nullable|date',
            'check_out_at' => 'sometimes|nullable|date|after_or_equal:check_in_at',
            'note' => 'sometimes|nullable|string|max:500',
        ]);
    }

    private function fields(array $data): array
    {
        return collect($data)->only(['status', 'check_in_at', 'check_out_at', 'note'])->all();
    }

    private function visible(User $user)
    {
        $q = EmployeeAttendance::query()->withoutGlobalScopes();

        return $user->isSuperAdmin() ? $q : $q->where('partner_id', $user->partner_id);
    }

    private function visibleEmployees(User $user)
    {
        $q = Employee::query()->withoutGlobalScopes();

        return $user->isSuperAdmin() ? $q : $q->where('partner_id', $user->partner_id);
    }

    private function ownEmployee(User $user): ?Employee
    {
        return Employee::query()->withoutGlobalScopes()->where('user_id', $user->getKey())->where('status', true)->first();
    }
}
