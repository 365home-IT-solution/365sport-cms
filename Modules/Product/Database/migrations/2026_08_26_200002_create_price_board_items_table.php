<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Port từ 365home-cms — 1 dòng = giá của 1 sân (product_id) trong 1 bảng giá (price_board_id).
// baseline_fields/baseline_time_slots (gộp từ migration bổ sung sau ở home) chụp "giá gốc" của sân
// NGAY LÚC TẠO bảng giá đặt tên này — đóng băng vĩnh viễn, dùng làm giá khôi phục khi CHÍNH bảng
// này hết hạn thay vì luôn đọc "Bảng giá mặc định" (liên tục cập nhật theo lần lưu mới nhất) — xem
// PriceBoardSyncService::captureBaselineIfMissing().
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_board_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_board_id')->constrained('price_boards')->cascadeOnDelete();
            $table->char('product_id', 36);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->decimal('price', 15, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->string('full_booking_discount', 50)->nullable();
            $table->longText('bulk_discount_rules')->nullable();
            $table->longText('room_config')->nullable();
            $table->unsignedTinyInteger('deposit_1_night')->nullable();
            $table->unsignedTinyInteger('deposit_multi_night')->nullable();
            $table->unsignedTinyInteger('deposit_min_nights')->nullable();
            $table->time('default_checkin')->nullable();
            $table->time('default_checkout')->nullable();
            $table->longText('baseline_fields')->nullable();
            $table->longText('baseline_time_slots')->nullable();
            $table->timestamps();

            $table->unique(['price_board_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_board_items');
    }
};
