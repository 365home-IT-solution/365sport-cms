<?php

declare(strict_types=1);

namespace App\Support;

// Token ngắn hạn ký HMAC cho link tải VOD/HLS qua CameraMediaProxyController — secret =
// config('app.key') nên verify được thẳng trong Laravel, không cần chia sẻ khoá ra ngoài.
class CameraMediaToken
{
    public static function issue(string $path, ?string $partnerId, bool $transcode, int $ttlSeconds = 10800): string
    {
        $payload = base64_encode(json_encode([
            'path'       => $path,
            'partner_id' => $partnerId,
            'transcode'  => $transcode,
            'exp'        => now()->addSeconds($ttlSeconds)->timestamp,
        ]));

        $signature = hash_hmac('sha256', $payload, (string) config('app.key'));

        return $payload . '.' . $signature;
    }

    public static function verify(string $token): ?array
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if (! $payload || ! $signature) {
            return null;
        }

        if (! hash_equals(hash_hmac('sha256', $payload, (string) config('app.key')), $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);
        if (! is_array($data) || ($data['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return $data;
    }
}
