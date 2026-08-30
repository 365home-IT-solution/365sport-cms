<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 2 cột số tiền giảm giá KHÔNG hề tồn tại trong DB dù code từ trước đã cố ghi vào ('coupon_discount'
// thiếu cả migration LẪN $fillable — Order::create() âm thầm bỏ qua field này bao lâu nay).
//
// 'loyalty_discount' MỚI — cần lưu riêng số tiền giảm giá khách quen (khác coupon_discount, xem
// ProductDetail::getLoyaltyDiscountRate()) để trang admin (total-amount-card.blade.php) đối chiếu
// lại đúng giá khách đã thấy lúc đặt, không hiểu nhầm phần giảm giá là "phát sinh thêm cần thu"
// khi mở lại đơn — bug thật đã gặp: đơn 200.000đ giảm 10% còn 180.000đ, admin mở lại tưởng thiếu
// 20.000đ, có nguy cơ bấm "thu thêm" xoá mất phần giảm giá khách đã được hưởng.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'coupon_discount')) {
                $table->unsignedInteger('coupon_discount')->nullable()->after('coupon_codes');
            }
            if (! Schema::hasColumn('orders', 'loyalty_discount')) {
                $table->unsignedInteger('loyalty_discount')->nullable()->after('coupon_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'loyalty_discount')) {
                $table->dropColumn('loyalty_discount');
            }
            if (Schema::hasColumn('orders', 'coupon_discount')) {
                $table->dropColumn('coupon_discount');
            }
        });
    }
};
