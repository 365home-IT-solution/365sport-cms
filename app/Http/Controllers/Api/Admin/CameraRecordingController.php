<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use App\Services\CameraRecordingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lịch sử ghi hình (VOD) từ Frigate cho 1 camera — xem CameraRecordingService (không bao giờ trả
 * thẳng URL Frigate, luôn ký token ngắn hạn qua CameraMediaProxyController).
 */
class CameraRecordingController extends Controller
{
    public function __construct(private CameraRecordingService $recordings)
    {
    }

    public function summary(Request $request, int $id): JsonResponse
    {
        $camera = Camera::visibleTo($request->user())->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        $summary = $this->recordings->recordingsSummary($camera, $request->input('timezone', 'Asia/Ho_Chi_Minh'));
        if ($summary === null) {
            return response()->json(['message' => 'Camera server (go2rtc/Frigate) is not configured or not reachable.'], 422);
        }

        return response()->json(['data' => $summary]);
    }

    public function recordings(Request $request, int $id): JsonResponse
    {
        $request->validate(['after' => ['required', 'numeric'], 'before' => ['required', 'numeric', 'gt:after']]);

        $camera = Camera::visibleTo($request->user())->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        $recordings = $this->recordings->recordings($camera, $request->input('after'), $request->input('before'));
        if ($recordings === null) {
            return response()->json(['message' => 'Camera server (go2rtc/Frigate) is not configured or not reachable.'], 422);
        }

        return response()->json(['data' => $recordings]);
    }

    public function playbackUrl(Request $request, int $id): JsonResponse
    {
        $request->validate(['after' => ['required', 'numeric'], 'before' => ['required', 'numeric', 'gt:after']]);

        $camera = Camera::visibleTo($request->user())->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        $result = $this->recordings->playbackUrl($camera, $request->input('after'), $request->input('before'));
        if ($result === null) {
            return response()->json(['message' => 'Camera server (go2rtc/Frigate) is not configured or not reachable.'], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function startRecording(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'label'       => ['nullable', 'string', 'max:100'],
            'duration'    => ['nullable', 'integer', 'min:10', 'max:3600'],
            'pre_capture' => ['nullable', 'integer', 'min:0', 'max:60'],
        ]);

        $camera = Camera::visibleTo($request->user())->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        $result = $this->recordings->startRecording(
            $camera,
            $data['label'] ?? 'manual',
            $data['duration'] ?? 300,
            $data['pre_capture'] ?? 5,
        );

        if ($result === null) {
            return response()->json(['message' => 'Camera server (go2rtc/Frigate) is not configured or not reachable.'], 422);
        }

        return response()->json(['data' => $result]);
    }

    public function stopRecording(Request $request, int $id, string $eventId): JsonResponse
    {
        $camera = Camera::visibleTo($request->user())->find($id);
        if (! $camera) {
            return response()->json(['message' => 'Camera not found.'], 404);
        }

        $ok = $this->recordings->stopRecording($camera, $eventId);

        return $ok
            ? response()->json(['message' => 'Recording stopped.'])
            : response()->json(['message' => 'Camera server (go2rtc/Frigate) is not configured or not reachable.'], 422);
    }
}
