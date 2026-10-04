<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Zalo Official Account (OA) Configuration
    |--------------------------------------------------------------------------
    | 
    | Configuration cho Zalo ZNS (Zalo Notification Service)
    | 
    | - app_id: ID ứng dụng Zalo (lấy từ https://developers.zalo.me/)
    | - app_secret: Secret key (dùng làm secret_key trong API)
    | - oauth_url: URL để lấy/refresh access token
    | - api_url: URL gửi ZNS
    | - refresh_token: Token để refresh (hiệu lực 3 tháng)
    | - templates: Các template ID đã tạo trên Zalo OA
    |
    */

    'zalo' => [
        'app_id' => env('ZALO_APP_ID'),
        'app_secret' => env('ZALO_APP_SECRET'),
        'oauth_url' => env('ZALO_OAUTH_URL', 'https://oauth.zaloapp.com/v4/oa/access_token'),
        'api_url' => env('ZALO_API_URL', 'https://business.openapi.zalo.me/message/template'),
        'refresh_token' => env('ZALO_REFRESH_TOKEN'),
        'templates' => [
            'booking_success' => env('ZALO_TEMPLATE_BOOKING_SUCCESS'),
            'booking_reminder' => env('ZALO_TEMPLATE_BOOKING_REMINDER'),
            'booking_cancelled' => env('ZALO_TEMPLATE_BOOKING_CANCELLED'),
        ],
        'timeout' => 30,
        'retry' => [
            'enabled' => true,
            'max_attempts' => 3,
            'delay' => [5, 15, 30],
        ],
    ],

    'telegram' => [
        'bot_token'       => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'         => env('TELEGRAM_CHAT_ID'),
        'deposit_chat_id' => env('TELEGRAM_DEPOSIT_CHAT_ID'), // Nhóm nhận thông báo cọc sân (style=2)
    ],

    'firebase' => [
        'vapid_key' => env('FIREBASE_VAPID_KEY'),
    ],

    'ocr_space' => [
        'api_key' => env('OCR_SPACE_API_KEY'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // Nền bản đồ CARTO Voyager (Modules/BladeThemeV1 — map-page.blade.php + product/search.blade.php,
    // thư viện Leaflet) — trước đây MIỄN PHÍ hoàn toàn, không cần key, nhưng CARTO đã đổi chính sách
    // bắt buộc phải có API key mới trả về tile thật (không key thì mọi ô bản đồ chỉ hiện chữ đè "API
    // KEY REQUIRED" — bug thật đã gặp). Lấy key MIỄN PHÍ (tới 5 triệu request/tháng, không cần tài
    // khoản CARTO, chỉ cần điền email + domain) tại https://carto.com/basemaps/apikey — xem hướng
    // dẫn đầy đủ trong README hoặc hỏi lại nếu cần các bước chi tiết.
    'carto' => [
        'basemaps_key' => env('CARTO_BASEMAPS_API_KEY'),
    ],

    // Đăng nhập Google cho KHÁCH HÀNG (storefront) — client_id/client_secret KHÔNG cấu hình qua
    // .env, nhập ở admin (App\Filament\Pages\GoogleLoginSettings), nạp vào đây lúc boot() bằng
    // App\Models\GoogleOauthConfiguration (xem App\Providers\AppServiceProvider::boot()). KHÔNG
    // dùng cho đăng nhập admin (Filament) — chỉ dùng ở luồng ZaloOtpController::googleRedirect()/
    // googleCallback().
    'google' => [
        'client_id'     => null,
        'client_secret' => null,
        'redirect'      => env('APP_URL') . '/auth/google/callback',
    ],

    // Chỉ cần khi có camera H.265 (HEVC) — dùng để transcode sang H.264/MP4 cho trình duyệt phát
    // được (xem App\Http\Controllers\Api\CameraMediaProxyController). Mặc định dò theo PATH hệ
    // thống nếu không cấu hình.
    'ffmpeg' => [
        'binary' => env('FFMPEG_BINARY', 'ffmpeg'),
    ],

    'websocket' => [
        'url'          => env('WS_SERVER_URL', 'http://localhost:3001'),
        'public_url'   => env('WS_PUBLIC_URL', env('WS_SERVER_URL', 'http://localhost:3001')),
        'internal_key' => env('WS_INTERNAL_KEY', ''),
    ],
];
