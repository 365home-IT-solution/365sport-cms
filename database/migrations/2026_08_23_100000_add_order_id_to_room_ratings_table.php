<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gắn review với ĐÚNG đơn đã hoàn thành thay vì chỉ ràng buộc "1 review/khách/sân" chung chung —
// cho phép khách review lại đúng theo từng lần đặt riêng biệt trong tương lai nếu cần, và là điều
// kiện để RatingController::store() xác minh khách THẬT SỰ đã đặt sân trước khi cho review (xem
// App\Http\Controllers\Api\RatingController.php). Nullable vì các review CŨ (tạo trước migration
// này) không có order_id để gắn ngược lại.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_ratings', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('room_id')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_ratings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
