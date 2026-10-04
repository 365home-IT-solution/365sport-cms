<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    // Chỉ tạo DANH MỤC BÀI VIẾT (tin tức). Chi nhánh/khu vực sân (category_type = 'product') do
    // PartnerSeeder / SportVenueDemoSeeder tạo theo từng đối tác — không còn danh mục phòng homestay
    // cố định như bản 365Home cũ. Chạy lại an toàn (updateOrInsert theo slug).
    public function run(): void
    {
        $categories = [
            ['Tin tức thể thao', 'tin-tuc-the-thao'],
            ['Hướng dẫn & kỹ thuật', 'huong-dan-ky-thuat'],
            ['Giải đấu & sự kiện', 'giai-dau-su-kien'],
            ['Khuyến mãi', 'khuyen-mai'],
        ];

        foreach ($categories as [$name, $slug]) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $slug, 'category_type' => 'post'],
                ['name' => $name, 'description' => null, 'status' => 1, 'image' => null, 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
