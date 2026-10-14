<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Trình độ người chơi theo từng môn: khách tự khai (source=self) hoặc nhân viên sân đánh giá / xác nhận (source=staff).
// sport_type = 'general' cho trình độ chung. Bảng log giữ lịch sử thay đổi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_skill_levels', function (Blueprint $table) {
            $table->id();
            $table->char('customer_id', 36)->index();
            $table->string('sport_type', 100)->default('general');
            $table->string('level', 30);
            $table->string('source', 10)->default('self'); // self | staff
            $table->char('assessed_by', 36)->nullable();    // users.id của người đánh giá (source=staff)
            $table->dateTime('assessed_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->dateTime('assessment_requested_at')->nullable(); // khách xin nhân viên đánh giá lại
            $table->timestamps();

            $table->unique(['customer_id', 'sport_type']);
            $table->index('level');
        });

        Schema::create('player_skill_level_logs', function (Blueprint $table) {
            $table->id();
            $table->char('customer_id', 36)->index();
            $table->string('sport_type', 100)->default('general');
            $table->string('from_level', 30)->nullable();
            $table->string('to_level', 30)->nullable();
            $table->string('source', 10)->default('self');
            $table->char('changed_by', 36)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Quyền Shield cho màn "Trình độ người chơi": chủ đối tác đủ quyền; nhân viên xem + đánh giá (sửa), không xoá.
        foreach (['view_any', 'view', 'create', 'update', 'delete', 'delete_any'] as $action) {
            $permission = Permission::firstOrCreate(['name' => $action . '_player::skill::level', 'guard_name' => 'web']);
            foreach (Role::where('guard_name', 'web')->get() as $role) {
                $isPartner = $role->name === 'partner';
                $isStaff = $role->name === 'employee' || str_starts_with($role->name, 'Nhân viên - ');
                $allowed = $isPartner || ($isStaff && in_array($action, ['view_any', 'view', 'create', 'update'], true));
                if ($allowed && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('player_skill_level_logs');
        Schema::dropIfExists('player_skill_levels');
    }
};
