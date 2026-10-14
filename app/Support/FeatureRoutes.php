<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

// Bản đồ "cờ tính năng (config/features.php) -> các đường dẫn API thuộc tính năng đó". Khi cờ TẮT, middleware BlockDisabledFeatures
// trả 404 cho các đường dẫn này; trình sinh OpenAPI và lệnh audit cũng bỏ qua chúng.
class FeatureRoutes
{
    public const MAP = [
        'settlement' => ['api/admin/partner-settlements*', 'api/admin/reports/commission'],
        'tournament' => ['api/tournaments*', 'api/my-tournaments', 'api/admin/tournaments*'],
        'open_match' => ['api/open-matches*', 'api/admin/open-matches*'],
        'attendance' => ['api/admin/attendance*'],
        'payroll'    => ['api/admin/payroll*', 'api/admin/reports/payroll', 'api/admin/salary-types*', 'api/admin/allowance-types*',
            'api/admin/deduction-types*', 'api/admin/salary-templates*'],
    ];

    /** Cờ đang TẮT mà đường dẫn này thuộc về; null nếu đường dẫn không bị khoá. */
    public static function disabledFlagFor(string $path): ?string
    {
        $path = ltrim($path, '/');
        foreach (self::MAP as $flag => $patterns) {
            if (config("features.{$flag}", false)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $path)) {
                    return $flag;
                }
            }
        }

        return null;
    }
}
