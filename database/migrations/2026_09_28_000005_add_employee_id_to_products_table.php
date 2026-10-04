<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Liên kết Product khi type='coach' với 1 Employee (nhân sự thật, chấm công/nhận lương) — nullable
// vì chỉ có ý nghĩa với coach, sân bình thường không cần. Coach VẪN LÀ 1 Product (tái dùng nguyên
// engine RoomTimeSlot/PriceBoard/Book/Order để bán giờ dạy cho khách) — Employee ở đây chỉ để biết
// "coach này là nhân viên nào" khi cần quản lý lương/chấm công qua module Employee, 2 khái niệm
// tách biệt nối bằng FK này.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('room_type_id')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
