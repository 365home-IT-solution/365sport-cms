<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nhật ký MƯỢN-TRẢ dụng cụ thể thao gắn theo đơn đặt sân (order_services) — khác hẳn phiếu
// xuất kho (WarehouseStockOut, dành cho vật tư TIÊU HAO). 1 dòng = 1 lượt mượn của 1 order_service.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_item_rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_service_id')->constrained('order_services')->cascadeOnDelete();
            $table->foreignId('warehouse_item_id')->constrained('warehouse_items')->cascadeOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->timestamp('rented_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('status')->default('rented'); // rented | returned | lost
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_item_rentals');
    }
};
