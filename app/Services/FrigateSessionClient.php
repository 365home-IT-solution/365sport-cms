<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CameraSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// Đăng nhập Frigate bằng user/password (Frigate xác thực bằng cookie phiên, không có API key
// tĩnh) — cache cookie 6 giờ theo partner để không phải đăng nhập lại mỗi request.
class FrigateSessionClient
{
    public function cookieFor(CameraSetting $setting): ?string
    {
        if (! $setting->hasFrigateCredentials()) {
            return null;
        }

        return Cache::remember(
            "frigate_session_cookie:{$setting->partner_id}",
            now()->addHours(6),
            fn () => $this->login($setting)
        );
    }

    public function forgetCookie(string $partnerId): void
    {
        Cache::forget("frigate_session_cookie:{$partnerId}");
    }

    private function login(CameraSetting $setting): ?string
    {
        try {
            $response = Http::timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->asJson()
                ->post(rtrim($setting->base_url, '/') . '/api/login', [
                    'user'     => $setting->username,
                    'password' => $setting->password,
                ]);

            $setCookie = $response->header('Set-Cookie');

            return $setCookie ? explode(';', $setCookie)[0] : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
