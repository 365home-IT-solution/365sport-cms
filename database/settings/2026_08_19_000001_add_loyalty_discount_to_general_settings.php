<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

// Đưa "giảm giá khách quen" (trước đây hard-code const LOYALTY_DISCOUNT_ENABLED trong
// ProductDetail.php) lên admin > Cấu hình web > Cài đặt chung — cho phép bật/tắt và chỉnh % ngay
// trên giao diện, thay vì phải sửa code mỗi lần muốn đổi.
return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('general.loyalty_discount_enabled')) {
            $this->migrator->add('general.loyalty_discount_enabled', true);
        }
        if (! $this->migrator->exists('general.loyalty_discount_rate_2nd_order')) {
            $this->migrator->add('general.loyalty_discount_rate_2nd_order', 10);
        }
        if (! $this->migrator->exists('general.loyalty_discount_rate_3rd_plus')) {
            $this->migrator->add('general.loyalty_discount_rate_3rd_plus', 5);
        }
    }
};
