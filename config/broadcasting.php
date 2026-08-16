<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            // QUAN TRỌNG: broadcast() PHÍA SERVER gọi THẲNG cổng nội bộ Reverb (REVERB_SERVER_HOST/
            // PORT, plain HTTP) — KHÔNG đi qua domain public/proxy WSS như trình duyệt (REVERB_HOST/
            // PORT/SCHEME/PATH ở .env chỉ dành cho CLIENT, xem VITE_REVERB_* + echo-client.js). 2 lý
            // do: (1) server và Reverb luôn cùng máy/mạng nội bộ, không cần vòng qua internet/TLS;
            // (2) từng thử đi qua domain public thật (đúng ý định ban đầu của comment cũ dưới đây,
            // đã xoá) nhưng POST .../apps/{id}/events qua nginx proxy path /reverb/ bị nuốt mất, rơi
            // vào route 404 của chính Laravel thay vì tới Reverb — chưa rõ nguyên nhân sâu xa (nghi
            // proxy_pass/redirect nội bộ của Reverb không biết về tiền tố path), gọi thẳng cổng nội
            // bộ đơn giản và chắc chắn hơn, sẵn tiện nhanh hơn (khỏi qua TLS + nginx round-trip).
            'options' => [
                'host' => env('REVERB_SERVER_HOST', '0.0.0.0') === '0.0.0.0' ? '127.0.0.1' : env('REVERB_SERVER_HOST'),
                'port' => env('REVERB_SERVER_PORT', 8080),
                'scheme' => 'http',
                'useTLS' => false,
            ],
            // Không còn cần thiết từ khi 'options' ở trên đổi sang gọi THẲNG cổng nội bộ (plain
            // HTTP, useTLS=false) — giữ lại phòng khi cần đổi hướng dùng domain public/TLS trở lại
            // (chứng chỉ .test của Herd/Valet là tự ký, cURL PHP phía server sẽ báo lỗi "SSL
            // certificate problem" nếu không tắt verify qua REVERB_VERIFY_SSL=false, CHỈ set ở local
            // — production dùng domain có CA thật, không được tắt xác thực).
            'client_options' => [
                'verify' => filter_var(env('REVERB_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN),
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
