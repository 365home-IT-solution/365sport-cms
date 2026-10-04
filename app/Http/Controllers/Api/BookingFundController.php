<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingFund;
use App\Models\BookingFundContribution;
use App\Models\BookingFundMember;
use App\Models\BookingFundMessage;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BookingFundChatRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Product\App\Models\Product;

// API cho tính năng "Quỹ nhóm góp tiền đặt sân" (App\Models\BookingFund) — dùng cho app mobile sau
// này, xoá logic y hệt Modules/BladeThemeV1/Livewire/BookingFund/{CreateFund,FundDetail}.php (bản
// web hiện tại) để không lệch nghiệp vụ giữa web và mobile — 2 nơi cùng gọi chung các method trên
// model (BookingFund::isLeader()/collectedAmount()/progressPercent()/slotLabel()/vietQrImageUrl(),
// CustomerPackage-style). Auth qua Sanctum (middleware 'auth:sanctum','customer.active' ở
// routes/api.php, cùng nhóm với ChatController/WaitlistController) — $request->user() luôn là
// Customer đã xác thực, không cần tự kiểm tra lại.
class BookingFundController extends Controller
{
    // GET /api/booking-funds — quỹ đang làm trưởng nhóm + đã tham gia (status=approved).
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $led = BookingFund::where('leader_customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BookingFund $f) => $this->summarize($f, true));

        $joined = BookingFundMember::where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->with('fund')
            ->get()
            ->pluck('fund')
            ->filter()
            ->map(fn (BookingFund $f) => $this->summarize($f, false))
            ->values();

        return response()->json(['data' => ['led' => $led, 'joined' => $joined]]);
    }

    // POST /api/booking-funds — tạo quỹ mới, người tạo tự động là trưởng nhóm.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'               => ['required', 'string', 'min:3', 'max:150'],
            'note'                => ['nullable', 'string', 'max:1000'],
            'target_amount'       => ['required', 'numeric', 'min:1000'],
            'bank_bin'            => ['required', 'string'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_holder' => ['required', 'string', 'max:150'],
            'category_id'         => ['nullable', 'exists:categories,id'],
            'product_id'          => ['nullable', 'exists:products,id'],
            'slot_date'           => ['nullable', 'date'],
            'slot_start_time'     => ['nullable', 'date_format:H:i'],
            'slot_end_time'       => ['nullable', 'date_format:H:i'],
            // Quỹ cho 1 nhóm: lấy sẵn thành viên đã duyệt của nhóm (xem TeamController).
            'team_id'             => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        $team = null;
        if (! empty($data['team_id'])) {
            $team = Team::find($data['team_id']);
            if (! $team || ! $team->isActive() || ! $team->hasMember($request->user()->id)) {
                return response()->json(['message' => 'You can only create a fund for an active team you belong to.'], 422);
            }
        }

        // Sân phải thuộc đúng chi nhánh đã chọn — cùng kiểm tra như CreateFund::create() bản web.
        if (! empty($data['product_id']) && ! empty($data['category_id'])) {
            $belongs = Product::whereKey($data['product_id'])
                ->whereHas('categories', fn ($q) => $q->where('categories.id', $data['category_id']))
                ->exists();
            if (! $belongs) {
                $data['product_id'] = null;
            }
        }

        $fund = BookingFund::create([
            'leader_customer_id'  => $request->user()->id,
            'team_id'             => $team?->id,
            'title'               => $data['title'],
            'note'                => $data['note'] ?? null,
            'target_amount'       => (int) $data['target_amount'],
            'category_id'         => $data['category_id'] ?? null,
            'product_id'          => $data['product_id'] ?? null,
            'slot_date'           => $data['slot_date'] ?? null,
            'slot_start_time'     => $data['slot_start_time'] ?? null,
            'slot_end_time'       => $data['slot_end_time'] ?? null,
            'bank_bin'            => $data['bank_bin'],
            'bank_account_number' => $data['bank_account_number'],
            'bank_account_holder' => strtoupper($data['bank_account_holder']),
        ]);

        // Quỹ của nhóm: trưởng nhóm + mọi thành viên đã duyệt của nhóm (trừ người tạo quỹ) tự vào quỹ ở trạng thái
        // đã duyệt, khỏi phải xin tham gia từng người. Thành viên vào nhóm SAU này cũng được thêm tự động
        // (TeamController::attachToOpenFunds).
        if ($team) {
            $ids = $team->approvedMembers()->pluck('customer_id')->push($team->leader_customer_id)
                ->reject(fn ($id) => $id === $request->user()->id)->unique();
            foreach ($ids as $customerId) {
                BookingFundMember::firstOrCreate(
                    ['booking_fund_id' => $fund->id, 'customer_id' => $customerId],
                    ['status' => 'approved', 'requested_at' => now(), 'decided_at' => now()]
                );
            }
        }

        return response()->json(['data' => $this->summarize($fund, true)], 201);
    }

