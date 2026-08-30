<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lưu IP của trình duyệt lúc tạo đơn — cần để chặn phá hoại kiểu "đặt liên tục nhiều lần đè khung
// giờ, chờ hết hạn rồi đặt lại" (số điện thoại KHÔNG được xác minh OTP ở luồng đặt sân, đổi số vô
// hạn để né giới hạn theo SĐT — xem App\Services\BookingRateLimiter). Không dùng SĐT làm khoá vì lý
// do đó.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'creator_ip')) {
                $table->string('creator_ip', 45)->nullable()->after('device_token')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'creator_ip')) {
                $table->dropColumn('creator_ip');
            }
        });
    }
};
