<?php

namespace Database\Seeders;

use App\Filament\Resources\PartnerPlanResource;
use App\Models\PartnerPlan;
use Illuminate\Database\Seeder;

// DUY NHẤT 1 gói dịch vụ cho đối tác: 199.000đ/tháng, dùng TOÀN BỘ tính năng, không giới hạn chi nhánh. Super admin bật/tắt từng
// tính năng của gói (và đổi giá) ở Admin → Gói đối tác — seeder chỉ tạo dữ liệu KHỞI ĐẦU, không ghi đè nếu gói đã tồn tại.
//
// Trang landing subdomain KHÔNG gồm sẵn: bán THÊM kèm gói — nhập "Giá bán thêm trang landing" ở Gói đối tác (để trống = chưa bán),
// hoặc tick tính năng "Trang landing riêng" vào gói để tặng kèm. Giảm giá theo kỳ (period_discounts) và dùng thử riêng
// (trial_months) cũng chỉnh ở đó.
class PartnerPlansSeeder extends Seeder
{
    public function run(): void
    {
        $features = array_values(array_diff(array_keys(PartnerPlanResource::FEATURE_OPTIONS), ['landing_page']));

        PartnerPlan::firstOrCreate(['slug' => 'day-du'], [
            'name'           => 'Đầy đủ tính năng',
            'description'    => 'Một gói duy nhất — dùng toàn bộ tính năng quản lý sân.',
            'price'          => 199000,
            'max_branches'   => null,
            'features'       => $features,
            'sort_order'     => 1,
            'is_highlighted' => true,
            'is_default'     => true,
            'is_active'      => true,
        ]);
    }
}
