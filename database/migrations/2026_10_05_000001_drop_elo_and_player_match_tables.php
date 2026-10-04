<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Bỏ hẳn hệ thống ELO / kết quả trận đấu (PlayerMatch*, CustomerEloRating) — tính năng dư thừa so với nhu
// cầu thật, thay bằng hồ sơ người chơi cơ bản (xem add_player_profile_to_customers_table) và Nhóm
// (create_teams_tables). Xoá bảng theo thứ tự khoá ngoại, rồi dọn quyền Shield của PlayerMatchResource để
// không còn quyền "mồ côi" trong màn hình phân quyền. down() KHÔNG khôi phục dữ liệu (đã bỏ tính năng).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('player_match_confirmations');
        Schema::dropIfExists('player_match_participants');
        Schema::dropIfExists('player_match_sides');
        Schema::dropIfExists('player_matches');
        Schema::dropIfExists('customer_elo_ratings');

        $permissions = config('permission.table_names.permissions', 'permissions');
        if (Schema::hasTable($permissions)) {
            DB::table($permissions)->where('name', 'like', '%player::match%')->delete();
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Không khôi phục: tính năng ELO đã được gỡ bỏ có chủ đích.
    }
};
