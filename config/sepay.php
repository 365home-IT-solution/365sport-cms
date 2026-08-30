<?php

declare(strict_types=1);

// Cấu hình webhook SePay — DÙNG CHUNG 1 tài khoản SePay quản lý nhiều tài khoản ngân hàng của
// nhiều đối tác (đã chốt với người dùng), khác PayOS (mỗi đối tác có bộ khoá riêng qua
// App\Services\PayOSCredentialResolver) — SePay chỉ cần ĐÚNG 1 API key cho toàn nền tảng, vì
// webhook tự phân biệt đối tác nào qua accountNumber trong payload (xem
// SePayWebhookController::resolvePartner()).
return [
    // Lấy ở SePay dashboard > Cấu hình > API/Webhook — SePay gửi header
    // "Authorization: Apikey {api_key}" kèm mỗi request webhook, dùng để xác thực request THẬT SỰ
    // đến từ SePay (không phải request giả mạo gọi thẳng vào endpoint công khai).
    'api_key' => env('SEPAY_API_KEY'),
];
