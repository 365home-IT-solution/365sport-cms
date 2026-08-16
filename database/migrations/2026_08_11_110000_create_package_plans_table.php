<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gói nhiều buổi / thẻ trả trước (App\Models\PackagePlan) — theo yêu cầu rà soát nghiệp vụ đặt
// sân: khách mua trước N buổi cho ĐÚNG 1 sân cụ thể (không dùng chéo sân khác) với giá ưu đãi hơn
// mua lẻ, dùng dần khi đặt sân đó (xem App\Models\CustomerPackage — sở hữu của khách,
// App\Models\PackageRedemption — nhật ký trừ buổi). id dùng bigint tự tăng (KHÁC char(36) UUID
// của products/customers) — bảng này không cần lộ ID ra URL công khai như product/customer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_plans', function (Blueprint $table) {
            $table->id();
            $table->char('product_id', 36)->comment('Sân áp dụng — KHÔNG dùng chéo sân khác (xem yêu cầu phạm vi gói).');
            $table->char('partner_id', 36)->nullable()->comment('Suy từ product lúc tạo — lọc theo đối tác ở DataPermission giống các resource khác.');
            $table->string('name');
            $table->unsignedInteger('sessions_count')->comment('Số buổi/khung giờ được trừ dần — 1 buổi = 1 khung giờ bất kể dài/ngắn khác nhau (khớp cách đặt sân hiện tại theo khung giờ rời rạc).');
            $table->unsignedInteger('price')->comment('Giá bán TRỌN GÓI (thường rẻ hơn sessions_count x giá 1 buổi lẻ).');
            $table->unsignedInteger('validity_days')->nullable()->comment('Số ngày hết hạn kể từ lúc mua — NULL = không hết hạn.');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('product_id');
            $table->index('partner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_plans');
    }
};
