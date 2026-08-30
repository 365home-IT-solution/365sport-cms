<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Tài khoản khách hàng test sẵn — đăng nhập bằng SĐT + mật khẩu (route('login.page'), tab
 * "Đăng nhập bằng mật khẩu") để test nhanh các luồng cần đăng nhập (yêu thích, đặt lịch, tài
 * khoản...) mà không phải qua OTP Zalo thật. Idempotent (updateOrCreate theo SĐT).
 *
 * Chạy: php artisan db:seed --class=TestCustomerSeeder
 */
class TestCustomerSeeder extends Seeder
{
    // Định dạng lưu SĐT trong DB là "84xxxxxxxxx" (không có "0"/"+" ở đầu) — xem
    // App\Services\ZaloOtpService::normalizePhone(). Đăng nhập trên giao diện vẫn gõ dạng thường
    // "0900000099" (form tự chuyển đổi).
    public const PHONE    = '84900000099';
    public const PHONE_UI = '0900000099';
    public const PASSWORD = 'Test@12345';

    public function run(): void
    {
        Customer::updateOrCreate(
            ['phone' => self::PHONE],
            [
                'fullname'          => 'Khách Test',
                'date_of_birth'     => '2000-01-01',
                'phone_verified_at' => now(),
                'status'            => Customer::STATUS_ACTIVE,
                'password'          => self::PASSWORD,
            ]
        );

        $this->command?->info('TestCustomerSeeder: tài khoản test sẵn sàng — SĐT ' . self::PHONE_UI . ' / mật khẩu ' . self::PASSWORD);
    }
}
