<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Port từ 365home-cms — giá từng khung giờ (sân kiểu "Theo Khung Giờ", styles=1) trong 1 dòng
// price_board_items. Sân kiểu "Theo Ngày" (styles=2) không dùng bảng này (chỉ dùng field price
// chung trên price_board_items).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_board_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_board_item_id')->constrained('price_board_items')->cascadeOnDelete();
            $table->unsignedBigInteger('timeslot_id');
            $table->foreign('timeslot_id')->references('id')->on('time_slots')->cascadeOnDelete();

            $table->integer('price')->nullable();
            $table->time('checkin')->nullable();
            $table->time('checkout')->nullable();
            $table->boolean('over_night')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();

            $table->index('timeslot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_board_time_slots');
    }
};
