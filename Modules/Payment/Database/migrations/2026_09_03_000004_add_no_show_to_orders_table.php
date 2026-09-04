<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đánh dấu khách ĐÃ đặt sân (paid/deposit) nhưng KHÔNG đến nhận sân — khác hẳn huỷ đơn
// (cancelled_at/cancelled_by ở migration 2026_08_11_100000_add_customer_cancellation_to_orders_table:
// khách/admin CHỦ ĐỘNG huỷ TRƯỚC giờ nhận sân, giải phóng khung giờ cho người khác). No-show là đơn
// VẪN giữ chỗ nguyên vẹn tới hết giờ mà khách không xuất hiện — không giải phóng khung giờ, không
// hoàn tiền tự động (khác cancelled_at), chỉ là NHÃN thống kê nội bộ để nhận diện khách hay bùng kèo.
// CỐ Ý không đổi enum cột `status` (paid/deposit/pending/failed/cancelled_payment/refunded) — cùng
// triết lý với cancelled_at: giữ nguyên status để báo cáo doanh thu không bị lệch (1 đơn đã paid rồi
// khách không đến vẫn tính "đã từng paid" cho mục đích thống kê cũ).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('no_show_at')->nullable()->after('cancel_reason');
            $table->string('no_show_marked_by')->nullable()->after('no_show_at')
                ->comment("id (string) của admin thao tác, hoặc 'system' nếu job tự động đánh dấu (chưa xây)");

            $table->index('no_show_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['no_show_at']);
            $table->dropColumn(['no_show_at', 'no_show_marked_by']);
        });
    }
};
