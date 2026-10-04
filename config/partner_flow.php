<?php

// LUỒNG ĐỐI TÁC: khách mua gói trên website → (đủ điều kiện) chờ Super Admin duyệt để TẶNG DÙNG THỬ, hoặc thanh toán gói để
// kích hoạt ngay → hệ thống tự tạo tài khoản chủ đối tác và gửi email đăng nhập.
return [
    // Đăng ký lần đầu được TẶNG dùng thử N tháng sau khi Super Admin DUYỆT (tài khoản + mật khẩu gửi qua email lúc duyệt);
    // 0 = tắt, khi đó phải thanh toán gói mới được dùng. Thanh toán trước khi được duyệt cũng kích hoạt luôn.
    'signup_trial_months' => (int) env('PARTNER_SIGNUP_TRIAL_MONTHS', 1),
];
