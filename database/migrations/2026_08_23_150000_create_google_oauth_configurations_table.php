<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cấu hình Google OAuth (đăng nhập Google cho KHÁCH HÀNG, không dùng cho admin) — nhập ở admin
// (App\Filament\Pages\GoogleLoginSettings) thay vì .env, mirror đúng cách PaymentConfiguration
// lưu cấu hình PayOS mặc định toàn hệ thống (Modules\Payment\Providers\PaymentServiceProvider).
// client_secret mã hoá qua cast 'encrypted' trên model, không lưu dạng chữ thường trong DB.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_oauth_configurations', function (Blueprint $table) {
            $table->id();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_oauth_configurations');
    }
};
