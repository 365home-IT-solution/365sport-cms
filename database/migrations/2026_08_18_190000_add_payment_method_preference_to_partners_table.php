<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Khi đối tác cấu hình CẢ 2 phương thức (PayOS riêng + chuyển khoản ngân hàng), ADMIN quyết định
// dùng phương thức nào — KHÔNG để khách hàng tự chọn nữa (yêu cầu người dùng: "admin có quyền
// quyết định 1 trong 2 chứ không để khách hàng chọn"). null = chưa chọn, mặc định về 'payos' (xem
// Partner::effectivePaymentMethod()). Cột này KHÔNG có tác dụng gì khi đối tác chỉ có 1 trong 2
// (hoặc không cấu hình gì) — khi đó chỉ có đúng 1 lựa chọn khả dĩ, không có gì để "ưu tiên".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('payment_method_preference', 20)->nullable()->after('payos_checksum_key');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('payment_method_preference');
        });
    }
};
