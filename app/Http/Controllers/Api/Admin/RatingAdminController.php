<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\RoomRating;
use App\Models\User;
use App\Services\NotificationFcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\App\Models\Product;

// Kiểm duyệt đánh giá sân: ẩn/hiện đánh giá vi phạm và trả lời công khai. Đối tác chỉ thấy đánh giá của các sân thuộc mình.
class RatingAdminController extends Controller
{
    // GET /api/admin/ratings — ?room_id=, ?star=, ?hidden=0|1, ?replied=0|1, ?search= (nội dung/tên khách), ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = $this->visible($request->user())->with(['customer:id,fullname,phone', 'room:id,name'])
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->query('room_id')))
            ->when($request->filled('star'), fn ($q) => $q->where('star', $request->integer('star')))
            ->when($request->filled('hidden'), fn ($q) => $q->where('is_hidden', $request->boolean('hidden')))
            ->when($request->filled('replied'), fn ($q) => $request->boolean('replied') ? $q->whereNotNull('reply') : $q->whereNull('reply'))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . trim((string) $request->query('search')) . '%';
                $q->where(fn ($w) => $w->where('comment', 'like', $s)->orWhereHas('customer', fn ($c) => $c->where('fullname', 'like', $s)));
            })
            ->orderByDesc('id')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $page->through(fn (RoomRating $r) => $this->item($r));

        return response()->json($page);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->with(['customer:id,fullname,phone', 'room:id,name'])->find($id);

        return $row ? response()->json(['data' => $this->item($row)]) : response()->json(['message' => 'Rating not found.'], 404);
    }

    // POST /api/admin/ratings/{id}/reply — body: reply (nội dung trả lời công khai; gửi rỗng để gỡ phản hồi cũ)
    public function reply(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->find($id);
        if (! $row) {
            return response()->json(['message' => 'Rating not found.'], 404);
        }

        $data = $request->validate(['reply' => 'present|nullable|string|max:1000']);
        $reply = isset($data['reply']) ? trim($data['reply']) : '';

        if ($reply === '') {
            $row->update(['reply' => null, 'replied_at' => null, 'replied_by' => null]);

            return response()->json(['data' => $this->item($row->fresh(['customer:id,fullname,phone', 'room:id,name']))]);
        }

        $row->update(['reply' => $reply, 'replied_at' => now(), 'replied_by' => (string) $request->user()->getKey()]);

        if ($row->customer_id && ($customer = Customer::find($row->customer_id))) {
            try {
                app(NotificationFcmService::class)->sendToCustomer($customer, 'Chủ sân đã trả lời đánh giá của bạn', mb_substr($reply, 0, 120), 'manual', ['subtype' => 'rating_replied', 'room_id' => (string) $row->room_id]);
            } catch (\Throwable) {
                // Thông báo lỗi không được làm hỏng việc đã lưu.
            }
        }

        return response()->json(['data' => $this->item($row->fresh(['customer:id,fullname,phone', 'room:id,name']))]);
    }

    // POST /api/admin/ratings/{id}/moderate — body: hidden (bool). Ẩn thì không hiện công khai và không tính vào điểm trung bình của sân.
    public function moderate(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->find($id);
        if (! $row) {
            return response()->json(['message' => 'Rating not found.'], 404);
        }

        $data = $request->validate(['hidden' => 'required|boolean']);
        $row->update(['is_hidden' => (bool) $data['hidden']]);
        RoomRating::recalcRoomScore((string) $row->room_id);

        return response()->json(['data' => $this->item($row->fresh(['customer:id,fullname,phone', 'room:id,name']))]);
    }

    // DELETE /api/admin/ratings/{id}
    public function destroy(Request $request, string $id): JsonResponse
    {
        $row = $this->visible($request->user())->find($id);
        if (! $row) {
            return response()->json(['message' => 'Rating not found.'], 404);
        }

        $roomId = (string) $row->room_id;
        $row->delete();
        RoomRating::recalcRoomScore($roomId);

        return response()->json(['message' => 'Deleted.']);
    }

    private function visible(User $user)
    {
        $q = RoomRating::query();

        return $user->isSuperAdmin() ? $q : $q->whereIn('room_id', Product::query()->withoutGlobalScopes()->where('partner_id', $user->partner_id)->select('id'));
    }

    private function item(RoomRating $r): array
    {
        return [
            'id' => $r->id, 'room_id' => $r->room_id, 'room_name' => $r->room?->name, 'customer_name' => $r->customer?->fullname, 'customer_phone' => $r->customer?->phone,
            'star' => $r->star, 'comment' => $r->comment, 'is_hidden' => (bool) $r->is_hidden, 'reply' => $r->reply,
            'replied_at' => $r->replied_at?->toIso8601String(), 'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
