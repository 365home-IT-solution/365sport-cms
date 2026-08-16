<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nhật ký mỗi lần dùng gói để trừ 1 buổi (App\Models\PackageRedemption) — 1 dòng = 1 khung giờ đã
// đặt bằng gói thay vì trả tiền, liên kết tới order_item cụ thể để có thể "hoàn buổi lại" đúng 1
// khung giờ nếu đơn đó bị huỷ (xem CustomerPackage::refundRedemptionFor()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_package_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('order_id');
            $table->timestamp('redeemed_at');
            $table->timestamp('refunded_at')->nullable()->comment('Buổi được hoàn lại vào gói (đơn dùng buổi này bị huỷ) — xem OrderCancellationService.');
            $table->timestamps();

            $table->index('customer_package_id');
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_redemptions');
    }
};
