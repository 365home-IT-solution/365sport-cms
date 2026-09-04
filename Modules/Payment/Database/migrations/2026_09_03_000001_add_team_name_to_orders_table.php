<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho phép đơn đặt sân gắn tên đội/nhóm (đặt sân theo nhóm/đội) — trước đây chỉ có guest_count
// (số lượng khách, không có định danh nhóm). Field tự do, không bắt buộc — chỉ hữu ích khi khách
// đặt sân theo dạng đội/nhóm (vd giải đấu nội bộ, CLB), không ảnh hưởng luồng đặt sân hiện có.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('team_name')->nullable()->after('guest_count');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('team_name');
        });
    }
};
