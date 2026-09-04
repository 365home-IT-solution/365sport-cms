<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Trước đây môn thể thao/mặt sân chỉ được "mã hoá" ngầm trong tên tự do của room_type (vd "Sân
// Pickleball") — không có dữ liệu có cấu trúc để lọc/thống kê theo môn/loại mặt sân. Thêm 3 field
// tuỳ chọn: sport_type/surface_type là text tự do (KHÔNG dùng enum cố định — mỗi đối tác có thể có
// môn thể thao khác nhau, Filament form chỉ gợi ý sẵn 1 số lựa chọn phổ biến), is_outdoor để biết
// sân trong nhà hay ngoài trời.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->string('sport_type')->nullable()->after('name');
            $table->string('surface_type')->nullable()->after('sport_type');
            $table->boolean('is_outdoor')->nullable()->after('surface_type');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['sport_type', 'surface_type', 'is_outdoor']);
        });
    }
};
