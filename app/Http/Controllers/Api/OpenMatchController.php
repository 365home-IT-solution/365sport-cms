<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpenMatch;
use App\Models\OpenMatchParticipant;
use App\Services\NotificationFcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Payment\Entities\Order;

// "Tìm bạn chơi" — API dành cho khách hàng đã đăng nhập (auth:sanctum + customer.active, xem
// routes/api.php). Xem ghi chú nghiệp vụ đầy đủ tại migration create_open_matches_table.
class OpenMatchController extends Controller
{
    /**
     * Danh sách kèo đang mở để duyệt/ghép — chỉ status='open', loại trừ kèo của chính khách
     * đang xem (đã làm host) VÀ kèo khách đã tham gia (activeParticipants), vì cả 2 trường hợp
     * đều không còn ý nghĩa "xin ghép thêm" với chính khách đó.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user();

        $query = OpenMatch::query()
            ->where('status', OpenMatch::STATUS_OPEN)
            ->where('host_customer_id', '!=', $customer->id)
            ->whereDoesntHave('activeParticipants', fn ($q) => $q->where('customer_id', $customer->id))
            ->with(['host:id,fullname,skill_level', 'product:id,name', 'category:id,name']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('play_date', $request->date);
        }

        if ($request->filled('skill_level')) {
            $level = $request->skill_level;
            $query->where(function ($q) use ($level) {
                $q->whereNull('min_skill_level')->orWhere('min_skill_level', '<=', $level);
            })->where(function ($q) use ($level) {
                $q->whereNull('max_skill_level')->orWhere('max_skill_level', '>=', $level);
            });
        }

        if ($request->filled('sport_type')) {
            $sportType = $request->sport_type;
            $query->whereHas('product.roomType', fn ($q) => $q->where('sport_type', $sportType));
        }

        $matches = $query->latest()->paginate((int) $request->input('per_page', 15));

        $matches->getCollection()->transform(fn (OpenMatch $m) => $this->transformSummary($m));

        return response()->json($matches);
    }

    /**
     * Host tạo kèo TỪ 1 đơn đã đặt (order_id) — bắt buộc đơn thuộc chính khách, đã thanh toán
     * (paid/deposit — 'pending' chưa chắc chắn giữ được sân), lịch chơi CHƯA diễn ra, và đơn CHƯA
     * từng mở kèo (unique order_id ở tầng DB, kiểm tra trước để trả message rõ ràng thay vì lỗi SQL).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'         => ['required', 'integer', 'exists:orders,id'],
            'players_needed'   => ['required', 'integer', 'min:1', 'max:50'],
            'min_skill_level'  => ['nullable', 'string', 'in:' . implode(',', OpenMatch::SKILL_LEVELS)],
            'max_skill_level'  => ['nullable', 'string', 'in:' . implode(',', OpenMatch::SKILL_LEVELS)],
            'price_per_player' => ['nullable', 'integer', 'min:0'],
            'note'             => ['nullable', 'string', 'max:1000'],
            // Tuỳ chọn — chỉ cần khai nếu muốn tự động tạo BookingFund thu tiền lúc đủ người, xem
            // OpenMatch::maybeCreateBookingFund().
            'bank_bin'            => ['nullable', 'string'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder' => ['nullable', 'string', 'max:150'],
        ]);

        $customer = $request->user();

        /** @var Order $order */
        $order = Order::with('items')->findOrFail($data['order_id']);

        if ($order->customer_id !== $customer->id) {
            return response()->json(['message' => 'This booking does not belong to you.'], 403);
        }

        if (! in_array($order->status, ['paid', 'deposit'], true)) {
            return response()->json(['message' => 'Only a paid or deposited booking can be opened for matching.'], 422);
        }

        $firstItem = $order->items->first();
        $checkin   = $firstItem?->checkin_date;

        if (! $checkin || ! $checkin->isFuture()) {
            return response()->json(['message' => 'This booking has no upcoming play time to open for matching.'], 422);
        }

        if (OpenMatch::where('order_id', $order->id)->exists()) {
            return response()->json(['message' => 'This booking already has an open match.'], 409);
        }

