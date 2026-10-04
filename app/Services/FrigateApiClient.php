<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CameraSetting;
use Illuminate\Support\Facades\Http;

// Client HTTP thuần gọi API Frigate thật (kho lưu trữ VOD 24/7 + tạo "sự kiện thủ công" để
// cắt/đánh dấu clip). Tự retry 1 lần khi 401 (cookie hết hạn giữa chừng).
class FrigateApiClient
{
    public function __construct(private FrigateSessionClient $session)
    {
    }

    public function recordingsSummary(CameraSetting $setting, string $cameraName, string $timezone): ?array
    {
        return $this->get($setting, "/api/{$cameraName}/recordings/summary", ['timezone' => $timezone]);
    }

    public function recordings(CameraSetting $setting, string $cameraName, string $after, string $before): ?array
    {
        return $this->get($setting, "/api/{$cameraName}/recordings", ['after' => $after, 'before' => $before]);
    }

    // Danh sách sự kiện Frigate TỰ ĐỘNG phát hiện (object detection có sẵn của Frigate — person/
    // car...) — dùng cho CameraAlertPollingService quét cảnh báo xâm nhập ngoài giờ mở cửa. Đây là
    // phần MỚI so với 365home-cms (chưa từng khai thác API này).
    public function events(CameraSetting $setting, string $cameraName, string $label, \DateTimeInterface $after): ?array
    {
        return $this->get($setting, '/api/events', [
            'camera' => $cameraName,
            'label'  => $label,
            'after'  => $after->getTimestamp(),
            'limit'  => 20,
        ]);
    }

    public function startEvent(CameraSetting $setting, string $cameraName, string $label, array $data = []): ?array
    {
        return $this->post($setting, "/api/events/{$cameraName}/{$label}/create", $data);
    }

    public function endEvent(CameraSetting $setting, string $eventId): bool
    {
        return $this->put($setting, "/api/events/{$eventId}/end");
    }

    public function isHevcPlaylist(CameraSetting $setting, string $cameraName, string $after, string $before): bool
    {
        $url = rtrim($setting->base_url, '/') . "/vod/{$cameraName}/start/{$after}/end/{$before}/master.m3u8";
        $cookie = $this->session->cookieFor($setting);

        try {
            $response = Http::timeout(5)
                ->withHeaders($cookie ? ['Cookie' => $cookie] : [])
                ->get($url);

            $body = $response->body();

            return str_contains($body, 'hev1') || str_contains($body, 'hvc1');
        } catch (\Throwable) {
            return false;
        }
    }

    private function get(CameraSetting $setting, string $path, array $query): ?array
    {
        $cookie = $this->session->cookieFor($setting);
        $url = rtrim($setting->base_url, '/') . $path;

        try {
            $response = Http::timeout(5)->withHeaders($cookie ? ['Cookie' => $cookie] : [])->get($url, $query);

            if ($response->status() === 401) {
                $this->session->forgetCookie($setting->partner_id);
                $cookie = $this->session->cookieFor($setting);
                $response = Http::timeout(5)->withHeaders($cookie ? ['Cookie' => $cookie] : [])->get($url, $query);
            }

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function post(CameraSetting $setting, string $path, array $data): ?array
    {
        $cookie = $this->session->cookieFor($setting);
        $url = rtrim($setting->base_url, '/') . $path;

        try {
            $response = Http::timeout(5)->withHeaders($cookie ? ['Cookie' => $cookie] : [])->post($url, $data);

            return $response->successful() ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function put(CameraSetting $setting, string $path): bool
    {
        $cookie = $this->session->cookieFor($setting);
        $url = rtrim($setting->base_url, '/') . $path;

        try {
            return Http::timeout(5)->withHeaders($cookie ? ['Cookie' => $cookie] : [])->put($url)->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
