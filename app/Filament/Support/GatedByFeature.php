<?php

declare(strict_types=1);

namespace App\Filament\Support;

// Ẩn HẲN một Resource khỏi panel (menu + truy cập trực tiếp bằng URL, kể cả super_admin) khi cờ trong config/features.php đang TẮT.
// Dùng: `use GatedByFeature;` + `protected static string $featureFlag = 'tournament';` trong class Resource.
trait GatedByFeature
{
    protected static function featureEnabled(): bool
    {
        return (bool) config('features.' . static::$featureFlag, false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::featureEnabled() && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return static::featureEnabled() && parent::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::featureEnabled() && parent::canViewAny();
    }
}
