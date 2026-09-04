<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// Chạy SAU khi đã drop access_code_order (900001) — pivot đó không có ràng buộc khoá ngoại thật
// trỏ vào access_codes, nhưng vẫn drop nó trước để dữ liệu logic không mồ côi giữa chừng.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('access_codes');
    }

    public function down(): void
    {
        Schema::create('access_codes', function ($table) {
            $table->id();
            $table->string('code', 20)->comment('Mã mở cửa');
            $table->unsignedBigInteger('category_id');
            $table->enum('status', ['active', 'disabled', 'expired'])->default('active');
            $table->integer('usage_count')->default(0);
            $table->timestamp('used_at')->nullable()->comment('Thời gian sử dụng');
            $table->timestamp('valid_from')->nullable()->comment('Có hiệu lực từ');
            $table->timestamp('valid_until')->nullable()->comment('Hết hiệu lực');
            $table->string('gate_location')->nullable()->comment('Vị trí cổng cụ thể');
            $table->unsignedBigInteger('ttlock_keyboard_pwd_id')->nullable();
            $table->unsignedBigInteger('ttlock_keyboard_pwd_id_checkout')->nullable();
            $table->string('max_uses', 50)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('status');
        });
    }
};
