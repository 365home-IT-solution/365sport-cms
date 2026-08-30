<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mở rộng trang landing page thành nhiều KHỐI NỘI DUNG LINH HOẠT (kiểu "sườn sẵn giống WP" —
// admin tự thêm/sửa/xoá từng mục qua Filament Repeater, không cần code) — mỗi khối lưu dạng JSON
// (array cast ở Partner model), null/rỗng thì controller tự dùng nội dung mặc định để trang không
// bao giờ trống trơn ngay khi đối tác mới bật tính năng. Xem
// BladeThemeV1Controller::partnerLandingPage() + pages/partner-landing.blade.php.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('landing_hero_video_url')->nullable()->after('landing_facebook_url');
            $table->json('landing_features')->nullable()->after('landing_hero_video_url');
            $table->json('landing_services')->nullable()->after('landing_features');
            $table->json('landing_stats')->nullable()->after('landing_services');
            $table->json('landing_testimonials')->nullable()->after('landing_stats');
            $table->json('landing_pricing_plans')->nullable()->after('landing_testimonials');
            $table->json('landing_faqs')->nullable()->after('landing_pricing_plans');
            $table->json('landing_news')->nullable()->after('landing_faqs');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'landing_hero_video_url', 'landing_features', 'landing_services', 'landing_stats',
                'landing_testimonials', 'landing_pricing_plans', 'landing_faqs', 'landing_news',
            ]);
        });
    }
};
