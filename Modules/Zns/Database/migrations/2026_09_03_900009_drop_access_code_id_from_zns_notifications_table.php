<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// zns_notifications.access_code_id (thêm bởi 2025_01_01_001900_create_zns_notifications_table.php,
// không có ràng buộc khoá ngoại thật) tham chiếu tới access_codes.id — bảng đó đã bị xoá
// (2026_09_03_900004_drop_access_codes_table.php), cột này không còn ý nghĩa.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zns_notifications', function (Blueprint $table) {
            $table->dropColumn('access_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('zns_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('access_code_id')->nullable()->after('order_id');
        });
    }
};
