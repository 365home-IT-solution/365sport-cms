<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Camera;
use App\Models\CameraAlert;
use App\Models\CameraSetting;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

// Quét định kỳ (Scheduled Command) các camera có alert_enabled=true, đang trong khung giờ
// "ngoài giờ mở cửa" (alert_start_time..alert_end_time) — gọi Frigate REST /api/events (object
// detection CÓ SẴN của Frigate, chưa được 365home-cms khai thác) tìm sự kiện 'person' mới, tạo
// CameraAlert + báo cho nhân viên phụ trách chi nhánh. Không throw khi Frigate không phản hồi —
// chỉ log, không được làm crash scheduler.
class CameraAlertPollingService
{
    public function __construct(
        private FrigateApiClient $frigate,
    ) {
    }

    public function pollAll(): int
    {
        $now = now('Asia/Ho_Chi_Minh');
        $created = 0;

        $cameras = Camera::where('status', true)->where('alert_enabled', true)->get();

        foreach ($cameras as $camera) {
            if (! $this->isWithinAlertWindow($camera, $now)) {
                continue;
            }

            $created += $this->pollCamera($camera, $now);
        }

        return $created;
    }

    private function isWithinAlertWindow(Camera $camera, \DateTimeInterface $now): bool
    {
        if (! $camera->alert_start_time || ! $camera->alert_end_time) {
            return false;
        }

        $current = $now->format('H:i:s');
        $start = $camera->alert_start_time;
        $end = $camera->alert_end_time;

        // Hỗ trợ khung giờ qua đêm (vd 22:00 -> 06:00).
        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }

        return $current >= $start || $current <= $end;
    }

    private function pollCamera(Camera $camera, \DateTimeInterface $now): int
    {
        $setting = CameraSetting::forPartner($camera->partner_id);
        if (! $setting->isConfigured()) {
            return 0;
        }

        $since = $camera->alert_last_checked_at ?? $now->copy()->subMinutes(10);

        try {
            $events = $this->frigate->events($setting, $camera->frigateCameraName(), 'person', $since);
        } catch (\Throwable $e) {
            Log::warning('CameraAlertPollingService: Frigate không phản hồi', [
                'camera_id' => $camera->id,
                'error'     => $e->getMessage(),
            ]);

            return 0;
        }

        $camera->update(['alert_last_checked_at' => $now]);

        if (! is_array($events) || empty($events)) {
            return 0;
        }

        $created = 0;

        foreach ($events as $event) {
            $eventId = $event['id'] ?? null;
            if (! $eventId) {
                continue;
            }

            $alert = CameraAlert::firstOrCreate(
                ['camera_id' => $camera->id, 'frigate_event_id' => $eventId],
                [
                    'detected_at' => isset($event['start_time']) ? date('Y-m-d H:i:s', (int) $event['start_time']) : $now,
                    'label'       => $event['label'] ?? 'person',
                ]
            );

            if ($alert->wasRecentlyCreated) {
                $created++;
                $this->notifyBranchEmployees($camera, $alert);
            }
        }

        return $created;
    }

    private function notifyBranchEmployees(Camera $camera, CameraAlert $alert): void
    {
        if (! $camera->branch_id) {
            return;
        }

        // Chạy từ scheduler (không có user đăng nhập) nên global scope partner KHÔNG áp dụng —
        // phải tự lọc theo đối tác của camera, nếu không nhân viên đối tác khác cũng nhận cảnh báo.
        $employees = \Modules\Employee\Entities\Employee::withoutGlobalScope('partner')
            ->where('partner_id', $camera->partner_id)
            ->where('status', true)
            ->where(function ($q) use ($camera) {
                $q->where('works_all_branches', true)
                    ->orWhereHas('workBranches', fn ($q2) => $q2->where('categories.id', $camera->branch_id));
            })
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        foreach ($employees as $employee) {
            if (! $employee->user) {
                continue;
            }

            try {
                Notification::make()
                    ->title('Phát hiện người lạ')
                    ->body("Camera \"{$camera->name}\" phát hiện có người ngoài giờ mở cửa.")
                    ->danger()
                    ->icon('heroicon-o-video-camera')
                    ->sendToDatabase($employee->user);
            } catch (\Throwable $e) {
                Log::warning('CameraAlertPollingService: gửi thông báo thất bại', [
                    'employee_id' => $employee->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
