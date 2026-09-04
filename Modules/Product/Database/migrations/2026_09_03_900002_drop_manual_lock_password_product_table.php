<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// Pivot manual_lock_password_product (Product <-> ManualLockPassword) phải drop TRƯỚC bảng
// manual_lock_passwords (xem 2026_09_03_900006_drop_manual_lock_passwords_table.php) vì nó có
// khoá ngoại thật trỏ tới manual_lock_passwords.id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('manual_lock_password_product');
    }

    public function down(): void
    {
        Schema::create('manual_lock_password_product', function ($table) {
            $table->unsignedBigInteger('manual_lock_password_id');
            $table->char('product_id', 36);

            $table->primary(['manual_lock_password_id', 'product_id']);

            $table->foreign('manual_lock_password_id')
                ->references('id')
                ->on('manual_lock_passwords')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }
};
