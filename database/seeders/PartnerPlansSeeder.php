<?php

namespace Database\Seeders;

use App\Models\PartnerPlan;
use Illuminate\Database\Seeder;

// 3 gói dịch vụ mặc định cho đối tác — khớp đúng nội dung quảng cáo đã dựng ở trang
// /lam-doi-tac (Modules/BladeThemeV1/Resources/views/pages/partner.blade.php). Admin có thể
// sửa giá/số chi nhánh/tính năng của các gói này (hoặc thêm gói mới) qua Filament
// PartnerPlanResource — seeder chỉ tạo dữ liệu KHỞI ĐẦU, không phải nguồn sự thật cố định.
//
// Danh sách khoá tính năng hiện đang được code kiểm tra thật (Partner::hasFeature()):
// - 'promotions'        : PromotionResource (Modules/Promotion) — quản lý khuyến mãi.
// - 'staff_permissions' : UserBranchPermissionResource (Modules/DataPermission) — phân quyền
//                         chi nhánh cho nhân viên.
class PartnerPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Cơ bản',
                'description' => 'Phù hợp sân mới bắt đầu chuyển đổi số.',
                'slug' => 'co-ban',
                'price' => 399000,
                'max_branches' => 1,
                'features' => ['branches', 'products'],
                'sort_order' => 1,
                'is_highlighted' => false,
            ],
            [
                'name' => 'Chuyên nghiệp',
                'description' => 'Cho chuỗi sân đang phát triển, cần công cụ tăng doanh thu.',
                'slug' => 'chuyen-nghiep',
                'price' => 799000,
                'max_branches' => 3,
                'features' => ['branches', 'products', 'promotions', 'orders'],
                'sort_order' => 2,
                'is_highlighted' => true,
            ],
            [
                'name' => 'Doanh nghiệp',
                'description' => 'Cho chuỗi sân quy mô lớn, cần thương hiệu riêng.',
                'slug' => 'doanh-nghiep',
                'price' => 1499000,
                'max_branches' => null,
                'features' => ['branches', 'products', 'promotions', 'orders', 'staff_permissions', 'staff_roles', 'staff_accounts'],
                'sort_order' => 3,
                'is_highlighted' => false,
            ],
        ];

        // firstOrCreate (KHÔNG dùng updateOrCreate) — seeder này có thể chạy lại (vd sau khi thêm
        // cột mới), nhưng KHÔNG được ghi đè features/price/max_branches nếu admin đã tự chỉnh sửa
        // qua trang "Gói đối tác"/"Tính năng theo gói" — chỉ tạo mới nếu gói đó CHƯA từng tồn tại.
        foreach ($plans as $plan) {
            PartnerPlan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
