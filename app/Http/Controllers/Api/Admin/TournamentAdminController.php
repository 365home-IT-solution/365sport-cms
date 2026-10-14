<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Quản trị giải đấu: CRUD giải, duyệt đội đăng ký (+ thu lệ phí), xếp trận và nhập tỉ số. Đối tác chỉ thấy giải của mình.
class TournamentAdminController extends Controller
{
    // GET /api/admin/tournaments — ?status=, ?search=, ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = $this->scoped($request)
            ->when(array_key_exists((string) $request->query('status'), Tournament::STATUSES), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%' . trim((string) $request->query('search')) . '%'))
            ->orderByDesc('start_date')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $page->through(fn (Tournament $t) => $this->item($t));

        return response()->json($page);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $t = $this->scoped($request)->find($id);

        return $t ? response()->json(['data' => $this->item($t) + ['standings' => app(TournamentService::class)->standings($t)]]) : $this->missing();
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        // Middleware cho POST rơi về quyền update (dành cho lệnh duyệt/nhập tỉ số), nên tạo giải phải kiểm tra riêng quyền create.
        if (! $user->can('create_tournament')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $this->validated($request, true);
        $partnerId = $user->isSuperAdmin() ? $request->input('partner_id') : $user->partner_id;
        if (! $partnerId) {
            return response()->json(['message' => 'partner_id is required.', 'errors' => ['partner_id' => ['The partner id field is required.']]], 422);
        }

        $t = Tournament::query()->withoutGlobalScopes()->create($data + ['partner_id' => $partnerId]);

        return response()->json(['data' => $this->item($t)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        if (! $t) {
            return $this->missing();
        }

        $t->update($this->validated($request, false));

        return response()->json(['data' => $this->item($t->fresh())]);
    }

    // Chỉ xoá được giải chưa có đội nào được duyệt; giải đã chạy thì huỷ (status=cancelled) thay vì xoá.
    public function destroy(Request $request, int $id): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        if (! $t) {
            return $this->missing();
        }
        if ($t->approvedCount() > 0 || $t->matches()->exists()) {
            return response()->json(['message' => 'This tournament already has approved teams or matches. Cancel it instead of deleting.'], 422);
        }

        $t->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // GET /api/admin/tournaments/{id}/registrations — ?status=pending|approved|rejected
    public function registrations(Request $request, int $id): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        if (! $t) {
            return $this->missing();
        }

        $rows = TournamentTeam::query()->with('team:id,name,code,leader_customer_id', 'team.leader:id,fullname,phone')->where('tournament_id', $t->id)
            ->when(in_array($request->query('status'), ['pending', 'approved', 'rejected'], true), fn ($q) => $q->where('status', $request->query('status')))
            ->orderBy('id')->get();

        return response()->json(['data' => $rows->map(fn ($r) => [
            'id' => $r->id, 'team_id' => $r->team_id, 'team_name' => $r->team?->name, 'team_code' => $r->team?->code, 'leader_name' => $r->team?->leader?->fullname,
            'leader_phone' => $r->team?->leader?->phone, 'status' => $r->status, 'fee_paid' => $r->fee_paid, 'note' => $r->note, 'created_at' => $r->created_at?->toIso8601String(),
        ])->all()]);
    }

    // POST /api/admin/tournaments/{id}/registrations/{regId}/decide — body: decision (approved|rejected), fee_paid?, note?
    public function decide(Request $request, int $id, int $regId): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        $reg = $t ? TournamentTeam::query()->where('tournament_id', $t->id)->find($regId) : null;
        if (! $reg) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'fee_paid' => 'nullable|boolean', 'note' => 'nullable|string|max:255']);
        $result = app(TournamentService::class)->decide($reg, $data['decision'], array_key_exists('fee_paid', $data) ? $data['fee_paid'] : null);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }
        if (array_key_exists('note', $data)) {
            $reg->update(['note' => $data['note']]);
        }

        return response()->json(['data' => ['id' => $reg->id, 'status' => $reg->fresh()->status, 'fee_paid' => $reg->fresh()->fee_paid]]);
    }

    // GET /api/admin/tournaments/{id}/matches
    public function matches(Request $request, int $id): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        if (! $t) {
            return $this->missing();
        }

        return response()->json(['data' => $t->matches()->with(['teamA:id,name', 'teamB:id,name'])->orderBy('scheduled_at')->orderBy('id')->get()->map(fn (TournamentMatch $m) => $this->match($m))->all()]);
    }

    // POST /api/admin/tournaments/{id}/matches — team_a_id, team_b_id, round?, scheduled_at?, venue?, score_a?, score_b?
    public function storeMatch(Request $request, int $id): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        if (! $t) {
            return $this->missing();
        }

        $result = app(TournamentService::class)->saveMatch($t, $request->validate($this->matchRules(true)));

        return $result['ok'] ? response()->json(['data' => $this->match($result['match']->load('teamA:id,name', 'teamB:id,name'))], 201) : response()->json(['message' => $result['message']], 422);
    }

    public function updateMatch(Request $request, int $id, int $matchId): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        $m = $t ? $t->matches()->find($matchId) : null;
        if (! $m) {
            return response()->json(['message' => 'Match not found.'], 404);
        }

        $result = app(TournamentService::class)->saveMatch($t, $request->validate($this->matchRules(false)), $m);

        return $result['ok'] ? response()->json(['data' => $this->match($m->fresh(['teamA:id,name', 'teamB:id,name']))]) : response()->json(['message' => $result['message']], 422);
    }

    public function destroyMatch(Request $request, int $id, int $matchId): JsonResponse
    {
        $t = $this->scoped($request)->find($id);
        $m = $t ? $t->matches()->find($matchId) : null;
        if (! $m) {
            return response()->json(['message' => 'Match not found.'], 404);
        }

        $m->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    private function matchRules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';

        return [
            'team_a_id' => "$req|integer|exists:teams,id", 'team_b_id' => "$req|integer|exists:teams,id", 'round' => 'nullable|string|max:50',
            'scheduled_at' => 'nullable|date', 'venue' => 'nullable|string|max:255', 'score_a' => 'nullable|integer|min:0|max:999', 'score_b' => 'nullable|integer|min:0|max:999',
        ];
    }

    private function validated(Request $request, bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';
        $data = $request->validate([
            'title' => "$req|string|max:150", 'description' => 'nullable|string|max:10000', 'location' => 'nullable|string|max:255',
            'start_date' => "$req|date", 'end_date' => "$req|date|after_or_equal:start_date", 'registration_deadline' => "$req|date|before_or_equal:start_date",
            'max_teams' => 'sometimes|integer|min:2|max:256', 'entry_fee' => 'sometimes|integer|min:0|max:100000000', 'prize_info' => 'nullable|string|max:500',
            'status' => ['sometimes', Rule::in(array_keys(Tournament::STATUSES))],
        ]);

        return array_map(fn ($v) => is_string($v) ? strip_tags($v) : $v, $data);
    }

    private function scoped(Request $request)
    {
        $user = $request->user();
        $q = Tournament::query()->withoutGlobalScopes();

        return $user->isSuperAdmin() ? $q : $q->where('partner_id', $user->partner_id);
    }

    private function missing(): JsonResponse
    {
        return response()->json(['message' => 'Tournament not found.'], 404);
    }

    private function item(Tournament $t): array
    {
        return [
            'id' => $t->id, 'partner_id' => $t->partner_id, 'title' => $t->title, 'description' => $t->description, 'location' => $t->location,
            'start_date' => $t->start_date->toDateString(), 'end_date' => $t->end_date->toDateString(), 'registration_deadline' => $t->registration_deadline->toDateString(),
            'max_teams' => $t->max_teams, 'approved_teams' => $t->approvedCount(), 'entry_fee' => $t->entry_fee, 'prize_info' => $t->prize_info, 'status' => $t->status,
        ];
    }

    private function match(TournamentMatch $m): array
    {
        return [
            'id' => $m->id, 'round' => $m->round, 'team_a_id' => $m->team_a_id, 'team_a' => $m->teamA?->name, 'team_b_id' => $m->team_b_id, 'team_b' => $m->teamB?->name,
            'scheduled_at' => $m->scheduled_at?->toIso8601String(), 'venue' => $m->venue, 'score_a' => $m->score_a, 'score_b' => $m->score_b, 'status' => $m->status,
        ];
    }
}
