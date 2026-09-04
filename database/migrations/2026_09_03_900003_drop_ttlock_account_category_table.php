<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Gỡ bỏ tính năng smart-lock/self-check-in — xem
// database/migrations/2026_09_03_900001_drop_access_code_order_table.php cho bối cảnh đầy đủ.
// Pivot ttlock_account_category (TtlockAccount <-> Category) phải drop TRƯỚC bảng ttlock_accounts
// (xem 2026_09_03_900005_drop_ttlock_accounts_table.php) vì nó có khoá ngoại thật trỏ tới
// ttlock_accounts.id.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('ttlock_account_category');
    }

    public function down(): void
    {
        Schema::create('ttlock_account_category', function ($table) {
            $table->unsignedBigInteger('ttlock_account_id');
            $table->unsignedBigInteger('category_id');
            $table->primary(['ttlock_account_id', 'category_id']);
            $table->unique('category_id');
            $table->foreign('ttlock_account_id')->references('id')->on('ttlock_accounts')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
        });
    }
};
