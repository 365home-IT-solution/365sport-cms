<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho phép khách đăng nhập bằng Email + mật khẩu (thay thế, KHÔNG song song với hệ thống tài
// khoản) — customers vẫn định danh chính bằng `phone` (unique, đã có sẵn); email chỉ là PHƯƠNG
// THỨC đăng nhập THÊM cho tài khoản đã tồn tại, gắn qua luồng xác thực mã 6 số riêng (xem
// ZaloOtpController::sendEmailVerificationCode()/verifyEmail()). Nullable vì đa số khách hiện tại
// chỉ có phone, chưa gắn email.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('email')->nullable()->unique()->after('phone_verified_at');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email', 'email_verified_at']);
        });
    }
};
