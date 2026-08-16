<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phân biệt đơn "đặt sân" (booking, mặc định — mọi hành vi cũ giữ nguyên) với đơn "mua gói nhiều
// buổi" (package_purchase, KHÔNG có order_items mang checkin_date/checkout_date thật — chỉ dùng
// PayOS để thu tiền rồi kích hoạt CustomerPackage, xem PaymentController::handlePaidStatus()).
// order_type='package_purchase' phải được xử lý riêng NGAY ĐẦU handlePaidStatus() để KHÔNG chạy
// nhầm vào logic gán mã cổng/checkin của luồng đặt sân — đơn mua gói không có sân/khung giờ để mở
// cổng.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type')->default('booking')->after('status');
            $table->unsignedBigInteger('package_plan_id')->nullable()->after('order_type')
                ->comment('Chỉ set khi order_type=package_purchase — plan đang mua.');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['order_type', 'package_plan_id']);
        });
    }
};
