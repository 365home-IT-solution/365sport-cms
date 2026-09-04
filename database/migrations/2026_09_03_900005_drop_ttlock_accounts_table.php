<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// Chạy SAU khi đã drop ttlock_account_category (900003) — pivot đó có khoá ngoại thật trỏ vào
// ttlock_accounts.id, phải gỡ trước khi drop bảng cha này.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ttlock_accounts');
    }

    public function down(): void
    {
        Schema::create('ttlock_accounts', function ($table) {
            $table->id();
            $table->string('name')->comment('Tên hiển thị trong admin');
            $table->string('client_id');
            $table->string('client_secret');
            $table->string('username');
            $table->string('password_md5', 32)->comment('MD5 lowercase của mật khẩu TTLock App');
            $table->string('api_base')->default('https://euapi.ttlock.com');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->timestamps();
        });
    }
};
