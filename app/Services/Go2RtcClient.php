<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CameraSetting;
use Illuminate\Support\Facades\Http;

// Client HTTP thuần gọi API chuẩn go2rtc — go2rtc là phần mềm NGOÀI dự án (server/NVR riêng do
// đối tác tự cài, kết nối camera IP thật qua RTSP), lớp này chỉ khai báo/xoá nguồn stream. Không
// throw exception — lỗi kết nối go2rtc không được chặn việc lưu Camera vào DB (best-effort).
class Go2RtcClient
{
    public function addStream(string $streamKey, string $rtspUrl, ?string $partnerId): ?string
    {
        $setting = CameraSetting::forPartner($partnerId);
        if (! $setting->isConfigured()) {
            return 'go2rtc chưa được cấu hình cho đối tác này.';
        }

        try {
            $this->client($setting)->put(rtrim($setting->base_url, '/') . '/api/streams', [], [
                'query' => ['name' => $streamKey, 'src' => $rtspUrl],
            ]);

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function deleteStream(string $streamKey, ?string $partnerId): ?string
    {
        $setting = CameraSetting::forPartner($partnerId);
        if (! $setting->isConfigured()) {
            return null;
        }

        try {
            $this->client($setting)->delete(rtrim($setting->base_url, '/') . '/api/streams', [
                'src' => $streamKey,
            ]);

            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function client(CameraSetting $setting)
    {
        $client = Http::timeout(5);

        if (filled($setting->api_key)) {
            $client = $client->withToken($setting->api_key);
        }

        return $client;
    }
}
