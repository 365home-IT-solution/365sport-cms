<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerSettlement;
use App\Models\User;
use App\Services\PartnerSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\App\Services\CommissionSummaryService;

// Đối soát hoa hồng theo tháng. Đối tác CHỈ xem bảng của chính mình; chỉ super_admin lập bảng, điều chỉnh và xác nhận đã thu (các thao tác
// ghi đi qua middleware admin.api nên đối tác — không có quyền tạo/sửa — bị chặn 403; controller vẫn tự kiểm tra thêm cho chắc).
class PartnerSettlementController extends Controller
{
    public function __construct(private readonly PartnerSettlementService $service) {}

    // GET /api/admin/partner-settlements — ?month=YYYY-MM, ?status=pending|paid, ?partner_id= (chỉ super_admin)
    public function index(Request $request): JsonResponse
    {
        $query = $this->visible($request->user())->with('partner:id,name')
            ->when($request->filled('month'), fn ($q) => $q->where('month', $request->query('month')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('partner_id') && $request->user()->isSuperAdmin(), fn ($q) => $q->where('partner_id', $request->query('partner_id')))
            ->orderByDesc('month')->orderBy('partner_id');

        return response()->json($query->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->with('partner:id,name')->find($id);

        return $row ? response()->json(['data' => $row]) : response()->json(['message' => 'Settlement not found.'], 404);
    }

    // POST /api/admin/partner-settlements/generate — body: month (YYYY-MM), partner_id? (bỏ trống = mọi đối tác đã duyệt)
    public function generate(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate(['month' => 'required|date_format:Y-m', 'partner_id' => 'sometimes|nullable|uuid|exists:partners,id']);

        $partners = Partner::query()
            ->when(! empty($data['partner_id']), fn ($q) => $q->whereKey($data['partner_id']), fn ($q) => $q->where('verification_status', 'approved'))
            ->get();

        $created = [];
        $skipped = [];
        foreach ($partners as $partner) {
            $result = $this->service->generate($partner, $data['month'], $request->user());
            if (! $result['ok']) {
                if (! empty($data['partner_id'])) {
                    return response()->json(['message' => $result['message']], 422);
                }
                $skipped[] = ['partner_id' => $partner->id, 'reason' => $result['message']];

                continue;
            }
            $created[] = $result['settlement'];
        }

        return response()->json(['message' => 'Settlements generated.', 'data' => $created, 'skipped' => $skipped], 201);
    }

    // PUT|PATCH /api/admin/partner-settlements/{id} — điều chỉnh tay: adjustment_amount (+/-), adjustment_note
    public function update(Request $request, string $id): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $row = PartnerSettlement::find($id);
        if (! $row) {
            return response()->json(['message' => 'Settlement not found.'], 404);
        }

        $data = $request->validate(['adjustment_amount' => 'required|integer|between:-1000000000,1000000000', 'adjustment_note' => 'sometimes|nullable|string|max:500']);
        $result = $this->service->adjust($row, (int) $data['adjustment_amount'], $data['adjustment_note'] ?? null);

        return $result['ok'] ? response()->json(['data' => $result['settlement']]) : response()->json(['message' => $result['message']], 422);
    }

    // POST /api/admin/partner-settlements/{id}/pay — body: method (cash|transfer), reference?
    public function pay(Request $request, string $id): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $row = PartnerSettlement::find($id);
        if (! $row) {
            return response()->json(['message' => 'Settlement not found.'], 404);
        }

        $data = $request->validate(['method' => 'required|in:cash,transfer', 'reference' => 'sometimes|nullable|string|max:100']);
        $result = $this->service->markPaid($row, $data['method'], $data['reference'] ?? null);

        return $result['ok'] ? response()->json(['message' => 'Settlement marked as paid.', 'data' => $result['settlement']]) : response()->json(['message' => $result['message']], 422);
    }

    // GET /api/admin/reports/commission?month=&year= — hoa hồng tạm tính của tháng (super_admin: mọi đối tác; chủ đối tác: của mình)
    public function commission(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $month = $request->integer('month') ?: null;
        $year = $request->integer('year') ?: null;

        if ($user->isSuperAdmin()) {
            return response()->json(['data' => CommissionSummaryService::getData($month, $year)]);
        }
        // Cùng quyền với màn đối soát: chỉ vai trò được xem hoa hồng (chủ đối tác) mới thấy, nhân viên thường thì không.
        if ($user->partner_id && $user->can('view_any_partner::settlement')) {
            return response()->json(['data' => CommissionSummaryService::getDataForPartner($user->partner_id, $month, $year)]);
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }

    private function visible(User $user)
    {
        $q = PartnerSettlement::query();

        return $user->isSuperAdmin() ? $q : $q->where('partner_id', $user->partner_id);
    }
}
