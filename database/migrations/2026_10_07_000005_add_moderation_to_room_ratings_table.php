<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Kiểm duyệt đánh giá sân: chủ sân/lễ tân có thể ẩn đánh giá vi phạm (is_hidden) và trả lời công khai (reply). Đánh giá bị ẩn không hiện cho
// khách khác và không tính vào điểm trung bình của sân. Cấp quyền màn "Đánh giá sân" (room::rating) cho vai trò đối tác/nhân viên.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_ratings', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('comment');
            $table->text('reply')->nullable()->after('is_hidden');
            $table->timestamp('replied_at')->nullable()->after('reply');
            $table->char('replied_by', 36)->nullable()->after('replied_at');
        });

        $names = ['view_any_room::rating', 'view_room::rating', 'update_room::rating', 'delete_room::rating'];
        foreach ($names as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            foreach (Role::where('guard_name', 'web')->get() as $role) {
                if ($role->name === 'partner' || $role->name === 'employee' || str_starts_with($role->name, 'Nhân viên - ')) {
                    // Nhân viên chỉ ẩn/trả lời (xem + sửa); xoá hẳn đánh giá chỉ chủ đối tác.
                    if ($name === 'delete_room::rating' && $role->name !== 'partner') {
                        continue;
                    }
                    if (! $role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('room_ratings', function (Blueprint $table) {
            $table->dropColumn(['is_hidden', 'reply', 'replied_at', 'replied_by']);
        });
    }
};
