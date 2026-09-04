<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chính sách hoãn/hoàn tiền do thời tiết cho sân NGOÀI TRỜI (xem products.is_outdoor) — trước đây
// hệ thống hoàn toàn KHÔNG có luồng nào xử lý khi trời mưa làm khách không thể chơi, admin phải xử
// lý tay ngoài hệ thống (nhắn tin/gọi điện) và KHÔNG có dấu vết gì lưu lại trên đơn. Cùng idiom với
// cancelled_at/cancelled_by (migration add_customer_cancellation_to_orders_table) — 2 cột đánh dấu,
// KHÔNG đổi enum `status` (giữ nguyên paid/deposit để báo cáo doanh thu không bị lệch, đơn bị mưa
// hoãn/hoàn vẫn tính "đã từng thanh toán" cho mục đích thống kê cũ).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'weather_affected_at')) {
                $table->timestamp('weather_affected_at')->nullable()->after('cancel_reason');
            }

            if (! Schema::hasColumn('orders', 'weather_resolution')) {
                $table->string('weather_resolution')->nullable()->after('weather_affected_at')
                    ->comment("'refunded' | 'rescheduled'");
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['weather_affected_at', 'weather_resolution']);
        });
    }
};
