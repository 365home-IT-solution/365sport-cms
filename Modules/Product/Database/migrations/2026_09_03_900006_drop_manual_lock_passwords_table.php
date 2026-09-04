<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// Chạy SAU khi đã drop manual_lock_password_product (900002) — pivot đó có khoá ngoại thật trỏ
// vào manual_lock_passwords.id, phải gỡ trước khi drop bảng cha này.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('manual_lock_passwords');
    }

    public function down(): void
    {
        Schema::create('manual_lock_passwords', function ($table) {
            $table->id();
            $table->string('name')->nullable()->comment('Tên nhóm/ghi chú cho bộ mật khẩu này');
            $table->string('gate_password')->comment('Mật khẩu cổng (Pass Cổng)');
            $table->string('room_password')->nullable()->comment('Mật khẩu sân (Pass Sân)');
            $table->unsignedBigInteger('category_id')->nullable()->comment('Chi nhánh');
            $table->text('notes')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }
};
