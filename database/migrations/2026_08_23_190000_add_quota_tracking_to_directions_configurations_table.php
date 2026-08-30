<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lưu lại quota OpenRouteService mới nhất đọc được từ header phản hồi (X-Ratelimit-*) mỗi lần gọi
// API thật — chính xác theo thời gian thực, không phụ thuộc dashboard web của ORS (hiển thị có độ
// trễ, xem trao đổi lúc kiểm tra quota không giảm dù đã gọi thành công). Hiển thị lại ở admin
// (App\Filament\Pages\DirectionsSettings) để không cần vào dashboard ORS kiểm tra.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directions_configurations', function (Blueprint $table) {
            $table->unsignedInteger('quota_limit')->nullable()->after('ors_api_key');
            $table->unsignedInteger('quota_remaining')->nullable()->after('quota_limit');
            $table->timestamp('quota_reset_at')->nullable()->after('quota_remaining');
            $table->timestamp('quota_checked_at')->nullable()->after('quota_reset_at');
        });
    }

    public function down(): void
    {
        Schema::table('directions_configurations', function (Blueprint $table) {
            $table->dropColumn(['quota_limit', 'quota_remaining', 'quota_reset_at', 'quota_checked_at']);
        });
    }
};
