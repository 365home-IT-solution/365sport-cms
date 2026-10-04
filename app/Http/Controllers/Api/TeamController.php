<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingFund;
use App\Models\BookingFundMember;
use App\Models\Customer;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\NotificationFcmService;
use App\Services\ZaloOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// API "Đội nhóm" cho app — xem ghi chú đầy đủ ở migration create_teams_tables. Cùng khung với quỹ nhóm
// (BookingFundController): trưởng nhóm là Customer tạo nhóm; người khác vào nhóm bằng MÃ NHÓM (xin tham gia ->
// trưởng nhóm duyệt) hoặc được trưởng nhóm MỜI theo SỐ ĐIỆN THOẠI (người được mời đồng ý). Quỹ nhóm gắn vào
// nhóm (booking_funds.team_id) tự lấy thành viên đã duyệt của nhóm.
//
// 3 tab của app: "Đội của bạn" = GET /teams, "Tham gia" = POST /teams/{code}/join + GET /teams/requests,
// "Lời mời" = GET /teams/invitations + POST /teams/{code}/respond.
// Auth: auth:sanctum + customer.active (routes/api.php) — $request->user() luôn là Customer.
class TeamController extends Controller
{
    /** Trần số thành viên (tính cả trưởng nhóm) và số nhóm một khách được làm trưởng nhóm — chống lạm dụng. */
    private const MAX_MEMBERS      = 50;
    private const MAX_LED_TEAMS    = 10;

