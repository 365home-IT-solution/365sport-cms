<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bảng thuộc module AccessCode/TTLock đã gỡ (bị drop ở các migration 2026_09_03_9000xx) —
        // cài mới từ đầu thì bảng chưa từng được tạo, bỏ qua thay vì lỗi.
        if (! Schema::hasTable('ttlock_accounts') || Schema::hasColumn('ttlock_accounts', 'partner_id')) {
            return;
        }

        Schema::table('ttlock_accounts', function (Blueprint $table) {
            $table->uuid('partner_id')->nullable()->after('id');
            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ttlock_accounts') || ! Schema::hasColumn('ttlock_accounts', 'partner_id')) {
            return;
        }

        Schema::table('ttlock_accounts', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropColumn('partner_id');
        });
    }
};
