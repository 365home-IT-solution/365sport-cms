<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Hồ sơ người chơi cơ bản (thay cho phần ELO/xếp hạng): "Thông tin thể chất" (chiều cao, cân nặng, ghi chú
// đặc biệt) + "Cá nhân hoá" (vị trí yêu thích, môn & trình độ theo từng môn, mục tiêu, tần suất chơi). Tất cả
// nullable, khách tự khai qua POST /api/auth/me. Tập giá trị hợp lệ của mục tiêu/tần suất/trình độ nằm ở
// App\Models\Customer (PLAY_GOALS, PLAY_FREQUENCIES) và OpenMatch::SKILL_LEVELS — validate ở tầng API, cột
// là string/json tự do để sau này thêm giá trị không cần migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedSmallInteger('height_cm')->nullable()->after('skill_level')->comment('Chiều cao (cm).');
            $table->decimal('weight_kg', 5, 1)->nullable()->after('height_cm')->comment('Cân nặng (kg).');
            $table->string('special_note', 500)->nullable()->after('weight_kg')->comment('Ghi chú đặc biệt (chấn thương, lưu ý sức khoẻ...).');
            $table->string('favorite_position', 100)->nullable()->after('special_note')->comment('Vị trí yêu thích khi chơi.');
            $table->json('sport_levels')->nullable()->after('favorite_position')->comment('Trình độ theo từng môn: {"pickleball":"intermediate",...}.');
            $table->json('play_goals')->nullable()->after('sport_levels')->comment('Mục tiêu chơi thể thao (mảng mã trong Customer::PLAY_GOALS).');
            $table->string('play_frequency', 30)->nullable()->after('play_goals')->comment('Tần suất chơi (mã trong Customer::PLAY_FREQUENCIES).');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['height_cm', 'weight_kg', 'special_note', 'favorite_position', 'sport_levels', 'play_goals', 'play_frequency']);
        });
    }
};
