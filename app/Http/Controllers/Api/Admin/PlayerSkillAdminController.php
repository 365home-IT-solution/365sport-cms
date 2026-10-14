<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PlayerSkillLevel;
use App\Models\PlayerSkillLevelLog;
use App\Services\NotificationFcmService;
use App\Services\PlayerSkillService;
use App\Support\SkillLevels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Trình độ người chơi (nhân viên sân): xem danh sách khách tự khai, đánh giá / xác nhận (verified), sửa, xoá, xem lịch sử.
class PlayerSkillAdminController extends Controller
{
    public function __construct(private readonly PlayerSkillService $service) {}

    // GET /api/admin/player-skills/options — thang trình độ
    public function options(): JsonResponse
    {
        return response()->json(['data' => ['levels' => SkillLevels::options(), 'general' => SkillLevels::GENERAL]]);
    }

    // GET /api/admin/player-skills — ?search= (tên/SĐT khách), ?customer_id=, ?sport_type=, ?level=, ?source=self|staff, ?requested=1, ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = PlayerSkillLevel::query()->with('customer:id,fullname,phone')
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->query('customer_id')))
            ->when($request->filled('sport_type'), fn ($q) => $q->where('sport_type', $request->query('sport_type')))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->query('level')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->query('source')))
            ->when($request->boolean('requested'), fn ($q) => $q->whereNotNull('assessment_requested_at'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim((string) $request->query('search')) . '%';
                $q->whereHas('customer', fn ($c) => $c->where('fullname', 'like', $s)->orWhere('phone', 'like', $s));
            })
            ->orderByRaw('assessment_requested_at is null')->orderByDesc('updated_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $page->through(fn (PlayerSkillLevel $r) => $this->item($r));

        return response()->json($page);
    }

    public function show(string $id): JsonResponse
    {
        $row = PlayerSkillLevel::with('customer:id,fullname,phone')->find($id);

        return $row ? response()->json(['data' => $this->item($row)]) : response()->json(['message' => 'Skill level not found.'], 404);
    }

    // POST /api/admin/player-skills — body: customer_id, sport_type? (mặc định general), level, note?
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('create_player::skill::level')) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $data = $request->validate([
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'sport_type'  => ['nullable', 'string', 'max:100'],
            'level'       => ['required', 'string', 'in:' . implode(',', SkillLevels::keys())],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        return $this->assess($request, Customer::findOrFail($data['customer_id']), $data['sport_type'] ?? SkillLevels::GENERAL, $data['level'], $data['note'] ?? null, 201);
    }

    // PUT|PATCH /api/admin/player-skills/{id} — body: level, note?
    public function update(Request $request, string $id): JsonResponse
    {
        $row = PlayerSkillLevel::find($id);
        if (! $row) {
            return response()->json(['message' => 'Skill level not found.'], 404);
        }

        $data = $request->validate([
            'level' => ['required', 'string', 'in:' . implode(',', SkillLevels::keys())],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        return $this->assess($request, Customer::findOrFail($row->customer_id), $row->sport_type, $data['level'], $data['note'] ?? $row->note, 200);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $row = PlayerSkillLevel::find($id);
        if (! $row) {
            return response()->json(['message' => 'Skill level not found.'], 404);
        }

        $this->service->set(Customer::findOrFail($row->customer_id), $row->sport_type, null, PlayerSkillLevel::SOURCE_STAFF, (string) $request->user()->id, 'Removed by staff');

        return response()->json(['message' => 'Skill level removed.']);
    }

    // GET /api/admin/player-skills/{id}/history
    public function history(string $id): JsonResponse
    {
        $row = PlayerSkillLevel::find($id);
        if (! $row) {
            return response()->json(['message' => 'Skill level not found.'], 404);
        }

        $logs = PlayerSkillLevelLog::where('customer_id', $row->customer_id)->where('sport_type', $row->sport_type)->orderByDesc('id')->limit(100)->get()
            ->map(fn ($l) => [
                'from_level' => $l->from_level, 'to_level' => $l->to_level, 'to_level_label' => SkillLevels::label($l->to_level),
                'source' => $l->source, 'changed_by' => $l->changed_by, 'note' => $l->note, 'created_at' => $l->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    private function assess(Request $request, Customer $customer, string $sport, string $level, ?string $note, int $status): JsonResponse
    {
        $row = $this->service->set($customer, $sport, $level, PlayerSkillLevel::SOURCE_STAFF, (string) $request->user()->id, $note);

        try {
            app(NotificationFcmService::class)->sendToCustomer(
                $customer,
                'Trình độ của bạn đã được cập nhật',
                'Nhân viên sân đã xác nhận trình độ ' . ($sport === SkillLevels::GENERAL ? 'chung' : $sport) . ' của bạn: ' . SkillLevels::label($level) . '.',
                'system',
                ['subtype' => 'skill_level', 'sport_type' => $sport, 'level' => $level],
            );
        } catch (\Throwable) {
            // thông báo đẩy lỗi không được làm hỏng thao tác đánh giá
        }

        return response()->json(['message' => 'Skill level saved.', 'data' => $this->item($row->load('customer:id,fullname,phone'))], $status);
    }

    private function item(PlayerSkillLevel $r): array
    {
        return PlayerSkillService::present($r) + [
            'customer'    => $r->customer ? ['id' => $r->customer->id, 'fullname' => $r->customer->fullname, 'phone' => $r->customer->phone] : null,
            'assessed_by' => $r->assessed_by,
        ];
    }
}
