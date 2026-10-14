<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PartnerRolePermissionsSeeder extends Seeder
{
    // Role 'partner'/'employee' mới tạo không tự có quyền nào (Filament/Shield ẩn hết menu nếu
    // role không có permission trên resource đó) — dẫn tới đối tác đăng nhập chỉ thấy vài trang
    // không được Shield bảo vệ (khai báo lưu trú, khách vãng lai, tỉnh thành, thông báo app...).
    // Seeder này cấp đủ quyền cho role 'partner' (dùng trực tiếp cho tài khoản chủ đối tác) và
    // cho role 'employee' — role 'employee' ở đây CHỈ dùng làm MẪU quyền để PartnerSeeder copy
    // sang role riêng của từng đối tác (vd "Nhân viên - Đối tác Golden Bee", created_by = chủ đối
    // tác), không gán trực tiếp cho tài khoản nào — vì role có created_by=null sẽ vô hình/không
    // gỡ được với đối tác trong UserResource (Select role ở đó lọc theo created_by = user hiện tại).
    private const PARTNER_RESOURCES = [
        'category',            // Chi nhánh
        'product',              // Sân
        'room::type',           // Danh mục Sân
        'room::amenity',        // Tiện ích Sân
        'room::amenity::assign', // Gán Tiện Ích Sân
        // 'room::image' (Ảnh Sân) KHÔNG đưa vào đây — RoomImageResource hard-code
        // canViewAny()/canCreate()/... chỉ cho super_admin (không dựa vào permission), nên cấp
        // quyền ở đây cũng không có tác dụng.
        'room::service',        // Dịch vụ Sân
        'room::special',        // Điểm Đặc Biệt
        'room::rating',         // Kiểm duyệt & trả lời đánh giá sân
        // 'room::housekeeping' (Kiểm tra dọn sân) KHÔNG đưa vào đây — RoomHousekeepingResource
        // hard-code canViewAny() chỉ cho super_admin (không dựa vào permission), nên cấp quyền ở
        // đây cũng không có tác dụng.
        'coupon',
        'promotion',
        'order',
        'invoice::request',      // Yêu cầu xuất hoá đơn VAT của khách
        'tournament',            // Giải đấu do đối tác tổ chức
        'book',
        'addition::service',
        'tag',
        'post',
        'audit::log',
        'user',                 // Tài khoản nhân viên của chính đối tác (trang này tạo đồng thời
                                // User + hồ sơ Employee liên kết) — đã lọc theo partner_id
        'user::branch::permission',
        'room::image',           // Ảnh sân
        'coach',                 // Huấn luyện viên theo chi nhánh
        'warehouse::item::rental', // Thuê dụng cụ thể thao
        'salary::template',      // Lương nhân viên của đối tác (đã lọc theo partner_id)
        'salary::type',
        'allowance::type',
        'deduction::type',
        'department',
        'position',
        'employee::attendance',  // Chấm công nhân viên của đối tác
        'payroll::period',       // Kỳ lương theo tháng
        'shield::role',          // Đối tác tự tạo role phụ cho nhân viên (đã lọc theo created_by)
        'warehouse::category',   // Danh mục vật tư
        'warehouse::unit',       // Đơn vị tính
        'warehouse::item',       // Vật tư / tồn kho
        'warehouse::stock::in',  // Nhập kho
        'warehouse::stock::out', // Xuất kho
        'warehouse::stock::check', // Kiểm kê
    ];

    // Nhân viên: thao tác vận hành hằng ngày, không quản lý tài khoản/role/audit log của đối tác.
    private const EMPLOYEE_RESOURCES = [
        'room::image',
        'coach',
        'warehouse::item::rental',
        'category',
        'product',
        'room::type',
        'room::amenity',
        'room::amenity::assign',
        'room::service',
        'room::special',
        'room::rating',
        // 'room::housekeeping' KHÔNG đưa vào đây — xem ghi chú ở PARTNER_RESOURCES phía trên.
        'coupon',
        'promotion',
        'order',
        'invoice::request',
        'book',
        'addition::service',
        'tag',
        'post',
        'warehouse::category',
        'warehouse::unit',
        'warehouse::item',
        'warehouse::stock::in',
        'warehouse::stock::out',
        'warehouse::stock::check',
    ];

    // Trang (không phải resource) chỉ có đúng 1 quyền 'page_X'. Hoàn tiền chờ xử lý và Chat khách hàng thuộc nghiệp vụ hằng ngày của chủ sân
    // lẫn lễ tân (khách tự huỷ đơn tạo ra hoàn tiền do CHÍNH đối tác xử lý), nên cấp cho cả hai vai trò; Báo cáo lương chỉ cho chủ sân.
    private const EXTRA_PAGES = [
        'page_Dashboard',
        'page_ManageGeneral',
        'page_MyProfilePage',
        'page_PendingRefunds',
        'page_CustomerChat',
    ];

    // Đối tác chỉ XEM đối soát hoa hồng của chính mình (không tạo/sửa/xoá).
    private const PARTNER_EXTRA_PERMISSIONS = [
        'view_any_partner::settlement',
        'view_partner::settlement',
    ];

    // Nhân viên chỉ xem và cập nhật giải đấu (duyệt đội, nhập tỉ số); tạo/xoá giải là việc của chủ đối tác.
    private const EMPLOYEE_EXTRA_PERMISSIONS = [
        'view_any_tournament',
        'view_tournament',
        'update_tournament',
    ];

    private const PARTNER_ONLY_PAGES = [
        'page_PaymentSetup',
        'page_SalaryReport',
    ];

    public function run(): void
    {
        $this->syncRolePermissions('partner', self::PARTNER_RESOURCES, array_merge(self::EXTRA_PAGES, self::PARTNER_ONLY_PAGES, self::PARTNER_EXTRA_PERMISSIONS));
        $this->syncRolePermissions('employee', self::EMPLOYEE_RESOURCES, array_merge(self::EXTRA_PAGES, self::EMPLOYEE_EXTRA_PERMISSIONS));
    }

    private function syncRolePermissions(string $roleName, array $resources, array $pages): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if (! $role) {
            return;
        }

        $allNames = Permission::pluck('name');

        $matched = $allNames->filter(function (string $name) use ($resources) {
            foreach ($resources as $resource) {
                if ($name === $resource
                    || str_ends_with($name, '_' . $resource)
                    || str_ends_with($name, '_any_' . $resource)
                ) {
                    return true;
                }
            }

            return false;
        })->merge(array_intersect($pages, $allNames->toArray()));

        $role->syncPermissions($matched->values()->all());
    }
}
