<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Port từ 365home-cms — đánh dấu 1 dòng room_time_slots đang được áp bởi bảng giá nào (null = giá
// tay/gốc, không do bảng giá đặt tên nào áp). Không dùng ->constrained() — price_boards ở migration
// khác, chỉ cần cột + index để tra cứu/khôi phục, không cần ràng buộc khoá ngoại cứng.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_time_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('synced_from_price_board_id')->nullable()->after('settings');
            $table->index('synced_from_price_board_id');
        });
    }

    public function down(): void
    {
        Schema::table('room_time_slots', function (Blueprint $table) {
            $table->dropIndex(['synced_from_price_board_id']);
            $table->dropColumn('synced_from_price_board_id');
        });
    }
};
