<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Đẩy tin nhắn chat nhóm quỹ tới Node WS service (websocket/server.js, kênh "fundchat:{id}") —
// cùng pattern App\Services\ChatRealtimeService (chat hỗ trợ khách hàng 1-1), tách riêng vì khác
// domain (chat NHIỀU người trong 1 quỹ, không phải 1 khách với 1 admin).
class BookingFundChatRealtimeService
{
    public function broadcastMessage(int $fundId, array $message): void
    {
        $this->post('/internal/fund-chat-message', [
            'fund_id' => $fundId,
            'message' => $message,
        ]);
    }

    private function post(string $path, array $payload): void
    {
        $url = rtrim(config('services.websocket.url', 'http://localhost:3001'), '/');
        $key = config('services.websocket.internal_key', '');

        if (empty($url)) {
            return;
        }

        try {
            Http::withHeaders(['x-internal-key' => $key])
                ->timeout(2)
                ->post("{$url}{$path}", $payload);
        } catch (\Throwable $e) {
            Log::warning("WS fund-chat push failed [{$path}]", [
                'fund_id' => $payload['fund_id'] ?? null,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
