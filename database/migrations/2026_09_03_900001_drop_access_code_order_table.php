<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in (TTLock/mã mở cổng) — nền tảng này cho thuê sân thể
// thao có nhân viên trực tại chỗ, không cần khách tự mở cổng như mô hình homestay/apartment gốc
// (365home-cms). Bảng pivot access_code_order (Order <-> AccessCode) phải drop TRƯỚC bảng
// access_codes (xem 2026_09_03_900004_drop_access_codes_table.php) vì access_code_order tham
// chiếu tới access_codes.id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('access_code_order');
    }

    public function down(): void
    {
        Schema::create('access_code_order', function ($table) {
            $table->id();
            $table->unsignedBigInteger('access_code_id');
            $table->unsignedBigInteger('order_id');
            $table->timestamp('assigned_at')->comment('Thời điểm gán mã cho order');
            $table->timestamps();

            $table->index('access_code_id');
            $table->index('order_id');
        });
    }
};
