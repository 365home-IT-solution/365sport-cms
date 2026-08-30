<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho phép đối tác/admin CHỌN 1 trong nhiều mẫu giao diện trang landing (subdomain riêng) — trước
// đây chỉ có đúng 1 mẫu cứng (kiểu "Mona Pickleball" bento), giờ thêm field để chọn qua lại. Toàn
// bộ mẫu dùng CHUNG 1 nguồn dữ liệu (BladeThemeV1Controller::partnerLandingPage()) — chỉ khác cách
// HIỂN THỊ, không có field nội dung riêng theo từng mẫu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (! Schema::hasColumn('partners', 'landing_template')) {
                $table->string('landing_template', 30)->default('bento')->after('landing_primary_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            if (Schema::hasColumn('partners', 'landing_template')) {
                $table->dropColumn('landing_template');
            }
        });
    }
};
