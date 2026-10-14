<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Điểm thưởng khách hàng: số dư nằm ở customers.loyalty_points, mọi biến động ghi vào sổ cái loyalty_transactions
// (earn = tích từ đơn đã thanh toán, revoke = thu hồi khi đơn bị huỷ/xoá, redeem = đổi sang mã giảm giá, adjust = admin chỉnh tay).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points')->default(0);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->char('customer_id', 36)->index();
            $table->enum('type', ['earn', 'revoke', 'redeem', 'adjust']);
            $table->integer('points')->comment('Dương = cộng, âm = trừ.');
            $table->unsignedInteger('balance_after');
            $table->char('order_id', 36)->nullable()->index();
            $table->string('coupon_code', 50)->nullable();
            $table->string('note', 255)->nullable();
            $table->char('created_by', 36)->nullable()->comment('User admin thực hiện (adjust).');
            $table->timestamps();

            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('loyalty_points');
        });
    }
};
