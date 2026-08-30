<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Từng lượt chuyển khoản của thành viên (App\Models\BookingFundContribution) — kèm ảnh biên lai,
// trưởng nhóm xác nhận/từ chối tay (giống cách admin xác nhận chuyển khoản đơn đặt sân thường,
// nhưng ở đây là trưởng nhóm tự duyệt, không qua admin). Số tiền đã góp CỦA CẢ QUỸ tính động bằng
// SUM(amount) WHERE status=confirmed — KHÔNG lưu cột denormalized (số dòng nhỏ, không có race
// condition cần chống như CustomerPackage.sessions_remaining).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_fund_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_fund_id')->constrained('booking_funds')->cascadeOnDelete();
            $table->char('customer_id', 36);
            $table->unsignedInteger('amount');
            $table->string('receipt_photo');
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('booking_fund_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_fund_contributions');
    }
};