    // GET /api/booking-funds/{code} — chi tiết đầy đủ, nội dung trả về tuỳ VAI TRÒ người xem, cùng
    // logic phân nhánh với FundDetail::render() bản web.
    public function show(Request $request, string $code): JsonResponse
    {
        $fund = $this->findOrFail($code);
        $customer   = $request->user();
        $isLeader   = $fund->isLeader($customer->id);
        $membership = $fund->members()->where('customer_id', $customer->id)->first();
        $canSeeContributions = $isLeader || ($membership && $membership->status === 'approved');

        $payload = $this->summarize($fund, $isLeader);
        $payload['is_leader'] = $isLeader;
        $payload['membership_status'] = $membership?->status; // null = chưa xin tham gia
        $payload['bank'] = [
            'bank_bin'            => $fund->bank_bin,
            'bank_account_number' => $fund->bank_account_number,
            'bank_account_holder' => $fund->bank_account_holder,
        ];

        if ($membership && $membership->status === 'approved' && $fund->status === 'collecting') {
            $remaining = max(0, $fund->target_amount - $fund->collectedAmount());
            $payload['qr_url'] = $fund->vietQrImageUrl($remaining, $fund->invite_code);
        }

        if ($isLeader) {
            $payload['members'] = $fund->members()->with('customer')->orderByDesc('requested_at')->get()
                ->map(fn (BookingFundMember $m) => [
                    'id'           => $m->id,
                    'customer_id'  => $m->customer_id,
                    'name'         => $m->customer?->fullname ?? $m->customer?->phone ?? 'Khách hàng',
                    'phone'        => $m->customer?->phone,
                    'status'       => $m->status,
                    'requested_at' => $m->requested_at?->toIso8601String(),
                ]);
        }

        if ($canSeeContributions) {
            $payload['contributions'] = $fund->contributions()
                ->with('customer')
                ->when(! $isLeader, fn ($q) => $q->where('customer_id', $customer->id))
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (BookingFundContribution $c) => [
                    'id'            => $c->id,
                    'customer_id'   => $c->customer_id,
                    'name'          => $c->customer?->fullname ?? $c->customer?->phone ?? 'Khách hàng',
                    'amount'        => $c->amount,
                    'receipt_photo_url' => Storage::disk('public')->url($c->receipt_photo),
                    'status'        => $c->status,
                    'created_at'    => $c->created_at?->toIso8601String(),
                ]);
        }

