<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// spatie/laravel-settings ghi bằng upsert theo (group, name). Bảng settings của dự án thiếu unique
// index này nên mỗi lần lưu Cài đặt chung lại CHÈN THÊM dòng trùng thay vì cập nhật. Dọn các dòng
// trùng (giữ dòng mới nhất) rồi thêm unique index để upsert hoạt động đúng.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $table = DB::getTablePrefix() . 'settings';

        DB::statement("
            DELETE s1 FROM `{$table}` s1
            INNER JOIN `{$table}` s2
                ON s1.`group` = s2.`group` AND s1.`name` = s2.`name` AND s1.`id` < s2.`id`
        ");

        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['group', 'name'], 'settings_group_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_group_name_unique');
        });
    }
};
