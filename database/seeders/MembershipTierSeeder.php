<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MembershipTier;
use Illuminate\Database\Seeder;

/**
 * 4 hạng thành viên mặc định theo tổng chi tiêu (MembershipService::assignWelcomeTier() gán hạng
 * min_spending = 0 cho khách mới — không có hạng nào thì khách đăng ký không được gán hạng/coupon
 * chào mừng). Admin chỉnh ngưỡng/ưu đãi ở Khách hàng > Hạng thành viên. Chạy lại an toàn.
 */
class MembershipTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['Đồng',      'dong',      '#B45309', 0,         'DONG',  'percentage', 5,  'Hạng khởi đầu cho mọi khách mới'],
            ['Bạc',       'bac',       '#6B7280', 3000000,   'BAC',   'percentage', 7,  'Chi tiêu từ 3 triệu đồng'],
            ['Vàng',      'vang',      '#CA8A04', 10000000,  'VANG',  'percentage', 10, 'Chi tiêu từ 10 triệu đồng'],
            ['Kim cương', 'kim-cuong', '#0891B2', 30000000,  'KCUONG', 'percentage', 15, 'Chi tiêu từ 30 triệu đồng'],
        ];

        foreach ($tiers as $i => [$name, $slug, $color, $min, $prefix, $type, $value, $desc]) {
            MembershipTier::updateOrCreate(['slug' => $slug], [
                'name'                       => $name,
                'description'                => $desc,
                'color'                      => $color,
                'sort_order'                 => $i + 1,
                'min_spending'               => $min,
                'welcome_coupon_prefix'      => $prefix,
                'welcome_coupon_type'        => $type,
                'welcome_coupon_value'       => $value,
                'welcome_coupon_days'        => 30,
                'welcome_coupon_usage_limit' => 1,
                'is_active'                  => true,
            ]);
        }
    }
}
