<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tên khách hiển thị cho admin (tra cứu/liên hệ) — buyer_phone mới là khoá tra cứu THẬT dùng để
// khớp gói khi đặt sân, cột này chỉ để hiển thị.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_packages', function (Blueprint $table) {
            $table->string('buyer_name')->nullable()->after('buyer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('customer_packages', function (Blueprint $table) {
            $table->dropColumn('buyer_name');
        });
    }
};
