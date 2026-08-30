<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

// Chương trình giới thiệu bạn bè: khách giới thiệu (referrer) và khách được giới thiệu (referee)
// đều nhận coupon cá nhân (Modules\Promotion\App\Models\Coupon, customer_id-scoped). Referee nhận
// ngay lúc đăng ký kèm mã giới thiệu hợp lệ; referrer chỉ nhận SAU KHI referee có đơn đầu tiên
// thanh toán thành công (chặn lạm dụng tạo tài khoản ảo) — xem Order::booted() hook 'updated'.
return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('general.referral_enabled')) {
            $this->migrator->add('general.referral_enabled', false);
        }
        if (! $this->migrator->exists('general.referral_reward_referee_percent')) {
            $this->migrator->add('general.referral_reward_referee_percent', 10);
        }
        if (! $this->migrator->exists('general.referral_reward_referrer_percent')) {
            $this->migrator->add('general.referral_reward_referrer_percent', 10);
        }
        if (! $this->migrator->exists('general.referral_reward_expiry_days')) {
            $this->migrator->add('general.referral_reward_expiry_days', 30);
        }
    }
};
