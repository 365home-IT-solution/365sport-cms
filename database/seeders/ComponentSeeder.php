<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComponentSeeder extends Seeder
{
    // Các khối giao diện (component) dùng trong Page builder. Chạy lại an toàn (insertOrIgnore theo id).
    public function run(): void
    {
        DB::table('components')->insertOrIgnore([
            ['id' => 1, 'name' => 'banner',            'label' => 'Banner',            'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'post',              'label' => 'Bài viết',          'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'post-page',         'label' => 'Trang bài viết',    'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'name' => 'contact',           'label' => 'Liên hệ',           'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'name' => 'gap',               'label' => 'Khoảng cách',       'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'name' => 'slide-component',   'label' => 'Slide Component',   'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'name' => 'book',              'label' => 'Đặt sân',           'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'name' => 'search-booking',    'label' => 'Tìm đơn đặt sân',   'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'content-component', 'label' => 'Nội dung',          'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
