<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Màu chủ đạo RIÊNG cho trang landing page theo subdomain (Partner::slug, xem
// BladeThemeV1Controller::partnerLandingPage()) — null = dùng màu xanh lá mặc định của hệ thống,
// KHÔNG liên quan tới --color-primary toàn site (đó vẫn lấy từ GeneralSettings->site_theme, xem
// route('theme.css')) vì trang landing chạy hoàn toàn cục bộ (partner-landing-scope), không ảnh
// hưởng các trang khác dùng chung layout.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('landing_primary_color', 9)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('landing_primary_color');
        });
    }
};
