<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

// Cấu hình Zalo OA / ZNS chỉnh được ở admin (trước đây chỉ có ở .env). Secret/token được mã hoá.
return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (['zalo_app_secret', 'zalo_access_token', 'zalo_refresh_token'] as $key) {
            if (! $this->migrator->exists("general.{$key}")) {
                $this->migrator->addEncrypted("general.{$key}", null);
            }
        }

        foreach ([
            'zalo_app_id',
            'zalo_otp_template_id',
            'zalo_template_booking_success',
            'zalo_template_booking_reminder',
            'zalo_template_booking_cancelled',
        ] as $key) {
            if (! $this->migrator->exists("general.{$key}")) {
                $this->migrator->add("general.{$key}", null);
            }
        }
    }
};
