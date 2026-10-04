<?php

namespace Modules\Coach\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Coach\App\Services\CoachBookingService;
use Modules\Category\Entities\Category;
use Modules\Coach\App\Models\Coach;

class CoachController extends Controller
{
    // ?slots[0][start]=2026-09-30 08:00&slots[0][end]=2026-09-30 09:00 (tuỳ chọn): bỏ các HLV đã có
    // lịch ở sân khác trùng các khung giờ khách đang chọn — cùng quy tắc với trang đặt sân web.
    public function index(Request $request, Category $branch): JsonResponse
    {
        $validated = $request->validate([
            'slots'         => ['sometimes', 'array', 'max:50'],
            'slots.*.start' => ['required_with:slots', 'date'],
            'slots.*.end'   => ['required_with:slots', 'date', 'after:slots.*.start'],
        ]);

        $coaches = Coach::query()
            ->where('category_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $periods = collect($validated['slots'] ?? [])
            ->map(fn ($s) => [Carbon::parse($s['start']), Carbon::parse($s['end'])])
            ->all();
        $busyIds = CoachBookingService::busyCoachIds($coaches->pluck('id')->all(), $periods);

        return response()->json(['data' => $coaches->reject(fn ($c) => in_array((int) $c->id, $busyIds, true))->values()]);
    }

    public function show(Category $branch, Coach $coach): JsonResponse
    {
        abort_unless($coach->category_id === $branch->id && $coach->is_active, 404);

        return response()->json(['data' => $coach]);
    }
}