        return response()->json(['data' => $payload]);
    }

    // POST /api/booking-funds/{code}/join — xin tham gia (idempotent, không tạo trùng).
    public function join(Request $request, string $code): JsonResponse
    {
        $fund     = $this->findOrFail($code);
        $customer = $request->user();

        if ($fund->isLeader($customer->id)) {
            return response()->json(['message' => 'Bạn là trưởng nhóm của quỹ này.'], 422);
        }

        if ($fund->status !== 'collecting') {
            return response()->json(['message' => 'Quỹ đã đóng, không nhận thêm thành viên.'], 422);
        }

        $member = BookingFundMember::firstOrCreate(
            ['booking_fund_id' => $fund->id, 'customer_id' => $customer->id],
            ['status' => 'pending', 'requested_at' => now()]
        );

        return response()->json([
            'message' => $member->wasRecentlyCreated ? 'Đã gửi yêu cầu tham gia — chờ trưởng nhóm duyệt.' : 'Bạn đã gửi yêu cầu tham gia trước đó.',
            'data'    => ['status' => $member->status],
        ], $member->wasRecentlyCreated ? 201 : 200);
    }

    // POST /api/booking-funds/{code}/members/{memberId}/decide — trưởng nhóm duyệt/từ chối.
    public function decideMember(Request $request, string $code, int $memberId): JsonResponse
    {
        $fund = $this->findOrFail($code);
        $this->authorizeLeader($request, $fund);

        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);

        $member = $fund->members()->whereKey($memberId)->first();
        if (! $member) {
            return response()->json(['message' => 'Không tìm thấy yêu cầu tham gia.'], 404);
        }

        $member->update(['status' => $data['decision'], 'decided_at' => now()]);

        return response()->json(['message' => 'Đã cập nhật.', 'data' => ['status' => $member->status]]);
    }

    // POST /api/booking-funds/{code}/contributions — thành viên đã duyệt gửi biên lai chuyển
    // khoản (multipart/form-data: amount, receipt_photo).
    public function storeContribution(Request $request, string $code): JsonResponse
    {
        $fund     = $this->findOrFail($code);
        $customer = $request->user();

        if ($fund->status !== 'collecting') {
            return response()->json(['message' => 'Quỹ đã đóng.'], 422);
        }

        $membership = $fund->members()->where('customer_id', $customer->id)->first();
        if (! $membership || $membership->status !== 'approved') {
            return response()->json(['message' => 'Bạn chưa là thành viên được duyệt của quỹ này.'], 403);
        }

        $data = $request->validate([
            'amount'        => ['required', 'numeric', 'min:1000'],
            'receipt_photo' => ['required', 'image', 'max:8192'],
        ]);

        $path = $request->file('receipt_photo')->store('booking-fund-receipts', 'public');

        $contribution = BookingFundContribution::create([
            'booking_fund_id' => $fund->id,
            'customer_id'     => $customer->id,
            'amount'          => (int) $data['amount'],
            'receipt_photo'   => $path,
            'status'          => 'pending',
        ]);

        return response()->json([
            'message' => 'Đã gửi — chờ trưởng nhóm xác nhận.',
            'data'    => ['id' => $contribution->id, 'status' => $contribution->status],
        ], 201);
    }

    // POST /api/booking-funds/{code}/contributions/{id}/decide — trưởng nhóm xác nhận/từ chối.
    public function decideContribution(Request $request, string $code, int $id): JsonResponse
    {
        $fund = $this->findOrFail($code);
        $this->authorizeLeader($request, $fund);

        $data = $request->validate(['decision' => ['required', 'in:confirmed,rejected']]);

        $contribution = $fund->contributions()->whereKey($id)->first();
        if (! $contribution) {
            return response()->json(['message' => 'Không tìm thấy khoản góp.'], 404);
        }
        if ($contribution->status !== 'pending') {
            return response()->json(['message' => 'Khoản góp này đã được xử lý trước đó.'], 422);
        }

        $contribution->update([
            'status'       => $data['decision'],
            'confirmed_at' => $data['decision'] === 'confirmed' ? now() : null,
        ]);

        return response()->json([
            'message' => 'Đã cập nhật.',
            'data'    => ['status' => $contribution->status, 'collected_amount' => $fund->collectedAmount()],
        ]);
    }

    // POST /api/booking-funds/{code}/close — trưởng nhóm đóng quỹ (không nhận thêm thành viên/góp).
    public function close(Request $request, string $code): JsonResponse
    {
        $fund = $this->findOrFail($code);
        $this->authorizeLeader($request, $fund);

        if ($fund->status !== 'collecting') {
            return response()->json(['message' => 'Quỹ đã đóng từ trước.'], 422);
        }

        $fund->update(['status' => 'closed', 'closed_at' => now()]);

        return response()->json(['message' => 'Đã đóng quỹ.']);
    }

    // GET /api/booking-funds/{code}/messages — lịch sử chat (chỉ trưởng nhóm/thành viên đã duyệt).
    public function messages(Request $request, string $code): JsonResponse
    {
        $fund = $this->findOrFail($code);
        $this->authorizeChat($request, $fund);

        $messages = $fund->messages()->with('customer')->orderBy('created_at')->get()
            ->map(fn (BookingFundMessage $m) => $this->formatMessage($m));

        return response()->json(['data' => $messages]);
    }

    // POST /api/booking-funds/{code}/messages — gửi tin nhắn, lưu DB + broadcast realtime (Socket.IO
    // "fundchat:{id}", cùng kênh app web đang dùng — mobile join kênh này để nhận tin trực tiếp,
    // không phải poll GET /messages liên tục).
    public function sendMessage(Request $request, string $code): JsonResponse
    {
        $fund = $this->findOrFail($code);
        $this->authorizeChat($request, $fund);

        $data = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = BookingFundMessage::create([
            'booking_fund_id' => $fund->id,
            'customer_id'     => $request->user()->id,
            'body'            => trim($data['body']),
        ]);
        $message->setRelation('customer', $request->user());

        $formatted = $this->formatMessage($message);

        app(BookingFundChatRealtimeService::class)->broadcastMessage($fund->id, [
            'id'          => $message->id,
            'customer_id' => $message->customer_id,
            'sender_name' => $formatted['sender_name'],
            'body'        => $message->body,
            'created_at'  => $formatted['created_at'],
        ]);

        return response()->json(['data' => $formatted], 201);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────────────
    private function findOrFail(string $code): BookingFund
    {
        $fund = BookingFund::where('invite_code', strtoupper($code))->first();
        abort_if(! $fund, 404, 'Không tìm thấy quỹ nhóm này.');

        return $fund;
    }

    private function authorizeLeader(Request $request, BookingFund $fund): void
    {
        abort_if(! $fund->isLeader($request->user()->id), 403, 'Chỉ trưởng nhóm mới thực hiện được thao tác này.');
    }

    // Chat chỉ mở cho trưởng nhóm + thành viên đã duyệt — cùng canChat() của FundDetail.php.
    private function authorizeChat(Request $request, BookingFund $fund): void
    {
        $customer = $request->user();
        if ($fund->isLeader($customer->id)) {
            return;
        }

        $membership = $fund->members()->where('customer_id', $customer->id)->first();
        abort_if(! $membership || $membership->status !== 'approved', 403, 'Bạn chưa phải thành viên được duyệt của quỹ này.');
    }

    private function formatMessage(BookingFundMessage $m): array
    {
        return [
            'id'          => $m->id,
            'customer_id' => $m->customer_id,
            'sender_name' => $m->customer?->fullname ?? $m->customer?->phone ?? 'Khách hàng',
            'body'        => $m->body,
            'created_at'  => $m->created_at?->toIso8601String(),
        ];
    }

    private function summarize(BookingFund $fund, bool $isLeader): array
    {
        return [
            'id'             => $fund->id,
            'invite_code'    => $fund->invite_code,
            'title'          => $fund->title,
            'note'           => $fund->note,
            'slot_label'     => $fund->slotLabel(),
            'branch_name'    => $fund->category?->name,
            'team_id'        => $fund->team_id,
            'team_name'      => $fund->team?->name,
            'target_amount'  => $fund->target_amount,
            'collected_amount' => $fund->collectedAmount(),
            'progress_percent' => $fund->progressPercent(),
            'status'         => $fund->status,
            'is_leader'      => $isLeader,
            'created_at'     => $fund->created_at?->toIso8601String(),
        ];
    }
}
