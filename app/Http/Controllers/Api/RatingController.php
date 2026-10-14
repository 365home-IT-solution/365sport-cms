<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\RoomRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Payment\Entities\Order;
use Modules\Product\App\Models\Product;

class RatingController extends Controller
{
    public function index(string $roomId): JsonResponse
    {
        $room = Product::where('id', $roomId)->where('is_activated', true)->first();

        if (! $room) {
            return response()->json(['message' => 'Sân không tồn tại.'], 404);
        }

        // Đánh giá bị chủ sân ẩn (vi phạm) không hiển thị công khai và không tính vào thống kê.
        $ratings = RoomRating::where('room_id', $roomId)->visible()
            ->with('customer:id,fullname')
            ->latest()
            ->paginate(10);

        $distribution = RoomRating::where('room_id', $roomId)->visible()
            ->selectRaw('star, COUNT(*) as count')
            ->groupBy('star')
            ->pluck('count', 'star');

        $summary = [
            'average'      => $room->rating_score !== null ? (float) $room->rating_score : null,
            'total_count'  => $ratings->total(),
            'distribution' => [
                5 => (int) ($distribution[5] ?? 0),
                4 => (int) ($distribution[4] ?? 0),
                3 => (int) ($distribution[3] ?? 0),
                2 => (int) ($distribution[2] ?? 0),
                1 => (int) ($distribution[1] ?? 0),
            ],
        ];

        $user = auth('sanctum')->user();
        $myRating = $user
            ? RoomRating::where('customer_id', $user->id)->where('room_id', $roomId)->first()
            : null;

        return response()->json([
            'summary' => $summary,
            'my_rating' => $myRating ? [
                'id'      => $myRating->id,
                'star'    => $myRating->star,
                'comment' => $myRating->comment,
            ] : null,
            'data'    => $ratings->getCollection()->map(fn ($r) => [
                'id'         => $r->id,
                'user_name'  => $r->customer?->fullname ?? 'Ẩn danh',
                'star'       => $r->star,
                'comment'    => $r->comment,
                'reply'      => $r->reply,
                'replied_at' => $r->replied_at?->toISOString(),
                'created_at' => $r->created_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $ratings->currentPage(),
                'last_page'    => $ratings->lastPage(),
                'per_page'     => $ratings->perPage(),
                'total'        => $ratings->total(),
            ],
        ]);
    }

    public function store(Request $request, string $roomId): JsonResponse
    {
        $room = Product::where('id', $roomId)->where('is_activated', true)->first();

        if (! $room) {
            return response()->json(['message' => 'Sân không tồn tại.'], 404);
        }

        $data = $request->validate([
            'star'    => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth('sanctum')->user();

        // Chỉ cho đánh giá nếu khách THẬT SỰ có đơn đã hoàn thành cho đúng sân này — trước đây chỉ
        // yêu cầu đăng nhập, ai cũng review được kể cả chưa từng đặt sân. Cùng danh sách trạng thái
        // "đã hoàn thành" dùng ở ProductDetail::getLoyaltyDiscountRate() (['paid', 'completed']).
        $completedOrder = Order::where('customer_id', $user->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $roomId))
            ->latest('id')
            ->first();

        if (! $completedOrder) {
            return response()->json(['message' => 'Bạn cần có đơn đặt sân đã hoàn thành mới có thể đánh giá.'], 403);
        }

        $existed = RoomRating::where('customer_id', $user->id)
            ->where('room_id', $roomId)
            ->exists();

        $rating = RoomRating::updateOrCreate(
            ['customer_id' => $user->id, 'room_id' => $roomId],
            ['star' => $data['star'], 'comment' => $data['comment'] ?? null, 'order_id' => $completedOrder->id],
        );

        $this->recalcRatingScore($roomId);

        $room->refresh();

        $status = $existed ? 200 : 201;

        return response()->json([
            'rating' => [
                'id'         => $rating->id,
                'star'       => $rating->star,
                'comment'    => $rating->comment,
                'created_at' => $rating->created_at?->toISOString(),
            ],
            'room_rating_score' => $room->rating_score !== null ? (float) $room->rating_score : null,
        ], $status);
    }

    public function destroy(string $roomId): JsonResponse
    {
        $room = Product::where('id', $roomId)->where('is_activated', true)->first();

        if (! $room) {
            return response()->json(['message' => 'Sân không tồn tại.'], 404);
        }

        $user = auth('sanctum')->user();

        $rating = RoomRating::where('customer_id', $user->id)
            ->where('room_id', $roomId)
            ->first();

        if (! $rating) {
            return response()->json(['message' => 'Bạn chưa đánh giá sân này.'], 404);
        }

        $rating->delete();

        $this->recalcRatingScore($roomId);

        return response()->json(['message' => 'Đã xoá đánh giá.']);
    }

    private function recalcRatingScore(string $roomId): void
    {
        RoomRating::recalcRoomScore($roomId);
    }
}