    // GET /api/teams — các nhóm của mình (làm trưởng nhóm + đã tham gia).
    public function index(Request $request): JsonResponse
    {
        $me = $request->user()->id;

        $teams = Team::where('status', Team::STATUS_ACTIVE)
            ->where(function ($q) use ($me) {
                $q->where('leader_customer_id', $me)
                    ->orWhereHas('members', fn ($m) => $m->where('customer_id', $me)->where('status', TeamMember::STATUS_APPROVED));
            })
            ->with('leader:id,fullname,phone')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Team $t) => $this->summarize($t, $me))
            ->values();

        return response()->json(['data' => $teams]);
    }

    // GET /api/teams/requests — các yêu cầu tham gia MÌNH đã gửi đang chờ trưởng nhóm duyệt (tab "Tham gia").
    public function requests(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->pendingRowsFor($request->user()->id, TeamMember::STATUS_PENDING)]);
    }

    // GET /api/teams/invitations — lời mời vào nhóm gửi cho mình (tab "Lời mời").
    public function invitations(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->pendingRowsFor($request->user()->id, TeamMember::STATUS_INVITED)]);
    }

    // POST /api/teams — tạo nhóm, người tạo là trưởng nhóm.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $me = $request->user();

        $ledCount = Team::where('leader_customer_id', $me->id)->where('status', Team::STATUS_ACTIVE)->count();
        if ($ledCount >= self::MAX_LED_TEAMS) {
            return response()->json(['message' => 'You can lead at most ' . self::MAX_LED_TEAMS . ' active teams.'], 422);
        }

        $team = Team::create([
            'leader_customer_id' => $me->id,
            'name'               => trim($data['name']),
            'description'        => isset($data['description']) ? trim($data['description']) : null,
        ]);

        return response()->json(['message' => 'Team created.', 'data' => $this->summarize($team->load('leader:id,fullname,phone'), $me->id)], 201);
    }

    // GET /api/teams/{code} — nội dung tuỳ vai trò: người ngoài chỉ thấy bản xem trước (tên, trưởng nhóm, số
    // thành viên, trạng thái yêu cầu của mình); thành viên thấy thêm danh sách thành viên + quỹ của nhóm;
    // trưởng nhóm thấy thêm SĐT, yêu cầu chờ duyệt và lời mời đã gửi.
    public function show(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);
        $me   = $request->user()->id;

        $payload = $this->summarize($team, $me);

        if (! $team->hasMember($me)) {
            return response()->json(['data' => $payload]);
        }

        $isLeader = $team->isLeader($me);

        $payload['members'] = collect([[
            'member_id'   => null,
            'customer_id' => $team->leader_customer_id,
            'name'        => $this->nameOf($team->leader),
            'phone'       => $isLeader ? $team->leader?->phone : null,
            'is_leader'   => true,
            'joined_at'   => $team->created_at?->toIso8601String(),
        ]])->concat(
            $team->approvedMembers()->with('customer:id,fullname,phone')->orderBy('decided_at')->get()
                ->map(fn (TeamMember $m) => [
                    'member_id'   => $m->id,
                    'customer_id' => $m->customer_id,
                    'name'        => $this->nameOf($m->customer),
                    'phone'       => $isLeader ? $m->customer?->phone : null,
                    'is_leader'   => false,
                    'joined_at'   => $m->decided_at?->toIso8601String(),
                ])
        )->values();

        $payload['funds'] = $team->funds()->orderByDesc('created_at')->get()->map(fn (BookingFund $f) => [
            'invite_code'      => $f->invite_code,
            'title'            => $f->title,
            'status'           => $f->status,
            'target_amount'    => $f->target_amount,
            'collected_amount' => $f->collectedAmount(),
            'progress_percent' => $f->progressPercent(),
        ])->values();

        if ($isLeader) {
            $payload['pending_requests'] = $this->rowsOf($team, TeamMember::STATUS_PENDING);
            $payload['invited']          = $this->rowsOf($team, TeamMember::STATUS_INVITED);
        }

        return response()->json(['data' => $payload]);
    }

    // PUT /api/teams/{code} — trưởng nhóm đổi tên/mô tả.
    public function update(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);
        $this->authorizeLeader($request, $team);

        $data = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $team->update(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data));

        return response()->json(['message' => 'Team updated.', 'data' => $this->summarize($team->fresh('leader:id,fullname,phone'), $request->user()->id)]);
    }

    // POST /api/teams/{code}/join — xin vào nhóm bằng mã (trưởng nhóm duyệt). Nếu chính mình đang được mời thì
    // coi như đồng ý lời mời luôn. Idempotent: gửi lại không tạo trùng.
    public function join(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);
        $me   = $request->user();

        if ($team->isLeader($me->id)) {
            return response()->json(['message' => 'You are the leader of this team.'], 422);
        }

        $row = $team->members()->where('customer_id', $me->id)->first();

        if ($row?->status === TeamMember::STATUS_APPROVED) {
            return response()->json(['message' => 'You are already in this team.'], 422);
        }

        if ($row?->status === TeamMember::STATUS_REJECTED) {
            return response()->json(['message' => 'The team leader declined your previous request.'], 422);
        }

        if ($row?->status === TeamMember::STATUS_INVITED) {
            return $this->approve($team, $row, 'You joined the team.', $me);
        }

        if ($row?->status === TeamMember::STATUS_PENDING) {
            return response()->json(['message' => 'Your request is already waiting for the leader to approve.', 'data' => ['status' => $row->status]]);
        }

        if ($team->memberCount() >= self::MAX_MEMBERS) {
            return response()->json(['message' => 'This team is full.'], 422);
        }

        // $row === null hoặc STATUS_DECLINED (trước đó chính mình từ chối lời mời) -> tạo/đặt lại yêu cầu.
        TeamMember::updateOrCreate(
            ['team_id' => $team->id, 'customer_id' => $me->id],
            ['status' => TeamMember::STATUS_PENDING, 'invited_by_customer_id' => null, 'requested_at' => now(), 'decided_at' => null]
        );

        $this->notify($team->leader, 'Có người xin vào nhóm', "{$this->nameOf($me)} xin tham gia nhóm \"{$team->name}\".", 'team_join_request', $team);

        return response()->json(['message' => 'Request sent — waiting for the team leader to approve.', 'data' => ['status' => TeamMember::STATUS_PENDING]], 201);
    }

    // DELETE /api/teams/{code}/join — rút lại yêu cầu tham gia mình đã gửi.
    public function cancelRequest(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);

        $deleted = $team->members()
            ->where('customer_id', $request->user()->id)
            ->where('status', TeamMember::STATUS_PENDING)
            ->delete();

        if (! $deleted) {
            return response()->json(['message' => 'You have no pending request for this team.'], 404);
        }

        return response()->json(['message' => 'Request cancelled.']);
    }

    // POST /api/teams/{code}/respond — người được mời trả lời lời mời: decision = accept | decline.
    public function respond(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);
        $me   = $request->user();
        $data = $request->validate(['decision' => ['required', 'in:accept,decline']]);

        $row = $team->members()->where('customer_id', $me->id)->where('status', TeamMember::STATUS_INVITED)->first();
        if (! $row) {
            return response()->json(['message' => 'You have no invitation to this team.'], 404);
        }

        if ($data['decision'] === 'decline') {
            $row->update(['status' => TeamMember::STATUS_DECLINED, 'decided_at' => now()]);

            return response()->json(['message' => 'Invitation declined.', 'data' => ['status' => $row->status]]);
        }

        return $this->approve($team, $row, 'You joined the team.', $me);
    }

    // POST /api/teams/{code}/invite — trưởng nhóm mời một khách THEO SỐ ĐIỆN THOẠI.
    public function invite(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);
        $this->authorizeLeader($request, $team);

        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(\+84|84|0)\d{9}$/'],
        ]);

        $target = Customer::where('phone', app(ZaloOtpService::class)->normalizePhone($data['phone']))
            ->where('status', Customer::STATUS_ACTIVE)
            ->first();

        if (! $target) {
            return response()->json(['message' => 'No account found with this phone number.'], 404);
        }

        if ($team->isLeader($target->id)) {
            return response()->json(['message' => 'You are the leader of this team.'], 422);
        }

        $row = $team->members()->where('customer_id', $target->id)->first();

        if ($row?->status === TeamMember::STATUS_APPROVED) {
            return response()->json(['message' => 'This person is already in the team.'], 422);
        }

        if ($row?->status === TeamMember::STATUS_INVITED) {
            return response()->json(['message' => 'This person has already been invited.', 'data' => ['status' => $row->status]]);
        }

        if ($team->memberCount() >= self::MAX_MEMBERS) {
            return response()->json(['message' => 'This team is full.'], 422);
        }

        // Người này đã xin vào nhóm và trưởng nhóm cũng muốn mời -> hai bên cùng đồng ý, vào nhóm luôn.
        if ($row?->status === TeamMember::STATUS_PENDING) {
            return $this->approve($team, $row, 'This person joined the team.', $request->user());
        }

        // Chưa có dòng, hoặc từng bị từ chối/tự từ chối -> mời lại (trưởng nhóm có quyền mời lại người từng bị từ chối).
        TeamMember::updateOrCreate(
            ['team_id' => $team->id, 'customer_id' => $target->id],
            ['status' => TeamMember::STATUS_INVITED, 'invited_by_customer_id' => $request->user()->id, 'requested_at' => now(), 'decided_at' => null]
        );

        $this->notify($target, 'Lời mời vào nhóm', "{$this->nameOf($request->user())} mời bạn vào nhóm \"{$team->name}\".", 'team_invite', $team);

        return response()->json(['message' => 'Invitation sent.', 'data' => ['status' => TeamMember::STATUS_INVITED]], 201);
    }

    // POST /api/teams/{code}/members/{memberId}/decide — trưởng nhóm duyệt/từ chối yêu cầu xin vào nhóm.
    public function decideMember(Request $request, string $code, int $memberId): JsonResponse
    {
        $team = $this->findActive($code);
        $this->authorizeLeader($request, $team);

        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);

        $row = $team->members()->whereKey($memberId)->first();
        if (! $row) {
            return response()->json(['message' => 'Join request not found.'], 404);
        }
        if ($row->status !== TeamMember::STATUS_PENDING) {
            return response()->json(['message' => 'This request has already been handled.'], 422);
        }

        if ($data['decision'] === 'rejected') {
            $row->update(['status' => TeamMember::STATUS_REJECTED, 'decided_at' => now()]);
            $this->notify($row->customer, 'Yêu cầu vào nhóm bị từ chối', "Trưởng nhóm \"{$team->name}\" đã từ chối yêu cầu của bạn.", 'team_request_rejected', $team);

            return response()->json(['message' => 'Request rejected.', 'data' => ['status' => $row->status]]);
        }

        return $this->approve($team, $row, 'Member approved.', $request->user());
    }

    // DELETE /api/teams/{code}/members/{memberId} — trưởng nhóm xoá thành viên khỏi nhóm / thu hồi lời mời / bỏ yêu cầu.
    public function removeMember(Request $request, string $code, int $memberId): JsonResponse
    {
        $team = $this->findActive($code);
        $this->authorizeLeader($request, $team);

        $row = $team->members()->whereKey($memberId)->first();
        if (! $row) {
            return response()->json(['message' => 'Member not found.'], 404);
        }

        $wasMember = $row->status === TeamMember::STATUS_APPROVED;
        $customerId = $row->customer_id;
        $row->delete();

        if ($wasMember) {
            $this->detachFromOpenFunds($team, $customerId);
        }

        return response()->json(['message' => 'Removed.']);
    }

    // POST /api/teams/{code}/leave — thành viên tự rời nhóm. Trưởng nhóm không rời được (phải giải tán nhóm).
    public function leave(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);
        $me   = $request->user();

        if ($team->isLeader($me->id)) {
            return response()->json(['message' => 'The leader cannot leave the team — disband it instead.'], 422);
        }

        $deleted = $team->members()->where('customer_id', $me->id)->where('status', TeamMember::STATUS_APPROVED)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'You are not a member of this team.'], 404);
        }

        $this->detachFromOpenFunds($team, $me->id);

        return response()->json(['message' => 'You left the team.']);
    }

    // POST /api/teams/{code}/disband — trưởng nhóm giải tán nhóm. Quỹ đã tạo cho nhóm vẫn giữ nguyên (không mất tiền).
    public function disband(Request $request, string $code): JsonResponse
    {
        $team = $this->findActive($code);
        $this->authorizeLeader($request, $team);

        $team->update(['status' => Team::STATUS_DISBANDED, 'disbanded_at' => now()]);

        return response()->json(['message' => 'Team disbanded.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────

    // Chốt một dòng thành viên thành "approved" (duyệt yêu cầu / đồng ý lời mời / hai bên cùng muốn): kiểm tra nhóm
    // chưa đầy, đồng bộ vào các quỹ đang gom của nhóm, báo cho bên còn lại.
    private function approve(Team $team, TeamMember $row, string $message, Customer $actor): JsonResponse
    {
        if ($team->memberCount() >= self::MAX_MEMBERS) {
            return response()->json(['message' => 'This team is full.'], 422);
        }

        DB::transaction(function () use ($team, $row) {
            $row->update(['status' => TeamMember::STATUS_APPROVED, 'decided_at' => now()]);
            $this->attachToOpenFunds($team, $row->customer_id);
        });

        // Báo cho BÊN CÒN LẠI: chính người vào nhóm thực hiện (xin vào rồi được mời / đồng ý lời mời) -> báo trưởng
        // nhóm; trưởng nhóm thực hiện (duyệt yêu cầu / mời người đã xin vào) -> báo người được vào.
        if ($actor->id === $row->customer_id) {
            $this->notify($team->leader, 'Thành viên mới', "{$this->nameOf($row->customer)} đã tham gia nhóm \"{$team->name}\".", 'team_member_joined', $team);
        } else {
            $this->notify($row->customer, 'Đã vào nhóm', "Bạn đã được duyệt vào nhóm \"{$team->name}\".", 'team_request_approved', $team);
        }

        return response()->json(['message' => $message, 'data' => ['status' => TeamMember::STATUS_APPROVED]]);
    }

    // Thành viên mới của nhóm tự vào các quỹ ĐANG GOM của nhóm (trạng thái đã duyệt) để góp được ngay. Không đè
    // lên dòng đã tồn tại (vd trưởng quỹ từng từ chối người đó).
    private function attachToOpenFunds(Team $team, string $customerId): void
    {
        $team->funds()->where('status', 'collecting')->get()->each(function (BookingFund $fund) use ($customerId) {
            if ($fund->isLeader($customerId)) {
                return;
            }

            BookingFundMember::firstOrCreate(
                ['booking_fund_id' => $fund->id, 'customer_id' => $customerId],
                ['status' => 'approved', 'requested_at' => now(), 'decided_at' => now()]
            );
        });
    }

    // Rời/bị xoá khỏi nhóm: gỡ khỏi các quỹ đang gom của nhóm, TRỪ khi đã có khoản góp (giữ để sổ quỹ minh bạch).
    private function detachFromOpenFunds(Team $team, string $customerId): void
    {
        $team->funds()->where('status', 'collecting')->get()->each(function (BookingFund $fund) use ($customerId) {
            if ($fund->contributions()->where('customer_id', $customerId)->exists()) {
                return;
            }

            $fund->members()->where('customer_id', $customerId)->delete();
        });
    }

    private function findActive(string $code): Team
    {
        $team = Team::where('code', strtoupper($code))->where('status', Team::STATUS_ACTIVE)->first();
        abort_if(! $team, 404, 'Team not found.');

        return $team;
    }

    private function authorizeLeader(Request $request, Team $team): void
    {
        abort_if(! $team->isLeader($request->user()->id), 403, 'Only the team leader can do this.');
    }

    private function nameOf(?Customer $customer): string
    {
        return $customer?->fullname ?: ($customer?->phone ?? 'Khách hàng');
    }

    // Bắn thông báo đẩy — lỗi gửi KHÔNG được làm hỏng thao tác chính (đã ghi DB xong).
    private function notify(?Customer $to, string $title, string $body, string $subtype, Team $team): void
    {
        if (! $to) {
            return;
        }

        try {
            app(NotificationFcmService::class)->sendToCustomer($to, $title, $body, 'booking', [
                'subtype'   => $subtype,
                'team_code' => $team->code,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Team notification failed: ' . $e->getMessage(), ['team_id' => $team->id, 'subtype' => $subtype]);
        }
    }

    // Các dòng thành viên ở 1 trạng thái của 1 nhóm (cho trưởng nhóm xem): yêu cầu chờ duyệt / lời mời đã gửi.
    private function rowsOf(Team $team, string $status): array
    {
        return $team->members()->where('status', $status)->with('customer:id,fullname,phone')->orderByDesc('requested_at')->get()
            ->map(fn (TeamMember $m) => [
                'member_id'    => $m->id,
                'customer_id'  => $m->customer_id,
                'name'         => $this->nameOf($m->customer),
                'phone'        => $m->customer?->phone,
                'requested_at' => $m->requested_at?->toIso8601String(),
            ])->values()->all();
    }

    // Yêu cầu đã gửi (pending) hoặc lời mời nhận được (invited) của CHÍNH MÌNH, kèm bản tóm tắt nhóm.
    private function pendingRowsFor(string $customerId, string $status): array
    {
        return TeamMember::where('customer_id', $customerId)
            ->where('status', $status)
            ->whereHas('team', fn ($q) => $q->where('status', Team::STATUS_ACTIVE))
            ->with(['team.leader:id,fullname,phone', 'inviter:id,fullname,phone'])
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn (TeamMember $m) => [
                'member_id'    => $m->id,
                'status'       => $m->status,
                'requested_at' => $m->requested_at?->toIso8601String(),
                'invited_by'   => $status === TeamMember::STATUS_INVITED ? $this->nameOf($m->inviter) : null,
                'team'         => $this->summarize($m->team, $customerId),
            ])->values()->all();
    }

    private function summarize(Team $team, string $viewerId): array
    {
        $isLeader = $team->isLeader($viewerId);
        $row      = $isLeader ? null : $team->members()->where('customer_id', $viewerId)->first();

        return [
            'id'            => $team->id,
            'code'          => $team->code,
            'name'          => $team->name,
            'description'   => $team->description,
            'status'        => $team->status,
            'leader_name'   => $this->nameOf($team->leader),
            'member_count'  => $team->memberCount(),
            'is_leader'     => $isLeader,
            // leader | approved | pending | invited | rejected | declined | null (chưa liên quan)
            'my_status'     => $isLeader ? 'leader' : $row?->status,
            'pending_count' => $isLeader ? $team->members()->where('status', TeamMember::STATUS_PENDING)->count() : null,
            'created_at'    => $team->created_at?->toIso8601String(),
        ];
    }
}
