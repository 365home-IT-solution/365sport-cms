<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BusinessSeeder extends Seeder
{
    // Thông tin doanh nghiệp/chi nhánh mẫu của nền tảng 365Sport (dữ liệu giả để phát triển, sửa ở
    // Cài đặt > Doanh nghiệp). Chạy lại an toàn (insertOrIgnore theo id).
    public function run(): void
    {
        DB::table('businesses')->insertOrIgnore([
            'id' => 1,
            'name' => '365Sport',
            'slug' => 'cau-hinh-doanh-nghiep',
            'address' => '1 Đường Nguyễn Văn Linh, Quận Ninh Kiều, TP. Cần Thơ',
            'phone' => '0900000365',
            'email' => 'support@365sport.test',
            'website' => 'https://365sport.test/',
            'tax_code' => '0100100365',
            'description' => '365Sport là nền tảng đặt sân thể thao trực tuyến: pickleball, cầu lông, bóng đá mini, tennis — đặt nhanh, thanh toán an toàn, ghép kèo, lập nhóm và quỹ nhóm.',
            'image' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('branches')->insertOrIgnore([
            [
                'id' => 1,
                'business_id' => 1,
                'name' => '365Sport - Trụ sở Cần Thơ',
                'address' => 'TP. Cần Thơ',
                'phone' => '0900000365',
                'email' => 'support@365sport.test',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
