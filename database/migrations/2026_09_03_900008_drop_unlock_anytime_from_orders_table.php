<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// unlock_anytime (thêm bởi 2026_06_18_000001_add_unlock_anytime_to_orders_table.php) điều khiển
// việc khách được tự mở cổng ngoài giờ nhận/trả sân — vô nghĩa khi không còn tính năng tự mở cổng.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('unlock_anytime');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('unlock_anytime')->default(false)->after('exclude_from_stats');
        });
    }
};
