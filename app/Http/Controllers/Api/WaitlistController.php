<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlotWaitlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\App\Models\Product;

class WaitlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $waitlists = $request->user()
            ->waitlists()
            ->whereNull('notified_at')
            ->with('product:id,name')
            ->latest()
            ->get()
            ->map(fn (SlotWaitlist $w) => [
                'id'           => $w->id,
                'product_id'   => $w->product_id,
                'product_name' => $w->product?->name,
                'date'         => $w->date?->toDateString(),
                'timeslot_id'  => $w->timeslot_id,
                'checkin_date' => $w->checkin_date?->toIso8601String(),
                'checkout_date' => $w->checkout_date?->toIso8601String(),
                'created_at'   => $w->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $waitlists]);
    }

    // Đăng ký "báo khi có chỗ trống" — khách bấm ngay tại UI báo lỗi trùng lịch. Kiểu sân
    // "khung giờ" (Product::styles=1) cần date+timeslot_id; kiểu sân "theo ngày" cần
    // checkin_date/checkout_date — xem cùng cách phân biệt tại
    // Order::broadcastSlotsReleasedByProduct()/notifyWaitlistByProduct().
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'     => ['required', 'string', 'exists:products,id'],
            'date'           => ['required_without:checkin_date', 'nullable', 'date_format:Y-m-d'],
            'timeslot_id'    => ['nullable', 'integer'],
            'checkin_date'   => ['required_without:date', 'nullable', 'date'],
            'checkout_date'  => ['nullable', 'date', 'after:checkin_date'],
        ]);

        $product = Product::findOrFail($request->product_id);
        $user    = $request->user();

        $isTimeslotRoom = (int) $product->styles === 1;

        $query = $user->waitlists()
            ->where('product_id', $product->id)
            ->whereNull('notified_at');

        if ($isTimeslotRoom) {
            $query->where('date', $request->date)->where('timeslot_id', $request->timeslot_id);
        } else {
            $query->where('checkin_date', $request->checkin_date);
        }

        if ($query->exists()) {
            return response()->json(['message' => 'Bạn đã đăng ký báo khi có chỗ trống cho khung này rồi.'], 409);
        }

        $waitlist = $user->waitlists()->create($isTimeslotRoom ? [
            'product_id'  => $product->id,
            'date'        => $request->date,
            'timeslot_id' => $request->timeslot_id,
        ] : [
            'product_id'    => $product->id,
            'checkin_date'  => $request->checkin_date,
            'checkout_date' => $request->checkout_date,
        ]);

        return response()->json([
            'message' => 'Đã đăng ký. Chúng tôi sẽ báo ngay khi có chỗ trống.',
            'id'      => $waitlist->id,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->waitlists()->where('id', $id)->delete();

        if (! $deleted) {
            return response()->json(['message' => 'Không tìm thấy đăng ký.'], 404);
        }

        return response()->json(['message' => 'Đã huỷ đăng ký báo chỗ trống.']);
    }
}
