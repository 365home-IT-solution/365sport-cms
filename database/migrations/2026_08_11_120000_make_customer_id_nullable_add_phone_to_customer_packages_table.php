<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Phát hiện thực tế: dự án này gần như KHÔNG có khách hàng đăng ký tài khoản (bảng customers rỗng)
// — mọi đơn đặt sân đều là "khách vãng lai" định danh qua buyer_phone (xem Order, AccountPage::
// phoneQuery()). Gói nhiều buổi PHẢI theo ĐÚNG cách này để dùng được thực tế — customer_id giờ chỉ
// là liên kết TUỲ CHỌN (nếu khách có tài khoản), buyer_phone mới là khoá tra cứu CHÍNH (bắt buộc).
// Drop + tạo lại cột thay vì ->change() (không cài doctrine/dbal) — bảng vừa tạo, chưa có dữ liệu
// thật cần giữ.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_packages', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });

        Schema::table('customer_packages', function (Blueprint $table) {
            $table->char('customer_id', 36)->nullable()->after('package_plan_id');
            $table->string('buyer_phone')->nullable()->after('customer_id');
            $table->index('buyer_phone');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_packages', function (Blueprint $table) {
            $table->dropIndex(['buyer_phone']);
            $table->dropColumn(['buyer_phone', 'customer_id']);
        });

        Schema::table('customer_packages', function (Blueprint $table) {
            $table->char('customer_id', 36)->after('package_plan_id');
        });
    }
};
