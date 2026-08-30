<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nút bật/tắt đăng nhập Google ở admin — tắt thì ẩn nút Google khỏi giao diện khách hàng ngay,
// không cần xoá Client ID/Secret đã nhập (giữ lại để bật lại nhanh sau này).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_oauth_configurations', function (Blueprint $table) {
            $table->boolean('enabled')->default(false)->after('client_secret');
        });
    }

    public function down(): void
    {
        Schema::table('google_oauth_configurations', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
