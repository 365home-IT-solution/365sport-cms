<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('price');
            // NULL = không giới hạn số chi nhánh.
            $table->unsignedInteger('max_branches')->nullable();
            // Danh sách khoá tính năng được mở (vd ["promotions","staff_permissions"]) — tra bằng
            // Partner::hasFeature(), không hardcode theo tên gói để admin tự thêm/sửa gói sau này
            // mà không cần đổi code.
            $table->json('features')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_plans');
    }
};
