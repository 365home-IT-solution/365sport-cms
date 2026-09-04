<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Port từ 365home-cms — lịch sử đổi giá RIÊNG (không dùng AuditLog chung của hệ thống, vì
// AuditLogger::log() chỉ ghi khi có auth()->user() — phần lớn thay đổi giá ở đây chạy tự động qua
// lịch price-boards:sync-due, không có ai đăng nhập; changed_by=null nghĩa là "hệ thống tự áp theo
// lịch"). Xem PriceBoardSyncService::logPriceChange().
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_board_price_logs', function (Blueprint $table) {
            $table->id();
            // nullOnDelete (không phải cascade): xoá 1 bảng giá không được kéo theo xoá luôn lịch
            // sử đổi giá của nó — lịch sử phải tồn tại độc lập với vật được nó ghi lại.
            $table->foreignId('price_board_id')->nullable()->constrained('price_boards')->nullOnDelete();
            $table->char('product_id', 36);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->decimal('old_price', 15, 2)->nullable();
            $table->decimal('new_price', 15, 2)->nullable();
            $table->longText('old_slots')->nullable();
            $table->longText('new_slots')->nullable();

            // null = hệ thống tự áp (lịch price-boards:sync-due), không phải ai đó đang đăng nhập.
            $table->char('changed_by', 36)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['price_board_id', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_board_price_logs');
    }
};
