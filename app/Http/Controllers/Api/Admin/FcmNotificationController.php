<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\NotificationFcm;
use App\Models\User;
use App\Services\NotificationFcmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Entities\Order;

// Thông báo đẩy do quản trị gửi (màn Filament "Thông báo đẩy"): gửi cho khách được chọn, toàn bộ khách có thiết bị, hoặc khách của một đơn;
// có thể hẹn giờ gửi. Logic gửi dùng chung NotificationFcmService với Filament (CreateNotificationFcm).
class FcmNotificationController extends Controller
{
    // GET /api/admin/fcm-notifications — ?search=, ?sent_for=, ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = NotificationFcm::query()
            ->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%' . trim((string) $request->query('search')) . '%'))
            ->when($request->filled('sent_for'), fn ($q) => $q->where('sent_for', $request->query('sent_for')))
            ->orderByDesc('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        return response()->json($page);
    }

    public function show(string $id): JsonResponse
    {
        $row = NotificationFcm::find($id);

        return $row ? response()->json(['data' => $row]) : response()->json(['message' => 'Notification not found.'], 404);
    }

    // POST /api/admin/fcm-notifications
    // body: title, body, sent_for (users|all|order), customer_ids[] (users), order_id (order), scheduled_at? (Y-m-d H:i, tương lai = hẹn giờ)
    public function store(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:2000',
            'sent_for' => 'required|in:users,all,order',
            'customer_ids' => 'required_if:sent_for,users|array|max:1000',
            'customer_ids.*' => 'string|max:36',
            'order_id' => 'required_if:sent_for,order|integer',
            'scheduled_at' => 'sometimes|nullable|date',
        ]);

        // Gửi cho TOÀN BỘ khách là thao tác của nền tảng, đối tác không được dùng.
        if ($data['sent_for'] === 'all' && ! $actor->isSuperAdmin()) {
            return response()->json(['message' => 'Only the platform can send to all customers.'], 403);
        }

        $order = null;
        if ($data['sent_for'] === 'order') {
            $order = Order::query()->withoutGlobalScopes()->with('customer')->find($data['order_id']);
            if (! $order || (! $actor->isSuperAdmin() && $order->partner_id !== $actor->partner_id)) {
                return response()->json(['message' => 'Order not found.'], 404);
            }
        }

        $scheduledAt = ! empty($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;
        $isScheduled = $scheduledAt && $scheduledAt->isFuture();

        $customerIds = $data['customer_ids'] ?? [];
        if ($data['sent_for'] === 'all') {
            $customerIds = Customer::whereNotNull('token_device')->where('status', Customer::STATUS_ACTIVE)->pluck('id')->all();
        }

        $record = NotificationFcm::create([
            'title' => $data['title'], 'body' => $data['body'], 'type' => 'manual', 'sent_for' => $data['sent_for'],
            'scheduled_at' => $isScheduled ? $scheduledAt : null, 'created_by' => $actor->getKey(),
            'recipient_ids' => $isScheduled ? ($order ? ['order_id' => $order->id] : $customerIds) : null,
        ]);

        if (! $isScheduled) {
            $service = app(NotificationFcmService::class);
            if ($order) {
                if ($order->customer_id && $order->customer?->token_device) {
                    $service->sendToExisting($record, collect([$order->customer]));
                } elseif ($order->device_token) {
                    $service->sendGuestToExisting($record, $order->device_token);
                }
            } else {
                $service->sendToExisting($record, Customer::whereIn('id', $customerIds)->where('status', Customer::STATUS_ACTIVE)->get());
            }
        }

        return response()->json([
            'message' => $isScheduled ? 'Notification scheduled.' : 'Notification sent.',
            'data' => $record->fresh(),
        ], 201);
    }
}
