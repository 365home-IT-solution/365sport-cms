<?php

namespace App\Console\Commands;

use App\Services\CameraAlertPollingService;
use Illuminate\Console\Command;

class PollCameraAlertsCommand extends Command
{
    protected $signature   = 'camera-alerts:poll';
    protected $description = 'Quét Frigate tìm cảnh báo phát hiện người ngoài giờ mở cửa cho các camera đã bật cảnh báo';

    public function handle(CameraAlertPollingService $service): int
    {
        $created = $service->pollAll();

        $this->info("Đã tạo {$created} cảnh báo mới.");

        return self::SUCCESS;
    }
}
