<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho phép TỪNG đối tác ghi đè cài đặt "Giảm giá khách quen" đang là 1 công tắc DUY NHẤT cho toàn
// hệ thống (GeneralSettings::loyalty_discount_enabled, xem ManageGeneral.php tab "Giảm giá khách
// quen") — 3 cột đều NULLABLE, null = "theo cài đặt chung" (hành vi CŨ, không đổi gì cho đối tác
// chưa từng cấu hình riêng), chỉ khi đối tác tự chọn ghi đè (khác null) thì mới dùng giá trị riêng.
// Xem Partner::effectiveLoyaltyDiscountEnabled()/Rate2ndOrder()/Rate3rdPlus() cho logic fallback.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->boolean('loyalty_discount_enabled')->nullable()->after('landing_faqs');
            $table->decimal('loyalty_discount_rate_2nd_order', 5, 2)->nullable()->after('loyalty_discount_enabled');
            $table->decimal('loyalty_discount_rate_3rd_plus', 5, 2)->nullable()->after('loyalty_discount_rate_2nd_order');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['loyalty_discount_enabled', 'loyalty_discount_rate_2nd_order', 'loyalty_discount_rate_3rd_plus']);
        });
    }
};
