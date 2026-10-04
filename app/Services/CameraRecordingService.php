<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Camera;
use App\Models\CameraSetting;
use App\Support\CameraMediaToken;

// Lớp mỏng dùng chung giữa API (CameraRecordingController) và Filament (CameraMonitor) — gọi
// FrigateApiClient rồi ký token cho link phát lại, KHÔNG bao giờ trả thẳng URL Frigate ra ngoài.
class CameraRecordingService
{
    public function __construct(private FrigateApiClient $frigate)
    {
    }

    public function recordingsSummary(Camera $camera, string $timezone = 'Asia/Ho_Chi_Minh'): ?array
    {
        $setting = CameraSetting::forPartner($camera->partner_id);
        if (! $setting->isConfigured()) {
            return null;
        }

        return $this->frigate->recordingsSummary($setting, $camera->frigateCameraName(), $timezone);
    }

    public function recordings(Camera $camera, string $after, string $before): ?array
    {
        $setting = CameraSetting::forPartner($camera->partner_id);
        if (! $setting->isConfigured()) {
            return null;
        }

        return $this->frigate->recordings($setting, $camera->frigateCameraName(), $after, $before);
    }

    public function playbackUrl(Camera $camera, string $after, string $before): ?array
    {
        $setting = CameraSetting::forPartner($camera->partner_id);
        if (! $setting->isConfigured()) {
            return null;
        }

        $cameraName = $camera->frigateCameraName();
        $isHevc = $this->frigate->isHevcPlaylist($setting, $cameraName, $after, $before);

        $path = $isHevc
            ? "/vod/{$cameraName}/start/{$after}/end/{$before}/stream.mp4"
            : "/vod/{$cameraName}/start/{$after}/end/{$before}/master.m3u8";

        $token = CameraMediaToken::issue($path, $camera->partner_id, $isHevc);
        $filename = $isHevc ? 'stream.mp4' : 'master.m3u8';

        return [
            'hls_url' => url("/api/camera-media/{$token}/{$filename}"),
            'after'   => $after,
            'before'  => $before,
        ];
    }

    public function startRecording(Camera $camera, string $label = 'manual', int $duration = 300, int $preCapture = 5): ?array
    {
        $setting = CameraSetting::forPartner($camera->partner_id);
        if (! $setting->isConfigured()) {
            return null;
        }

        return $this->frigate->startEvent($setting, $camera->frigateCameraName(), $label, [
            'duration'          => $duration,
            'include_recording' => true,
            'pre_capture'       => $preCapture,
        ]);
    }

    public function stopRecording(Camera $camera, string $eventId): bool
    {
        $setting = CameraSetting::forPartner($camera->partner_id);
        if (! $setting->isConfigured()) {
            return false;
        }

        return $this->frigate->endEvent($setting, $eventId);
    }
}