        $match = OpenMatch::create([
            'order_id'            => $order->id,
            'host_customer_id'    => $customer->id,
            'product_id'          => $firstItem?->product_id,
            'category_id'         => $order->category_id,
            'play_date'           => $checkin->toDateString(),
            'start_time'          => $checkin->format('H:i:s'),
            'end_time'            => $order->items->last()?->checkout_date?->format('H:i:s'),
            'players_needed'      => $data['players_needed'],
            'min_skill_level'     => $data['min_skill_level'] ?? null,
            'max_skill_level'     => $data['max_skill_level'] ?? null,
            'price_per_player'    => $data['price_per_player'] ?? null,
            'note'                => $data['note'] ?? null,
            'bank_bin'            => $data['bank_bin'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_account_holder' => $data['bank_account_holder'] ?? null,
        ]);

        return response()->json([
            'message' => 'Open match created.',
            'data'    => $this->transformDetail($match->fresh(['host', 'product', 'category'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $match = OpenMatch::with([
            'host:id,fullname,skill_level',
            'product:id,name',
            'category:id,name',
            'activeParticipants.customer:id,fullname,skill_level',
        ])->find($id);

        if (! $match) {
            return response()->json(['message' => 'Open match not found.'], 404);
        }

        return response()->json(['data' => $this->transformDetail($match)]);
    }

    /**
     * Khách khác xin ghép vào kèo. Tự động join ngay (không cần host duyệt — xem ghi chú migration
     * create_open_match_participants_table). Dùng updateOrCreate để khách rời rồi join lại không
     * đụng unique constraint (open_match_id, customer_id) — chỉ có 1 dòng participant/khách/match,
     * cập nhật lại status='joined' nếu trước đó đã 'left'.
     */
    public function join(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();

        $match = OpenMatch::find($id);

        if (! $match) {
            return response()->json(['message' => 'Open match not found.'], 404);
        }

        if ($match->isHost($customer->id)) {
            return response()->json(['message' => 'You cannot join your own open match.'], 422);
        }

        if ($match->status !== OpenMatch::STATUS_OPEN) {
            return response()->json(['message' => 'This open match is no longer accepting players.'], 422);
        }

        $alreadyJoined = $match->activeParticipants()->where('customer_id', $customer->id)->exists();
        if ($alreadyJoined) {
            return response()->json(['message' => 'You have already joined this open match.'], 409);
        }

        $result = DB::transaction(function () use ($match, $customer) {
            $locked = OpenMatch::query()->lockForUpdate()->findOrFail($match->id);

            if ($locked->status !== OpenMatch::STATUS_OPEN || $locked->players_joined >= $locked->players_needed) {
                return null;
            }

            OpenMatchParticipant::updateOrCreate(
                ['open_match_id' => $locked->id, 'customer_id' => $customer->id],
                ['status' => OpenMatchParticipant::STATUS_JOINED]
            );

            $locked->increment('players_joined');
            $locked->refresh();

            if ($locked->isFull()) {
                $locked->update(['status' => OpenMatch::STATUS_FULL]);
                $locked->maybeCreateBookingFund();
            }

            return $locked;
        });

        if (! $result) {
            return response()->json(['message' => 'This open match just got full. Please try another one.'], 409);
        }

        $host = $result->host;
        if ($host) {
            app(NotificationFcmService::class)->sendToCustomer(
                $host,
                'Có người ghép kèo!',
                "{$customer->fullname} vừa tham gia kèo của bạn.",
                'booking',
                [
                    'subtype'       => 'open_match_joined',
                    'open_match_id' => (string) $result->id,
                ]
            );
        }

        return response()->json([
            'message' => 'Joined the open match.',
            'data'    => $this->transformDetail($result->fresh(['host', 'product', 'category'])),
        ]);
    }

    /**
     * Khách đã tham gia rời kèo — trả lại 1 suất, mở lại status='open' nếu trước đó 'full'. Host
     * KHÔNG dùng route này để huỷ cả kèo (dùng DELETE /open-matches/{id}).
     */
    public function leave(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();

        $match = OpenMatch::find($id);

        if (! $match) {
            return response()->json(['message' => 'Open match not found.'], 404);
        }

        $participant = $match->activeParticipants()->where('customer_id', $customer->id)->first();

        if (! $participant) {
            return response()->json(['message' => 'You have not joined this open match.'], 422);
        }

        DB::transaction(function () use ($match, $participant) {
            $participant->update(['status' => OpenMatchParticipant::STATUS_LEFT]);

            $locked = OpenMatch::query()->lockForUpdate()->findOrFail($match->id);
            $wasFull = $locked->status === OpenMatch::STATUS_FULL;

            $locked->decrement('players_joined');
            if ($locked->players_joined < 0) {
                $locked->update(['players_joined' => 0]);
            }

            if ($wasFull) {
                $locked->update(['status' => OpenMatch::STATUS_OPEN]);
            }
        });

        $match->refresh();

        $host = $match->host;
        if ($host) {
            app(NotificationFcmService::class)->sendToCustomer(
                $host,
                'Có người rời kèo',
                "{$customer->fullname} vừa rời kèo của bạn.",
                'booking',
                [
                    'subtype'       => 'open_match_left',
                    'open_match_id' => (string) $match->id,
                ]
            );
        }

        return response()->json(['message' => 'Left the open match.']);
    }

    /**
     * Host huỷ cả kèo — báo cho TẤT CẢ người đã tham gia (activeParticipants), giống cách
     * BookingFundController::close() thông báo thành viên khi trưởng nhóm đóng quỹ.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();

        $match = OpenMatch::with('activeParticipants.customer')->find($id);

        if (! $match) {
            return response()->json(['message' => 'Open match not found.'], 404);
        }

        if (! $match->isHost($customer->id)) {
            return response()->json(['message' => 'Only the host can cancel this open match.'], 403);
        }

        if ($match->status === OpenMatch::STATUS_CANCELLED) {
            return response()->json(['message' => 'This open match is already cancelled.'], 409);
        }

        $participants = $match->activeParticipants;

        $match->update(['status' => OpenMatch::STATUS_CANCELLED]);

        $notifier = app(NotificationFcmService::class);
        foreach ($participants as $participant) {
            if (! $participant->customer) {
                continue;
            }

            $notifier->sendToCustomer(
                $participant->customer,
                'Kèo đã bị huỷ',
                "Kèo của {$customer->fullname} vừa bị huỷ.",
                'booking',
                [
                    'subtype'       => 'open_match_cancelled',
                    'open_match_id' => (string) $match->id,
                ]
            );
        }

        return response()->json(['message' => 'Open match cancelled.']);
    }

    private function transformSummary(OpenMatch $match): array
    {
        return [
            'id'                => $match->id,
            'host_name'         => $match->host?->fullname,
            'product_id'        => $match->product_id,
            'product_name'      => $match->product?->name,
            'category_id'       => $match->category_id,
            'category_name'     => $match->category?->name,
            'play_date'         => $match->play_date?->toDateString(),
            'start_time'        => $match->start_time,
            'end_time'          => $match->end_time,
            'players_needed'    => $match->players_needed,
            'players_joined'    => $match->players_joined,
            'min_skill_level'   => $match->min_skill_level,
            'max_skill_level'   => $match->max_skill_level,
            'price_per_player'  => $match->price_per_player,
            'note'              => $match->note,
            'status'            => $match->status,
            'created_at'        => $match->created_at?->toIso8601String(),
        ];
    }

    private function transformDetail(OpenMatch $match): array
    {
        $summary = $this->transformSummary($match);

        $summary['host_id']         = $match->host_customer_id;
        $summary['host_skill_level'] = $match->host?->skill_level;
        $summary['booking_fund_id'] = $match->booking_fund_id;
        $summary['participants']    = $match->relationLoaded('activeParticipants')
            ? $match->activeParticipants->map(fn (OpenMatchParticipant $p) => [
                'customer_id'  => $p->customer_id,
                'fullname'     => $p->customer?->fullname,
                'skill_level'  => $p->customer?->skill_level,
                'joined_at'    => $p->created_at?->toIso8601String(),
            ])->values()
            : [];

        return $summary;
    }
}
