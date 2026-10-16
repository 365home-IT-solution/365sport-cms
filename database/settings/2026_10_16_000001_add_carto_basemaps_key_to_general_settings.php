<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

// Key nền bản đồ CARTO chỉnh được ở admin (trước đây chỉ có ở .env, server không sửa được).
return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('general.carto_basemaps_key')) {
            $this->migrator->add('general.carto_basemaps_key', null);
        }
    }
};
