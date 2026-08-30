<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Khách đăng ký "báo khi có chỗ trống" cho 1 sân đã hết chỗ. 2 kiểu sân dùng field khác nhau
// (giống chính cách Order::broadcastSlotsReleasedByProduct() phân biệt): sân "khung giờ"
// (Product::styles=1) dùng date+timeslot_id; sân "theo ngày" dùng checkin_date/checkout_date.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_waitlists', function (Blueprint $table) {
            $table->id();
            $table->char('customer_id', 36);
            $table->char('product_id', 36);
            $table->date('date')->nullable();
            $table->unsignedBigInteger('timeslot_id')->nullable();
            $table->timestamp('checkin_date')->nullable();
            $table->timestamp('checkout_date')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['product_id', 'date', 'timeslot_id']);
            $table->index(['product_id', 'checkin_date', 'checkout_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_waitlists');
    }
};
