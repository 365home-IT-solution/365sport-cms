<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Liên kết "dịch vụ bổ sung" với 1 vật tư kho cụ thể — dùng cho dịch vụ dạng CHO THUÊ dụng cụ
// (vợt, giày, bóng...) thay vì bán đứt/tiêu hao. Khi có warehouse_item_id, tạo order_service sẽ
// tự tăng WarehouseItem.quantity_in_use (mượn) và trả lại khi đơn kết thúc/huỷ — xem
// Modules\Warehouse\App\Services\WarehouseRentalService.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->foreignId('warehouse_item_id')
                ->nullable()
                ->after('price')
                ->constrained('warehouse_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_item_id');
        });
    }
};
