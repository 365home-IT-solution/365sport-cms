<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cấu hình dẫn đường (bản đồ /ban-do) — bật thì dùng OpenRouteService (avoid_countries chặn định
// tuyến qua Lào/Campuchia ngay từ API, chính xác hơn); tắt thì quay lại y hệt trước đây (OSRM công
// cộng + tự lọc điểm ngoài lãnh thổ VN, xem map-page.blade.php). Nhập ở admin
// (App\Filament\Pages\DirectionsSettings), không qua .env — cùng quy ước với GoogleOauthConfiguration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directions_configurations', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->text('ors_api_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directions_configurations');
    }
};
