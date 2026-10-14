<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đơn dùng gói nhiều buổi KẾT HỢP tiền mặt (số khung giờ vượt số buổi còn lại, dịch vụ, HLV...): lưu phần tiền thật khách phải trả.
// NULL = đơn trả hoàn toàn bằng gói theo cách cũ (không có tiền để hoàn khi huỷ).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('package_cash_amount')->nullable()->after('full_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('package_cash_amount');
        });
    }
};
