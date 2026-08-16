<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho phép khách tự huỷ đơn/xin hoàn tiền online (trước đây chỉ admin huỷ tay, không có dấu vết
// riêng). CỐ Ý không đổi enum cột `status` (paid/deposit/pending/failed/cancelled_payment/refunded)
// — cancelled_at là NGUỒN SỰ THẬT DUY NHẤT cho "đơn này còn giữ chỗ hay không", tách biệt khỏi
// trạng thái thanh toán (giữ status nguyên vẹn để báo cáo doanh thu không bị sai lệch — 1 đơn đã
// paid rồi bị khách huỷ vẫn tính "đã từng paid" cho mục đích thống kê cũ, chỉ không còn CHIẾM CHỖ
// khung giờ nữa). Số tiền hoàn tái dùng field extra_refund_amount/extra_refund_method/
// extra_refund_paid_at đã có sẵn (xem App\Services\ExtraChargeService::recordPendingRefund() —
// cùng cơ chế panel "Hoàn tiền chưa xử lý" ở admin, không cần field hoàn tiền riêng).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->string('cancelled_by')->nullable()->after('cancelled_at')
                ->comment("'customer' | 'admin'");
            $table->text('cancel_reason')->nullable()->after('cancelled_by');

            $table->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancelled_at', 'cancelled_by', 'cancel_reason']);
        });
    }
};
