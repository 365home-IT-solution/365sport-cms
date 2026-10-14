<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Employee\Entities\PayrollPeriod;
use Modules\Employee\Entities\PayrollSlip;

// Bảng lương theo tháng: xem báo cáo (tính mới hoặc ảnh chụp đã chốt), chốt/mở lại kỳ, đánh dấu đã trả từng phiếu. Nghiệp vụ nằm ở
// App\Services\PayrollService — dùng chung với màn Filament "Kỳ lương".
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    // GET /api/admin/reports/payroll?month=YYYY-MM&partner_id= (partner_id chỉ super_admin cần gửi)
    public function report(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isSuperAdmin() && ! $user->can('page_SalaryReport')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate(['month' => 'sometimes|date_format:Y-m', 'partner_id' => 'sometimes|nullable|uuid']);
        $partnerId = $this->partnerId($user, $data['partner_id'] ?? null);
        if ($partnerId === null) {
            return response()->json(['message' => 'partner_id is required.'], 422);
        }

        return response()->json(['data' => $this->payroll->report($partnerId, $data['month'] ?? now()->format('Y-m'))]);
    }

    // GET /api/admin/payroll — các kỳ lương
    public function index(Request $request): JsonResponse
    {
        $query = $this->visible($request->user())->orderByDesc('month');

        return response()->json($query->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    // GET /api/admin/payroll/{id}
    public function show(Request $request, string $id): JsonResponse
    {
        $period = $this->visible($request->user())->with('slips')->find($id);

        return $period ? response()->json(['data' => $period]) : response()->json(['message' => 'Payroll period not found.'], 404);
    }

    // POST /api/admin/payroll/close — body: month (YYYY-MM), partner_id (chỉ super_admin)
    public function close(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['month' => 'required|date_format:Y-m', 'partner_id' => 'sometimes|nullable|uuid']);
        $partnerId = $this->partnerId($user, $data['partner_id'] ?? null);

        $result = $this->payroll->close($partnerId, $data['month'], $user);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => 'Payroll closed.', 'data' => $result['period']], 201);
    }

    // POST /api/admin/payroll/{id}/reopen
    public function reopen(Request $request, string $id): JsonResponse
    {
        $period = $this->visible($request->user())->find($id);
        if (! $period) {
            return response()->json(['message' => 'Payroll period not found.'], 404);
        }

        $result = $this->payroll->reopen($period);

        return $result['ok'] ? response()->json(['message' => 'Payroll reopened.', 'data' => $result['period']]) : response()->json(['message' => $result['message']], 422);
    }

    // POST /api/admin/payroll/slips/{id}/pay — body: method (cash|transfer)
    public function pay(Request $request, string $id): JsonResponse
    {
        $slip = PayrollSlip::query()->whereHas('period', fn ($q) => $this->scope($q, $request->user()))->find($id);
        if (! $slip) {
            return response()->json(['message' => 'Payslip not found.'], 404);
        }
        if ($slip->paid_at) {
            return response()->json(['message' => 'This payslip has already been paid.'], 422);
        }

        $data = $request->validate(['method' => 'required|in:cash,transfer']);
        $this->payroll->markPaid($slip, $data['method']);

        return response()->json(['message' => 'Payslip marked as paid.', 'data' => $slip->fresh()]);
    }

    private function partnerId(User $user, ?string $requested): ?string
    {
        return $user->isSuperAdmin() ? $requested : $user->partner_id;
    }

    private function visible(User $user)
    {
        return $this->scope(PayrollPeriod::query()->withoutGlobalScopes(), $user);
    }

    private function scope($query, User $user)
    {
        return $user->isSuperAdmin() ? $query : $query->where('partner_id', $user->partner_id);
    }
}
