<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mở rộng nội dung trang landing page riêng theo subdomain (Partner::slug) — thêm công tắc bật/tắt
// RIÊNG (landing_page_enabled, mặc định BẬT) tách biệt với Partner::hasFeature('landing_page') (đó
// là do GÓI quyết định có ĐƯỢC PHÉP dùng tính năng hay không; cột này là admin/đối tác TỰ Ý tạm ẩn
// trang dù gói vẫn cho phép — 2 điều kiện ĐỘC LẬP, cả 2 đều phải đúng thì trang mới hiển thị, xem
// BladeThemeV1Controller::partnerLandingPage()) + các trường nội dung cho trang landing dài/đầy đủ
// hơn (tagline, giới thiệu, Facebook).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('landing_page_enabled')->default(true)->after('landing_primary_color');
            $table->string('landing_tagline')->nullable()->after('landing_page_enabled');
            $table->text('landing_description')->nullable()->after('landing_tagline');
            $table->string('landing_facebook_url')->nullable()->after('landing_description');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['landing_page_enabled', 'landing_tagline', 'landing_description', 'landing_facebook_url']);
        });
    }
};
