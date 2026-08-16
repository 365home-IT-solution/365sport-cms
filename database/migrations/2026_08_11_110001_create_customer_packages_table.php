<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gói mà 1 khách CỤ THỂ đã sở hữu sau khi mua (từ App\Models\PackagePlan) — xem
// App\Models\CustomerPackage. Chấp nhận package_plan_id có thể bị xoá/sửa sau này (không đặt FK
// cứng, snapshot lại product_id/sessions_total/price_paid tại thời điểm mua để lịch sử luôn đúng
// dù plan gốc thay đổi sau — cùng triết lý với order_items không phụ thuộc cứng vào cấu hình sân
// hiện tại).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_plan_id')->nullable();
            $table->char('customer_id', 36);
            $table->char('product_id', 36)->comment('Snapshot từ plan lúc mua — sân được phép dùng gói này.');
            $table->string('name')->comment('Snapshot tên gói lúc mua.');
            $table->unsignedInteger('sessions_total');
            $table->unsignedInteger('sessions_remaining');
            $table->unsignedInteger('price_paid');
            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable();
            // Kênh bán: 'online' (khách tự mua qua PayOS, xem PaymentController::handlePaidStatus()
            // nhánh order_type=package_purchase) | 'admin' (admin bán tay walk-in, thu tiền ngoài
            // hệ thống — xem PackagePlanResource các action bán gói).
            $table->string('sale_channel')->default('online');
            $table->unsignedBigInteger('order_id')->nullable()->comment('Đơn thanh toán online tương ứng (order_type=package_purchase) — NULL nếu admin bán tay.');
            $table->char('created_by', 36)->nullable()->comment('Admin đã bán gói này (walk-in) — NULL nếu khách tự mua online.');
            $table->timestamps();

            $table->index('customer_id');
            $table->index('product_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_packages');
    }
};
