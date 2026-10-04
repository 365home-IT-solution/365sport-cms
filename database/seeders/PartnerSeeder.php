<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\AuditLog\Entities\AuditLog;
use Modules\Category\Entities\Category;
use Modules\Employee\Entities\Department;
use Modules\Employee\Entities\Employee;
use Modules\Employee\Entities\Position;
use Modules\Payment\Entities\Order;
use Modules\BladeThemeV1\App\Models\AdditionService;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomAmenity;
use Modules\Product\App\Models\RoomService;
use Modules\Product\App\Models\RoomSpecial;
use Modules\Product\App\Models\RoomType;
use Modules\Promotion\App\Models\Coupon;
use Modules\Promotion\App\Models\Promotion;
use Spatie\Permission\Models\Role;

class PartnerSeeder extends Seeder
{
    // Tạo N đối tác mẫu, mỗi đối tác có đủ dữ liệu nghiệp vụ (≥2 bản ghi/loại) thuộc phạm vi đã
    // lọc theo partner_id — để kiểm tra toàn diện việc cô lập dữ liệu giữa các đối tác:
    // chi nhánh, sân, nhân viên, coupon, khuyến mãi, đơn hàng, audit log.
    //
    // Tên các bản ghi nghiệp vụ (sân/chi nhánh/coupon/dịch vụ...) KHÔNG gắn tên đối tác vào —
    // việc phân biệt dữ liệu của đối tác nào đã có badge màu riêng theo từng đối tác ở trang
    // super_admin (xem App\Filament\Support\PartnerTableHelpers), nên tên vẫn bình thường như một
    // đối tác thật tự đặt, không lộ ra ngoài trang khách hàng.
    //
    // Idempotent: chạy lại nhiều lần không tạo trùng (dùng firstOrCreate/updateOrCreate theo khoá tự nhiên).
    public function run(): void
    {
        $superAdmin = User::where('email', 'support@goldenbeeltd.vn')->first();

        // Phòng ban/chức vụ dùng chung cho toàn hệ thống (không thuộc riêng đối tác nào) — trước
        // đây đoạn seed nhân viên bên dưới giả định sẵn ID 1/2/3 đã tồn tại, nhưng KHÔNG có seeder
        // nào tạo ra 2 danh mục này cả, nên PartnerSeeder luôn crash lỗi khoá ngoại ngay tại đối
        // tác đầu tiên (kể cả trên database hiện tại đang dùng, không chỉ khi phục hồi DB cũ).
        // firstOrCreate theo slug để an toàn chạy lại nhiều lần.
        $departmentKinhDoanh = Department::firstOrCreate(['slug' => 'kinh-doanh'], ['name' => 'Kinh doanh', 'status' => true]);
        $departmentIT        = Department::firstOrCreate(['slug' => 'it'], ['name' => 'IT', 'status' => true]);
        $positionLeTan        = Position::firstOrCreate(['slug' => 'nhan-vien-le-tan'], ['name' => 'Nhân viên lễ tân', 'status' => true]);
        $positionNhanVien     = Position::firstOrCreate(['slug' => 'nhan-vien'], ['name' => 'Nhân viên', 'status' => true]);

        // Cùng lý do như Department/Position ở trên — đoạn seed sân bên dưới cũng giả định sẵn
        // room_type_id 1/2 đã tồn tại, nhưng không có seeder nào tạo loại sân cả.
        // Loại sân = môn thể thao (sport_type dùng cho lọc và HLV theo môn).
        $roomTypes = [];
        foreach ([
            ['pickleball', 'Pickleball',   'pickleball', 'hard',      false, 10],
            ['cau-long',   'Cầu lông',     'badminton',  'synthetic', false, 20],
            ['bong-da-mini', 'Bóng đá mini', 'football', 'artificial_grass', true, 30],
            ['tennis',     'Tennis',       'tennis',     'hard',      true,  40],
        ] as [$slug, $name, $sport, $surface, $outdoor, $sort]) {
            $roomTypes[$sport] = RoomType::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'sport_type' => $sport, 'surface_type' => $surface,
                'is_outdoor' => $outdoor, 'is_active' => true, 'sort_order' => $sort,
            ]);
        }

        $partners = [
            ['name' => 'Đối tác Golden Bee', 'tax_code' => '0100100001', 'phone' => '0900000001', 'email' => 'partner1@365home.test'],
            ['name' => 'Đối tác Sunrise Sports', 'tax_code' => '0100100002', 'phone' => '0900000002', 'email' => 'partner2@365home.test'],
            ['name' => 'Đối tác Ocean View', 'tax_code' => '0100100003', 'phone' => '0900000003', 'email' => 'partner3@365home.test'],
            // Đối tác thứ 4 — NeighboringProvinceBranchSeeder/SportsWarehouseItemSeeder tìm đúng tên này.
            // Phải đứng SAU 3 đối tác đầu (SportVenueDemoSeeder lấy 3 đối tác đầu theo created_at).
            ['name' => 'Mamble Sport', 'tax_code' => '0100100004', 'phone' => '0900000004', 'email' => 'partner4@365home.test'],
        ];

        foreach ($partners as $index => $data) {
            $n = $index + 1;

            $partner = Partner::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'       => $data['name'],
                    'tax_code'   => $data['tax_code'],
                    'phone'      => $data['phone'],
                    'status'     => true,
                    // Phải 'approved' — User::canAccessPanel() chỉ cho đối tác đã duyệt đăng nhập admin (mặc định cột là 'pending').
                    'verification_status' => 'approved',
                    'created_by' => $superAdmin?->id,
                ]
            );

            $owner = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'fullname'          => "Chủ {$data['name']}",
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'partner_id'        => $partner->id,
                    'created_by'        => $superAdmin?->id,
                ]
            );
            if (! $owner->hasRole('partner')) {
                $owner->assignRole('partner');
            }

            // Role nhân viên RIÊNG của đối tác này (created_by = chủ đối tác) — để đối tác tự thấy,
            // tự sửa/xoá/gán quyền được trong UserResource (Select role ở đó lọc created_by =
            // auth()->id(), nên role toàn cục 'employee' — created_by null — sẽ vô hình và không
            // gỡ được với đối tác). Tên role vẫn cần gắn tên đối tác để không trùng (roles là bảng
            // dùng chung, unique theo tên) — nhưng role không hiển thị cho khách hàng nên không
            // ảnh hưởng gì. Quyền khởi tạo copy từ role mẫu 'employee' (nếu có).
            $partnerEmployeeRole = Role::firstOrCreate(
                ['name' => "Nhân viên - {$data['name']}", 'guard_name' => 'web'],
                ['created_by' => $owner->id]
            );
            if ($partnerEmployeeRole->wasRecentlyCreated) {
                $templateRole = Role::where('name', 'employee')->where('guard_name', 'web')->first();
                if ($templateRole) {
                    $partnerEmployeeRole->syncPermissions($templateRole->permissions);
                }
            }

            // 2 nhân viên/đối tác, mỗi người 1 chức vụ/phòng ban khác nhau (đủ "nhiều loại" để test)
            $employeePositions  = [1 => $positionLeTan->id, 2 => $positionNhanVien->id];
            $employeeDepartments = [1 => $departmentKinhDoanh->id, 2 => $departmentIT->id];
            $employeeLabels     = [1 => 'Lễ tân', 2 => 'Kỹ thuật'];

            $employees = [];
            for ($i = 1; $i <= 2; $i++) {
                $employeeEmail = "nv{$i}.partner{$n}@365home.test";
                $label = $employeeLabels[$i];

                $employeeUser = User::updateOrCreate(
                    ['email' => $employeeEmail],
                    [
                        'fullname'          => "NV {$label}",
                        'password'          => Hash::make('password'),
                        'email_verified_at' => now(),
                        'partner_id'        => $partner->id,
                        'created_by'        => $owner->id,
                    ]
                );
                // syncRoles thay vì assignRole: vừa gán role riêng của đối tác, vừa dọn sạch
                // role 'employee' toàn cục cũ (nếu tài khoản này từng bị gán trước đây).
                if (! $employeeUser->hasRole($partnerEmployeeRole)) {
                    $employeeUser->syncRoles([$partnerEmployeeRole]);
                }

                $employees[] = Employee::updateOrCreate(
                    ['user_id' => $employeeUser->id],
                    [
                        'created_by'   => $owner->id,
                        'partner_id'   => $partner->id,
                        'name'         => $employeeUser->fullname,
                        'email'        => $employeeUser->email,
                        'phone'        => "09" . str_pad((string) ($n * 10 + $i), 8, '0', STR_PAD_LEFT),
                        'gender'       => 'male',
                        'status'       => true,
                        'position_id'   => $employeePositions[$i] ?? null,
                        'department_id' => $employeeDepartments[$i] ?? null,
                    ]
                );
            }

            $shortName = trim(preg_replace('/^Đối tác\s+/u', '', $data['name']));

            // 2 chi nhánh (Category loại 'product') — slug đã gắn theo partner nên không trùng
            // giữa các đối tác, tên giữ đơn giản/bình thường.
            $branches = [];
            for ($b = 1; $b <= 2; $b++) {
                $branches[] = Category::updateOrCreate(
                    ['slug' => "co-so-{$b}-partner-{$n}"],
                    [
                        'name'          => "{$shortName} - Cơ sở {$b}",
                        'description'   => "Cơ sở thi đấu số {$b} của {$shortName}",
                        'category_type' => 'product',
                        'status'        => true,
                        'partner_id'    => $partner->id,
                    ]
                );
            }

            // 2 sân, mỗi sân 1 loại sân (room_type_id) khác nhau và gán vào 1 chi nhánh khác nhau
            $roomTypeIds = [1 => $roomTypes['pickleball']->id, 2 => $roomTypes['badminton']->id];
            $roomTypeLabels = [1 => 'Pickleball', 2 => 'Cầu lông'];

            $rooms = [];
            for ($r = 1; $r <= 2; $r++) {
                $roomLabel = $roomTypeLabels[$r];

                $room = Product::updateOrCreate(
                    ['slug' => "san-{$r}-partner-{$n}"],
                    [
                        'partner_id'   => $partner->id,
                        'name'         => "Sân {$roomLabel} {$shortName}",
                        'description'  => "Sân {$roomLabel} tiêu chuẩn thi đấu",
                        'price'        => 100000 + ($r * 20000),
                        'styles'       => 1,
                        'room_type_id' => $roomTypeIds[$r] ?? null,
                        'is_in_stock'  => true,
                        'is_activated' => true,
                    ]
                );

                $branch = $branches[$r - 1] ?? $branches[0];
                if (! $room->categories()->where('categories.id', $branch->id)->exists()) {
                    $room->categories()->attach($branch->id);
                }

                $rooms[] = $room;
            }

            // 2 tiện ích riêng của đối tác (mỗi đối tác tự định nghĩa, không dùng chung danh mục
            // tiện ích của đối tác khác) — gán cả 2 vào cả 2 sân để test hiển thị.
            $amenityData = [
                1 => ['type' => 'Tiện ích', 'name' => 'Phòng thay đồ & tủ khoá'],
                2 => ['type' => 'Tiện ích', 'name' => 'Bãi giữ xe miễn phí'],
            ];
            foreach ($amenityData as $idx => $amenityInfo) {
                $amenity = RoomAmenity::updateOrCreate(
                    ['partner_id' => $partner->id, 'name' => $amenityInfo['name']],
                    ['amenity_type' => $amenityInfo['type'], 'status' => true, 'sort_order' => $idx]
                );
                foreach ($rooms as $room) {
                    if (! $room->amenities()->where('room_amenities.id', $amenity->id)->exists()) {
                        $room->amenities()->attach($amenity->id);
                    }
                }
            }

            // 2 dịch vụ bổ sung riêng của đối tác (vd đưa đón sân bay, giặt ủi) — gán vào cả 2 sân
            $additionServiceData = [
                1 => ['name' => 'Thuê vợt', 'price' => 30000],
                2 => ['name' => 'Nước suối', 'price' => 10000],
            ];
            foreach ($additionServiceData as $svc) {
                $service = AdditionService::updateOrCreate(
                    ['partner_id' => $partner->id, 'name' => $svc['name']],
                    ['price' => $svc['price'], 'is_active' => true]
                );
                foreach ($rooms as $room) {
                    if (! $service->products()->wherePivot('room_id', $room->id)->exists()) {
                        $service->products()->attach($room->id);
                    }
                }
            }

            // 2 dịch vụ sân + 2 điểm đặc biệt, mỗi sân 1 cái (kế thừa partner_id qua product_id)
            foreach ($rooms as $idx => $room) {
                RoomService::updateOrCreate(
                    ['product_id' => $room->id, 'name' => 'Thuê bóng/cầu'],
                    ['description' => 'Bóng hoặc cầu thi đấu chính hãng', 'price' => 20000, 'unit' => 'bộ/giờ', 'sort_order' => $idx]
                );

                RoomSpecial::updateOrCreate(
                    ['product_id' => $room->id, 'title' => 'Đèn LED chống chói'],
                    ['icon' => 'light-bulb', 'short_description' => 'Chiếu sáng đạt chuẩn thi đấu buổi tối', 'sort_order' => $idx]
                );
            }

            // 2 coupon — mã code đã gắn theo partner nên không trùng, tên giữ đơn giản/bình thường.
            for ($c = 1; $c <= 2; $c++) {
                Coupon::updateOrCreate(
                    ['code' => "P{$n}COUPON{$c}"],
                    [
                        'partner_id'  => $partner->id,
                        'name'        => "Coupon {$c}",
                        'description' => "Mã giảm giá mẫu số {$c}",
                        'type'        => $c === 1 ? 'percentage' : 'fixed',
                        'value'       => $c === 1 ? 10 : 50000,
                        'apply_type'  => 'all_rooms',
                        'start_at'    => now(),
                        'end_at'      => now()->addMonths(3),
                        'is_active'   => true,
                        'created_by'  => $owner->id,
                    ]
                );
            }

            // 2 khuyến mãi — khoá theo partner_id + tên ngắn gọn (không gắn tên đối tác vào key
            // để tên hiển thị luôn bình thường; partner_id đã đủ để tránh trùng giữa các đối tác).
            for ($p = 1; $p <= 2; $p++) {
                Promotion::updateOrCreate(
                    ['partner_id' => $partner->id, 'name' => "Khuyến mãi {$p}"],
                    [
                        'description' => "Chương trình khuyến mãi mẫu số {$p}",
                        'type'       => 'discount',
                        'value'      => $p * 10,
                        'start_at'   => now(),
                        'end_at'     => now()->addMonths(2),
                        'is_active'  => true,
                        'created_by' => $owner->id,
                    ]
                );
            }

            // 2 đơn hàng, mỗi đơn gắn với 1 chi nhánh
            for ($o = 1; $o <= 2; $o++) {
                Order::firstOrCreate(
                    ['order_code' => "PN{$n}ORD{$o}"],
                    [
                        'partner_id'  => $partner->id,
                        'category_id' => $branches[$o - 1]->id ?? $branches[0]->id,
                        'user_id'     => $owner->id,
                        'amount'      => 120000 * $o,
                        'buyer_name'  => "Khách hàng mẫu {$o}",
                        'buyer_phone' => "090111000{$o}",
                        'status'      => $o === 1 ? 'paid' : 'pending',
                        'guest_count' => 2,
                    ]
                );
            }


            // 2 audit log mẫu (thao tác của chủ đối tác)
            foreach ($rooms as $l => $room) {
                AuditLog::firstOrCreate(
                    [
                        'partner_id'   => $partner->id,
                        'target_label' => $room->name,
                    ],
                    [
                        'user_id'        => $owner->id,
                        'user_name'      => $owner->fullname,
                        'user_email'     => $owner->email,
                        'performer_role' => 'partner',
                        'action'         => $l === 0 ? 'create' : 'update',
                        'module'         => 'Product',
                        'target_id'      => $room->id,
                        'created_at'     => now(),
                    ]
                );
            }
        }
    }
}
