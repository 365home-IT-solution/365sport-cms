<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mã BIN ngân hàng (chuẩn Napas, vd Vietcombank=970436) — bắt buộc để sinh mã QR VietQR tự động
// theo cú pháp https://img.vietqr.io/image/{bin}-{so_tk}-compact2.png (xem
// config/vietnam_banks.php cho danh sách bin theo tên ngân hàng, và luồng "chuyển khoản thủ công"
// mới: BladeThemeV1Controller::uploadPaymentProof()). partners.bank_name/bank_account_number/
// bank_account_holder đã có sẵn từ trước — bank_bin là field DUY NHẤT còn thiếu để sinh QR.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('bank_bin', 10)->nullable()->after('bank_name');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('bank_bin');
        });
    }
};
