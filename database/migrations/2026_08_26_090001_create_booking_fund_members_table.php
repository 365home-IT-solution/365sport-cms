<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Danh sách xin tham gia quỹ (App\Models\BookingFundMember) — trưởng nhóm KHÔNG có row ở đây
// (thành viên ngầm định qua booking_funds.leader_customer_id). Chỉ khách status=approved mới được
// góp tiền (xem booking_fund_contributions).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_fund_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_fund_id')->constrained('booking_funds')->cascadeOnDelete();
            $table->char('customer_id', 36);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['booking_fund_id', 'customer_id']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_fund_members');
    }
};
