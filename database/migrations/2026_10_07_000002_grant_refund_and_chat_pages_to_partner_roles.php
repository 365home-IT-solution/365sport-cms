<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Hoàn tiền chờ xử lý và Chat khách hàng là việc hằng ngày của chủ sân/lễ tân (khách tự huỷ đơn tạo ra khoản hoàn do chính đối tác xử lý)
// nhưng trước đây role đối tác/nhân viên không có quyền 2 trang này. Cấp cho role mẫu 'partner'/'employee' và mọi role nhân viên đã
// sao chép cho từng đối tác ("Nhân viên - ..."); báo cáo lương chỉ cấp cho chủ sân. Role mới tạo sẽ lấy từ PartnerRolePermissionsSeeder.
return new class extends Migration
{
    public function up(): void
    {
        $grant = function (Role $role, array $names): void {
            foreach ($names as $name) {
                $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        };

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if ($role->name === 'partner') {
                $grant($role, ['page_PendingRefunds', 'page_CustomerChat', 'page_SalaryReport']);
            } elseif ($role->name === 'employee' || str_starts_with($role->name, 'Nhân viên - ')) {
                $grant($role, ['page_PendingRefunds', 'page_CustomerChat']);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Không thu hồi quyền đã cấp.
    }
};
