<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Trình độ chơi tự khai của khách — dùng để lọc/gợi ý ghép cặp trong tính năng "Tìm bạn chơi"
// (App\Models\OpenMatch). Giá trị tự do (KHÔNG enum cứng ở tầng DB, giống cách sport_type ở
// room_types được để text tự do — xem migration add_sport_attributes_to_room_types_table) — validate
// đúng tập 'beginner'|'intermediate'|'advanced'|'pro' ở tầng API (OpenMatchController/ZaloOtpController)
// để sau này dễ đổi/thêm mức mà không cần migration lại.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('skill_level')->nullable()->after('no_show_count');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('skill_level');
        });
    }
};
