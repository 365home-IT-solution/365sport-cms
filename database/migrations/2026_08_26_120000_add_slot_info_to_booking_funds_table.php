<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cho trưởng nhóm chọn TRỰC TIẾP chi nhánh/sân/khung giờ dự định đặt khi tạo quỹ (thay vì chỉ ghi
// chú tự do) — để MINH BẠCH hơn cho thành viên góp tiền: biết chính xác đang góp cho sân/giờ nào.
// LƯU Ý: đây vẫn chỉ là THÔNG TIN MÔ TẢ, KHÔNG giữ chỗ khung giờ thật (không có TimeslotHold) —
// đúng nguyên tắc "tiền không đi qua hệ thống, quỹ không gắn với 1 booking thật" của plan gốc.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_funds', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('leader_customer_id')->comment('Chi nhánh dự định đặt — Modules\Category\Entities\Category.');
            $table->char('product_id', 36)->nullable()->after('category_id')->comment('Sân dự định đặt.');
            $table->date('slot_date')->nullable()->after('product_id');
            $table->time('slot_start_time')->nullable()->after('slot_date');
            $table->time('slot_end_time')->nullable()->after('slot_start_time');

            $table->index('category_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('booking_funds', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropIndex(['product_id']);
            $table->dropColumn(['category_id', 'product_id', 'slot_date', 'slot_start_time', 'slot_end_time']);
        });
    }
};
