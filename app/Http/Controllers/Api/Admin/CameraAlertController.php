<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CameraAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Employee\Entities\Employee;

/**
 * Lịch sử cảnh báo phát hiện người ngoài giờ mở cửa — do CameraAlertPollingService tạo tự động.
 * GET  /api/admin/camera-alerts?branch_id=&acknowledged=0|1
 * POST /api/admin/camera-alerts/{id}/acknowledge
 */
class CameraAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CameraAlert::query()
            ->whereHas('camera', fn ($q) => $q->visibleTo($request->user()))
            ->with(['camera:id,name,branch_id', 'camera.branch:id,name', 'acknowledgedBy:id,name']);

        if ($request->filled('branch_id')) {
            $query->whereHas('camera', fn ($q) => $q->where('branch_id', $request->input('branch_id')));
        }

        if ($request->has('acknowledged')) {
            $request->boolean('acknowledged')
                ? $query->whereNotNull('acknowledged_at')
                : $query->whereNull('acknowledged_at');
        }

        $alerts = $query->orderByDesc('detected_at')->paginate($request->integer('per_page', 20));

        return response()->json($alerts);
    }

    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $alert = CameraAlert::whereHas('camera', fn ($q) => $q->visibleTo($request->user()))->find($id);
        if (! $alert) {
            return response()->json(['message' => 'Alert not found.'], 404);
        }

        if ($alert->isAcknowledged()) {
            return response()->json(['message' => 'This alert has already been acknowledged.'], 422);
        }

        $employee = Employee::where('user_id', $request->user()->id)->first();

        $alert->update([
            'acknowledged_by' => $employee?->id,
            'acknowledged_at' => now(),
        ]);

        return response()->json(['data' => $alert->fresh(['camera', 'acknowledgedBy'])]);
    }
}
