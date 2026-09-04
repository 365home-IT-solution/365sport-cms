<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Liên kết 1 Customer (nhân viên công ty) với 1 CorporateAccount — KHÔNG dùng belongsToMany/pivot
// thuần (chỉ id đôi) vì cần giữ LỊCH SỬ ai đã từng được gán/gỡ khỏi tài khoản công ty (added_at/
// removed_at, giống cách package_redemptions giữ redeemed_at/refunded_at thay vì xoá thẳng dòng) —
// gỡ 1 nhân viên KHÔNG xoá bản ghi, chỉ set removed_at (xem CorporateAccount::activeEmployees()).
// 1 Customer có thể là nhân viên của NHIỀU công ty khác nhau theo thời gian (đổi việc) nên KHÔNG
// đặt unique cứng trên customer_id — chỉ nên tránh trùng lặp Ở TẦNG ỨNG DỤNG (không thêm 1 nhân
// viên đang active 2 lần vào CÙNG 1 công ty, xem CorporateAccountController::addEmployee()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_account_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corporate_account_id');
            $table->char('customer_id', 36)->comment('App\\Models\\Customer.id (UUID).');
            $table->timestamp('added_at');
            $table->timestamp('removed_at')->nullable()->comment('Gỡ khỏi công ty (nghỉ việc/chuyển team) — NULL = đang là nhân viên hoạt động.');
            $table->timestamps();

            $table->index('corporate_account_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_account_employees');
    }
};
