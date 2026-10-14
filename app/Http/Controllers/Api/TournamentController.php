<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use App\Services\TournamentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Giải đấu cho khách: xem danh sách/chi tiết công khai, trưởng nhóm đăng ký đội.
class TournamentController extends Controller
{
    private const PUBLIC_STATUSES = ['open', 'ongoing', 'finished'];

    // GET /api/tournaments — ?status=open|ongoing|finished, ?partner_id=, ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = Tournament::query()->withoutGlobalScopes()->whereIn('status', self::PUBLIC_STATUSES)
            ->when(in_array($request->query('status'), self::PUBLIC_STATUSES, true), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('partner_id'), fn ($q) => $q->where('partner_id', $request->query('partner_id')))
            ->orderByRaw("status = 'open' desc")->orderBy('start_date')->paginate(min(50, max(1, $request->integer('per_page', 10))));

        $page->through(fn (Tournament $t) => $this->summary($t));

        return response()->json($page);
    }

    // GET /api/tournaments/{id} — đội đã duyệt, lịch/kết quả trận và bảng xếp hạng
    public function show(int $id): JsonResponse
    {
        $t = $this->find($id);
        if (! $t) {
            return response()->json(['message' => 'Tournament not found.'], 404);
        }

        $teams = TournamentTeam::query()->with('team:id,name,code')->where('tournament_id', $t->id)->where('status', 'approved')->get()
            ->map(fn ($r) => ['team_id' => $r->team_id, 'name' => $r->team?->name, 'code' => $r->team?->code])->values()->all();
        $matches = TournamentMatch::query()->with(['teamA:id,name', 'teamB:id,name'])->where('tournament_id', $t->id)->orderBy('scheduled_at')->orderBy('id')->get()
            ->map(fn (TournamentMatch $m) => [
                'id' => $m->id, 'round' => $m->round, 'team_a' => $m->teamA?->name, 'team_b' => $m->teamB?->name, 'scheduled_at' => $m->scheduled_at?->toIso8601String(),
                'venue' => $m->venue, 'score_a' => $m->score_a, 'score_b' => $m->score_b, 'status' => $m->status,
            ])->all();

        return response()->json(['data' => $this->summary($t) + ['description' => $t->description, 'teams' => $teams, 'matches' => $matches, 'standings' => app(TournamentService::class)->standings($t)]]);
    }

    // POST /api/tournaments/{id}/register — body: team_code (đội do tôi làm trưởng nhóm)
    public function register(Request $request, int $id): JsonResponse
    {
        $t = $this->find($id);
        if (! $t) {
            return response()->json(['message' => 'Tournament not found.'], 404);
        }

        $data = $request->validate(['team_code' => 'required|string|size:8']);
        $team = Team::where('code', strtoupper($data['team_code']))->first();
        if (! $team) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $result = app(TournamentService::class)->register($t, $team, $request->user());
        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], $result['code']);
        }

        return response()->json(['message' => 'Registration submitted — waiting for the organizer to approve.', 'data' => ['status' => 'pending', 'entry_fee' => $t->entry_fee]], 201);
    }

    // DELETE /api/tournaments/{id}/register/{team_code} — rút đăng ký khi còn chờ duyệt
    public function cancel(Request $request, int $id, string $teamCode): JsonResponse
    {
        $team = Team::where('code', strtoupper($teamCode))->first();
        if (! $team || ! $team->isLeader($request->user()->id)) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        $reg = TournamentTeam::query()->where('tournament_id', $id)->where('team_id', $team->id)->first();
        if (! $reg) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }
        if ($reg->status !== 'pending') {
            return response()->json(['message' => 'Only a pending registration can be withdrawn.'], 422);
        }

        $reg->delete();

        return response()->json(['message' => 'Registration withdrawn.']);
    }

    // GET /api/my-tournaments — đăng ký của các đội tôi làm trưởng nhóm
    public function mine(Request $request): JsonResponse
    {
        $rows = TournamentTeam::query()->with(['tournament' => fn ($q) => $q->withoutGlobalScopes(), 'team:id,name,code'])
            ->whereIn('team_id', Team::where('leader_customer_id', $request->user()->id)->select('id'))->orderByDesc('id')->get();

        return response()->json(['data' => $rows->filter(fn ($r) => $r->tournament)->map(fn ($r) => [
            'tournament_id' => $r->tournament_id, 'title' => $r->tournament->title, 'team_code' => $r->team?->code, 'team_name' => $r->team?->name,
            'status' => $r->status, 'fee_paid' => $r->fee_paid, 'entry_fee' => $r->tournament->entry_fee, 'start_date' => $r->tournament->start_date->toDateString(),
        ])->values()->all()]);
    }

    private function find(int $id): ?Tournament
    {
        return Tournament::query()->withoutGlobalScopes()->whereIn('status', self::PUBLIC_STATUSES)->find($id);
    }

    private function summary(Tournament $t): array
    {
        return [
            'id' => $t->id, 'title' => $t->title, 'location' => $t->location, 'banner' => $t->banner ? asset('storage/' . $t->banner) : null,
            'start_date' => $t->start_date->toDateString(), 'end_date' => $t->end_date->toDateString(), 'registration_deadline' => $t->registration_deadline->toDateString(),
            'max_teams' => $t->max_teams, 'approved_teams' => $t->approvedCount(), 'entry_fee' => $t->entry_fee, 'prize_info' => $t->prize_info,
            'status' => $t->status, 'can_register' => $t->acceptsRegistration(),
        ];
    }
}
