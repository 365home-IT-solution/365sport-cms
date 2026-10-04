<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cấu hình server go2rtc/Frigate theo TỪNG ĐỐI TÁC — 1-1 với partners (mỗi đối tác có server
// riêng). base_url trỏ tới go2rtc trần (vd http://ip:1984) hoặc Frigate (vd http://ip:5000).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_settings', function (Blueprint $table) {
            $table->uuid('partner_id')->primary();
            $table->string('base_url')->nullable();
            $table->text('api_key')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_settings');
    }
};
