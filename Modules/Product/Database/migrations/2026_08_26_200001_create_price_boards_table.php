<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Port từ 365home-cms (Modules/Product/Database/migrations/2026_08_27_000001_create_price_boards_table.php
// + 2026_08_27_000005_add_pricing_mode_to_price_boards_table.php, gộp thành 1 migration final vì
// dự án này chưa từng có bảng cũ để cần replay từng bước lịch sử) — xem App\Services\
// PriceBoardSyncService. "Bảng giá" (price_board) là 1 khoảng ngày hiệu lực áp giá cho nhiều sân
// cùng lúc, tách biệt khỏi "Hệ thống giá" (SettingBook, sửa giá trực tiếp) — bảng giá đặt tên có
// thể lên lịch trước/hết hạn tự khôi phục.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_boards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('note')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            // 'override'   = nhập giá cụ thể thay thế hoàn toàn cho từng sân (price_board_items).
            // 'adjustment' = 1 mức %/số tiền áp chung, tính từ giá gốc (bảng mặc định) của từng
            // sân tại thời điểm áp dụng — không cần nhập lại giá từng sân.
            $table->string('pricing_mode')->default('override');
            $table->string('adjustment_type')->nullable();
            $table->decimal('adjustment_value', 10, 2)->nullable();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_boards');
    }
};
