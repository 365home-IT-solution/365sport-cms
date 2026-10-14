<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Cấp quyền trang "Thiết lập thanh toán" cho vai trò chủ đối tác (partner). Chỉ THÊM quyền còn thiếu; nhân viên không có trang này.
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'page_PaymentSetup', 'guard_name' => 'web']);
        $role = Role::where('name', 'partner')->where('guard_name', 'web')->first();

        if ($role && ! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::where('name', 'partner')->where('guard_name', 'web')->first();
        if ($role && ($p = Permission::where('name', 'page_PaymentSetup')->first())) {
            $role->revokePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
