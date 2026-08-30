<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Luồng "chuyển khoản thủ công" (payment_method='bank_transfer', xem
// ProductDetail::processBankTransferPayment()) — khách chuyển khoản qua VietQR sinh từ
// partners.bank_bin/bank_account_number, upload ảnh biên lai, admin đối tác duyệt thủ công.
//
// CỐ Ý không đụng cột `orders.status` (ENUM cố định trong DB — pending/paid/failed/shipped/
// deposit, xem migration create_orders_table) để tránh phải ALTER ENUM rủi ro ảnh hưởng toàn bộ
// luồng PayOS hiện có — đơn chuyển khoản vẫn giữ status='pending' như bình thường cho tới khi admin
// duyệt (chuyển 'paid'), chỉ khác ở việc có payment_proof + bank_transfer_status hay không.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Đường dẫn ảnh biên lai khách upload (cùng kiểu lưu trữ path-string như cccd_front/
            // cccd_back đã có sẵn trên bảng này — không dùng Spatie Media Library).
            $table->string('payment_proof')->nullable()->after('payment_method');
            // pending = đã upload ảnh, chờ admin duyệt | confirmed = admin đã xác nhận (đồng thời
            // status chuyển 'paid') | rejected = admin từ chối (khách cần chuyển khoản lại/liên hệ).
            // null = đơn không dùng phương thức chuyển khoản thủ công, hoặc chưa upload ảnh.
            $table->string('bank_transfer_status')->nullable()->after('payment_proof');
            $table->timestamp('bank_transfer_confirmed_at')->nullable()->after('bank_transfer_status');
            $table->uuid('bank_transfer_confirmed_by')->nullable()->after('bank_transfer_confirmed_at');
            $table->foreign('bank_transfer_confirmed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['bank_transfer_confirmed_by']);
            $table->dropColumn(['payment_proof', 'bank_transfer_status', 'bank_transfer_confirmed_at', 'bank_transfer_confirmed_by']);
        });
    }
};
