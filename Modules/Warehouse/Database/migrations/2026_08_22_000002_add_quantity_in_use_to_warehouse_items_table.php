<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Đang sử dụng" — phần tồn kho đã cấp phát cho việc gì đó nhưng chưa hẳn xuất hẳn khỏi kho (vd
// đang mượn/đang lắp đặt). "Dự phòng" (quantity_reserve) KHÔNG lưu cột riêng — tính động
// = quantity - quantity_in_use ở accessor trên model, tránh 2 cột lệch nhau khi 1 trong 2 bị sửa
// tay mà quên đồng bộ cột còn lại.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->decimal('quantity_in_use', 15, 2)->default(0)->after('quantity')->comment('Số lượng đang sử dụng, chưa xuất hẳn khỏi kho');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_items', function (Blueprint $table) {
            $table->dropColumn('quantity_in_use');
        });
    }
};
