<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * 5 tài khoản khách hàng test thêm — dùng để test luồng nhiều người (VD: quỹ nhóm góp tiền đặt
 * sân, App\Models\BookingFund — cần ít nhất 2 tài khoản khác nhau: 1 trưởng nhóm + 1 thành viên).
 * Cùng cách đăng nhập/pattern với TestCustomerSeeder (SĐT + mật khẩu, route('login.page') tab
 * "Đăng nhập bằng mật khẩu"). Idempotent (updateOrCreate theo SĐT).
 *
 * Chạy: php artisan db:seed --class=TestGroupCustomersSeeder
 */
class TestGroupCustomersSeeder extends Seeder
{
    public const PASSWORD = 'Test@12345';

    // SĐT dạng UI (khách gõ khi đăng nhập) — form tự chuyển sang "84xxxxxxxxx" trước khi lưu, xem
    // ghi chú App\Services\ZaloOtpService::normalizePhone() tại TestCustomerSeeder.
    public const ACCOUNTS = [
        ['phone_ui' => '0900000101', 'fullname' => 'Thành viên Test 1'],
        ['phone_ui' => '0900000102', 'fullname' => 'Thành viên Test 2'],
        ['phone_ui' => '0900000103', 'fullname' => 'Thành viên Test 3'],
        ['phone_ui' => '0900000104', 'fullname' => 'Thành viên Test 4'],
        ['phone_ui' => '0900000105', 'fullname' => 'Thành viên Test 5'],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $account) {
            $phone = '84' . substr($account['phone_ui'], 1);

            Customer::updateOrCreate(
                ['phone' => $phone],
                [
                    'fullname'          => $account['fullname'],
                    'date_of_birth'     => '2000-01-01',
                    'phone_verified_at' => now(),
                    'status'            => Customer::STATUS_ACTIVE,
                    'password'          => self::PASSWORD,
                ]
            );

            $this->command?->info("TestGroupCustomersSeeder: {$account['fullname']} — SĐT {$account['phone_ui']} / mật khẩu " . self::PASSWORD);
        }
    }
}
