<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Team;
use App\Models\TeamMessage;
use App\Services\NotificationFcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

// Chat nhóm + chia sẻ lời mời (link/QR). Chat chỉ dành cho trưởng nhóm và thành viên đã duyệt; xem trước nhóm qua mã là công khai (không lộ SĐT).
class TeamChatController extends Controller
{
    private const MAX_PER_PAGE = 100;

    // GET /api/teams/{code}/messages — ?before_id= (tin cũ hơn), ?after_id= (tin mới hơn, để poll), ?limit=30. Trả theo thứ tự cũ → mới.
    public function messages(Request $request, string $code): JsonResponse
    {
        $team = $this->activeTeam($code);
        $me = $request->user();
        abort_if(! $team->hasMember($me->id), 403, 'Only team members can read the chat.');

        $limit = min(self::MAX_PER_PAGE, max(1, $request->integer('limit', 30)));
        $q = TeamMessage::query()->where('team_id', $team->id)->with('customer:id,fullname');

        if ($request->filled('after_id')) {
            $rows = $q->where('id', '>', $request->integer('after_id'))->orderBy('id')->limit($limit)->get();
        } else {
            $rows = $q->when($request->filled('before_id'), fn ($w) => $w->where('id', '<', $request->integer('before_id')))
                ->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        }

        return response()->json(['data' => $rows->map(fn (TeamMessage $m) => $this->item($m, $me->id))->values()->all()]);
    }

    // POST /api/teams/{code}/messages — body: {body}
    public function send(Request $request, string $code): JsonResponse
    {
        $team = $this->activeTeam($code);
        $me = $request->user();
        abort_if(! $team->hasMember($me->id), 403, 'Only team members can send messages.');

        $data = $request->validate(['body' => 'required|string|max:1000']);
        $text = trim($data['body']);
        if ($text === '') {
            return response()->json(['message' => 'Message is empty.', 'errors' => ['body' => ['The body field is required.']]], 422);
        }

        $msg = TeamMessage::create(['team_id' => $team->id, 'customer_id' => $me->id, 'body' => $text]);
        $msg->setRelation('customer', $me);

        $this->notifyOthers($team, $me, $text);

        return response()->json(['data' => $this->item($msg, $me->id)], 201);
    }

    // GET /api/teams/{code}/share — dữ liệu chia sẻ lời mời: mã nhóm, deep link (cũng là nội dung QR) và câu mời có sẵn. Thành viên nào cũng chia sẻ được.
    public function share(Request $request, string $code): JsonResponse
    {
        $team = $this->activeTeam($code);
        abort_if(! $team->hasMember($request->user()->id), 403, 'Only team members can share this team.');

        $deepLink = rtrim((string) config('app.mobile_scheme', 'sport365'), ':/') . '://teams/' . $team->code;

        return response()->json(['data' => [
            'code' => $team->code, 'name' => $team->name, 'deep_link' => $deepLink, 'qr_payload' => $deepLink,
            'share_text' => "Tham gia nhóm \"{$team->name}\" của mình nhé! Mã nhóm: {$team->code} — mở ứng dụng và nhập mã hoặc bấm {$deepLink}",
        ]]);
    }

    // GET /api/public/teams/{code} — xem trước nhóm từ link mời (chưa cần đăng nhập). Không lộ SĐT hay danh sách thành viên.
    public function preview(string $code): JsonResponse
    {
        $team = $this->activeTeam($code);

        return response()->json(['data' => [
            'code' => $team->code, 'name' => $team->name, 'description' => $team->description,
            'leader_name' => $team->leader?->fullname, 'member_count' => $team->memberCount(),
        ]]);
    }

    private function activeTeam(string $code): Team
    {
        $team = Team::where('code', strtoupper($code))->where('status', Team::STATUS_ACTIVE)->first();
        abort_if(! $team, 404, 'Team not found.');

        return $team;
    }

    private function item(TeamMessage $m, string $viewerId): array
    {
        return [
            'id' => $m->id, 'customer_id' => $m->customer_id, 'name' => $m->customer?->fullname ?: 'Khách hàng', 'body' => $m->body,
            'is_mine' => $m->customer_id === $viewerId, 'created_at' => $m->created_at?->toIso8601String(),
        ];
    }

    // Báo cho các thành viên còn lại (kể cả trưởng nhóm) — lỗi gửi không được làm hỏng tin đã lưu.
    private function notifyOthers(Team $team, Customer $from, string $text): void
    {
        $ids = $team->approvedMembers()->pluck('customer_id')->push($team->leader_customer_id)->unique()->reject(fn ($id) => $id === $from->id);

        foreach (Customer::whereIn('id', $ids)->get() as $to) {
            try {
                app(NotificationFcmService::class)->sendToCustomer($to, "{$team->name}: " . ($from->fullname ?: 'Thành viên'), mb_substr($text, 0, 100), 'booking', ['subtype' => 'team_message', 'team_code' => $team->code]);
            } catch (\Throwable $e) {
                Log::warning('Team chat notification failed: ' . $e->getMessage(), ['team_id' => $team->id]);
            }
        }
    }
}
