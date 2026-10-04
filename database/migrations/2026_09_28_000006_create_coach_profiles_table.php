<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Thông tin riêng cho Product khi type='coach' (thuê HLV theo giờ) — tách bảng phụ thay vì nhồi
// thêm cột chuyên biệt vào 'products' (vốn đã rất nhiều cột cho nghiệp vụ sân). 1-1 với products.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_profiles', function (Blueprint $table) {
            $table->char('product_id', 36)->primary();
            $table->string('specialties')->nullable()->comment('VD: Cầu lông, Pickleball');
            $table->text('certifications')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_profiles');
    }
};
