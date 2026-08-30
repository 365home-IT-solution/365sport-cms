<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho phép TỪNG đối tác tự cấu hình tài khoản merchant PayOS riêng (thay vì luôn dùng chung 1 tài
// khoản nền tảng, xem PayOSCredentialResolver) — đối tác nào KHÔNG cấu hình PayOS thì tự động rơi
// xuống "chuyển khoản ngân hàng thủ công" (Partner::hasBankTransferInfo(), đã có sẵn) nếu có, hoặc
// tài khoản PayOS chung của nền tảng (fallback cuối, giữ nguyên hành vi cũ cho đối tác chưa cấu
// hình gì cả — không phá luồng thanh toán hiện có của bất kỳ đối tác nào).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // encrypted cast ở Partner model — không lưu API key/checksum dạng plain text trong DB.
            $table->text('payos_client_id')->nullable()->after('bank_bin');
            $table->text('payos_api_key')->nullable()->after('payos_client_id');
            $table->text('payos_checksum_key')->nullable()->after('payos_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['payos_client_id', 'payos_api_key', 'payos_checksum_key']);
        });
    }
};
