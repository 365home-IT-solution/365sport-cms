<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_plans', function (Blueprint $table) {
            // 2 cột này CHỈ phục vụ hiển thị trang quảng cáo /lam-doi-tac (mô tả ngắn dưới tên
            // gói + có nổi bật badge "Phổ biến nhất" hay không) — không ảnh hưởng gì tới việc
            // giới hạn/mở khoá tính năng thật (features/max_branches).
            $table->string('description')->nullable()->after('name');
            $table->boolean('is_highlighted')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('partner_plans', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_highlighted']);
        });
    }
};
