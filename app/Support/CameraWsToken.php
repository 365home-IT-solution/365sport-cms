<?php

declare(strict_types=1);

namespace App\Support;

// Token ngắn hạn ký HMAC cho URL WebSocket camera-proxy (Node server) — payload base64 JSON +
// chữ ký, secret dùng chung với Node (services.websocket.internal_key).
class CameraWsToken
{
    public static function issue(string $streamKey, string $baseUrl, ?string $partnerId, int $ttlSeconds = 60): string
    {
        $payload = base64_encode(json_encode([
            'stream_key' => $streamKey,
            'base_url'   => $baseUrl,
            'partner_id' => $partnerId,
            'exp'        => now()->addSeconds($ttlSeconds)->timestamp,
        ]));

        $signature = hash_hmac('sha256', $payload, self::secret());

        return $payload . '.' . $signature;
    }

    public static function verify(string $token): ?array
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if (! $payload || ! $signature) {
            return null;
        }

        if (! hash_equals(hash_hmac('sha256', $payload, self::secret()), $signature)) {
            return null;
        }

        $data = json_decode(base64_decode($payload), true);
        if (! is_array($data) || ($data['exp'] ?? 0) < now()->timestamp) {
            return null;
        }

        return $data;
    }

    private static function secret(): string
    {
        return (string) config('services.websocket.internal_key');
    }
}
