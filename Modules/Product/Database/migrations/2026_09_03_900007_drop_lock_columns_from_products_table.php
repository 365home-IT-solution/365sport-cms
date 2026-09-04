<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// Xoá 4 cột khoá điện tử/mật khẩu thủ công khỏi products: lock_id, lock_id_checkout (TTLock
// keyboardPwdId của khoá ngoài/trong — xem 2025_01_01_000600_create_products_table.php),
// has_manual_lock (xem 2026_05_29_000001_add_has_manual_lock_to_products.php) và
// unlock_both_locks (xem 2026_08_30_000001_add_unlock_both_locks_to_products.php). Chạy sau khi
// manual_lock_passwords/manual_lock_password_product đã bị drop (900002/900006) vì
// Product::manualLockPasswords() và mọi code liên quan đã được gỡ khỏi ứng dụng.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['lock_id', 'lock_id_checkout', 'has_manual_lock', 'unlock_both_locks']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('lock_id')->nullable()->comment('TTLock lockId - ID khóa thông minh TTLock');
            $table->unsignedBigInteger('lock_id_checkout')->nullable()->comment('TTLock ID của khóa trong (dùng để check-out)');
            $table->boolean('has_manual_lock')->default(false)->comment('Sân dùng mật khẩu thủ công thay vì khóa TTLock');
            $table->boolean('unlock_both_locks')->default(false)->comment('Cửa cần mở CẢ 2 ổ CÙNG LÚC mới thật sự mở được');
        });
    }
};
