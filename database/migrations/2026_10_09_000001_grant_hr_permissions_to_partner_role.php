<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Cấp cho vai trò chủ đối tác (partner) quyền chấm công và kỳ lương — chỉ THÊM quyền còn thiếu, không đụng các quyền đã có (khác với seeder vốn đồng bộ lại cả vai trò).
// Nhân viên không có hai nhóm quyền này.
return new class extends Migration
{
    private const RESOURCES = ['employee::attendance', 'payroll::period'];

    private const ACTIONS = ['view_any', 'view', 'create', 'update', 'delete', 'delete_any'];

    public function up(): void
    {
        $role = Role::where('name', 'partner')->where('guard_name', 'web')->first();

        foreach (self::RESOURCES as $resource) {
            foreach (self::ACTIONS as $action) {
                $permission = Permission::firstOrCreate(['name' => "{$action}_{$resource}", 'guard_name' => 'web']);
                if ($role && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::where('name', 'partner')->where('guard_name', 'web')->first();
        foreach (self::RESOURCES as $resource) {
            foreach (self::ACTIONS as $action) {
                if ($role && ($p = Permission::where('name', "{$action}_{$resource}")->first())) {
                    $role->revokePermissionTo($p);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
