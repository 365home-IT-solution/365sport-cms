<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Province;
use App\Models\ProvinceBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomType;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Product\App\Models\TimeSlot;

// Tạo 4 chi nhánh test ở các tỉnh lân cận Cần Thơ (Cà Mau, Vĩnh Long, Đồng Tháp, An Giang) — mỗi
// chi nhánh có toạ độ (latitude/longitude) THẬT của trung tâm thành phố đó, để kiểm tra tính năng
// "dẫn đường"/tính khoảng cách trên trang chủ + trang Bản đồ (VenueGrid.php/MapComponent.php đọc
// thẳng Product::address/latitude/longitude — xem ghi chú trong 2 file đó). Gắn vào ĐÚNG đối tác
// "Mamble Sport" (partner test đã dùng xuyên suốt phiên làm việc) để không phải tạo thêm tài khoản
// đối tác mới chỉ để test 1 tính năng hiển thị.
//
// LƯU Ý địa giới: sau đợt sáp nhập tỉnh 2025, Hậu Giang + Sóc Trăng đã nhập vào Cần Thơ, Kiên Giang
// đã nhập vào An Giang — bảng `provinces` của HỆ THỐNG NÀY chỉ còn 34 tỉnh/thành, xác nhận qua
// tinker KHÔNG còn "Hậu Giang"/"Sóc Trăng"/"Kiên Giang" riêng. Vì vậy "Vị Thanh" (cũ là Hậu Giang)
// KHÔNG còn tính là "tỉnh lân cận" nữa (giờ CÙNG tỉnh Cần Thơ) — đổi sang Cà Mau (tỉnh lân cận thật
// sự, vẫn còn riêng) để đúng ý "kiểm tra dẫn đường khác tỉnh".
//
// province_id dưới đây lấy ĐÚNG theo `App\Models\Province` hiện có của hệ thống — bắt buộc tra lại
// nếu chạy trên DB khác (id có thể khác), KHÔNG hard-code theo tên vì tra bằng tên dễ sai khi có 2
// tỉnh trùng tiền tố (vd "Tỉnh Vĩnh Long" ~ "Tỉnh Vĩnh Phúc").
class NeighboringProvinceBranchSeeder extends Seeder
{
    public function run(): void
    {
        $partner = Partner::where('name', 'Mamble Sport')->first();

        if (! $partner) {
            $this->command?->warn('Không tìm thấy đối tác "Mamble Sport" — bỏ qua seeder chi nhánh lân cận Cần Thơ.');
            return;
        }

        $roomTypeId = RoomType::query()->value('id');

        // Dùng lại 1 ảnh SÂN THẬT đã có sẵn trên đĩa (không phải chỉ có bản ghi DB — nhiều sân demo
        // khác trong hệ thống có bản ghi media nhưng file vật lý đã mất, hiện ảnh vỡ, xem bug đã
        // sửa ở booking-detail.blade.php) — kiểm tra tồn tại thật trước khi dùng, KHÔNG seed ảnh nếu
        // file nguồn không còn, tránh lặp lại đúng lỗi "ảnh vỡ" đã gặp.
        $sourceImagePath = storage_path('app/public/13/pickleball.jpg');
        $sourceImageExists = file_exists($sourceImagePath);
        if (! $sourceImageExists) {
            $this->command?->warn('Không tìm thấy ảnh mẫu (storage/app/public/13/pickleball.jpg) — 4 sân mới sẽ KHÔNG có ảnh bìa, cần upload tay sau.');
        }

        // Khung giờ 05:00-22:00 (17 khung, khớp đúng cấu hình "Sân BOSS" thật của Mamble Sport —
        // loại trừ khung 22:00-23:00 và khung qua đêm 23:00-06:00) — không có khung giờ thì sân
        // KHÔNG đặt được (lưới NGÀY×GIỜ trống trơn), đây là lý do chính khiến bạn không test được.
        $timeSlotIds = TimeSlot::orderBy('start_time')
            ->where('label', '!=', '22:00 - 23:00')
            ->where('label', 'not like', '%Qua đêm%')
            ->pluck('id');

        // Toạ độ THẬT (trung tâm thành phố) — đủ chính xác để test khoảng cách/dẫn đường, không cần
        // toạ độ chính xác tới từng sân. 'province_name' dùng để TRA province_id thật trong DB
        // (không hard-code id) — xem vòng lặp bên dưới.
        $branches = [
            [
                'name'          => 'Mamble Sport - Cà Mau',
                'address'       => '123 Đường Trần Hưng Đạo, Cà Mau',
                'latitude'      => 9.1769,
                'longitude'     => 105.1500,
                'province_name' => 'Cà Mau',
            ],
            [
                'name'          => 'Mamble Sport - Vĩnh Long',
                'address'       => '45 Đường 30 Tháng 4, Vĩnh Long',
                'latitude'      => 10.2537,
                'longitude'     => 105.9722,
                'province_name' => 'Vĩnh Long',
            ],
            [
                'name'          => 'Mamble Sport - Cao Lãnh',
                'address'       => '78 Đường Nguyễn Huệ, Cao Lãnh',
                'latitude'      => 10.4593,
                'longitude'     => 105.6325,
                'province_name' => 'Đồng Tháp',
            ],
            [
                'name'          => 'Mamble Sport - Long Xuyên',
                'address'       => '12 Đường Trần Hưng Đạo, Long Xuyên',
                'latitude'      => 10.3861,
                'longitude'     => 105.4348,
                'province_name' => 'An Giang',
            ],
        ];

        foreach ($branches as $branch) {
            $category = Category::updateOrCreate(
                ['slug' => Str::slug($branch['name'])],
                [
                    'name'          => $branch['name'],
                    'description'   => $branch['address'],
                    'category_type' => 'product',
                    'partner_id'    => $partner->id,
                    'status'        => true,
                    'parent_id'     => null,
                    'timezone'      => 'Asia/Ho_Chi_Minh',
                    'checkin_time'  => '05:00:00',
                    'checkout_time' => '22:00:00',
                ]
            );

            $product = Product::updateOrCreate(
                ['slug' => Str::slug($branch['name']) . '-san-1'],
                [
                    'partner_id'    => $partner->id,
                    'name'          => $branch['name'] . ' - Sân 1',
                    'type'          => 'simple',
                    'room_type_id'  => $roomTypeId,
                    'price'         => 100000,
                    'discount'      => 0,
                    'vat'           => 0,
                    'address'       => $branch['address'],
                    'latitude'      => $branch['latitude'],
                    'longitude'     => $branch['longitude'],
                    'is_in_stock'   => true,
                    'is_activated'  => true,
                    'is_shipped'    => true,
                    'room_config'   => ['max_free_guests' => 2, 'extra_guest_fee' => 50000],
                    'styles'        => 1,
                ]
            );

            if (! $product->categories()->where('category_id', $category->id)->exists()) {
                $product->categories()->attach($category->id);
            }

            // Ảnh bìa — preservingOriginal() bắt buộc: đây là 1 file DÙNG CHUNG cho cả 4 sân (không
            // phải file tạm 1 lần), addMedia() mặc định XOÁ file nguồn sau khi copy nếu không có
            // cờ này, sẽ làm hỏng luôn ảnh của sân đầu tiên đã có sẵn (id 13) sau lần chạy đầu.
            if ($sourceImageExists && ! $product->getFirstMedia('Ảnh bìa')) {
                $product->addMedia($sourceImagePath)
                    ->preservingOriginal()
                    ->usingFileName(Str::slug($branch['name']) . '.jpg')
                    ->toMediaCollection('Ảnh bìa');
            }

            // Khung giờ 05:00-22:00 — updateOrCreate theo (room_id, timeslot_id, date=null) để chạy
            // lại seeder nhiều lần không tạo trùng khung giờ.
            foreach ($timeSlotIds as $timeSlotId) {
                RoomTimeSlot::updateOrCreate(
                    ['room_id' => $product->id, 'timeslot_id' => $timeSlotId, 'date' => null],
                    ['price' => 100000, 'status' => 'available', 'over_night' => false]
                );
            }

            // MẢNH QUAN TRỌNG NHẤT — dễ bỏ sót nhất: trang chủ (VenueGrid.php) và các trang gợi ý
            // chi nhánh KHÔNG query trực tiếp từ Category, mà query từ App\Models\ProvinceBranch
            // (bảng trung gian gán 1 Category cho 1 Province) rồi mới join ngược lại Category. Tạo
            // xong Category + Product mà THIẾU dòng ProvinceBranch này thì chi nhánh vẫn tồn tại
            // trong DB nhưng KHÔNG BAO GIỜ xuất hiện ở trang chủ — bug thật đã gặp khi test seeder
            // lần đầu (4 chi nhánh tạo xong nhưng "sân mới tạo đâu??" — thiếu đúng bước này).
            // ward_code để trống (null) — bảng `wards` của hệ thống hiện CHỈ có dữ liệu đủ cho Hà
            // Nội/TP.HCM/Cần Thơ (391 dòng, xác nhận qua tinker), không có phường/xã nào cho Cà
            // Mau/Vĩnh Long/Đồng Tháp/An Giang để gán đúng — cột này nullable nên để trống an toàn
            // hơn gán bừa 1 mã phường sai tỉnh.
            // Tên thật trong DB có tiền tố "Tỉnh "/"Thành phố " (vd "Tỉnh Vĩnh Long") — dùng LIKE
            // thay vì so khớp tuyệt đối.
            $province = Province::where('name', 'like', '%' . $branch['province_name'] . '%')->first();

            if ($province) {
                ProvinceBranch::updateOrCreate(
                    ['categorie_id' => $category->id],
                    ['province_id' => $province->id, 'ward_code' => null, 'status' => true]
                );
            } else {
                $this->command?->warn("  ⚠ Không tìm thấy tỉnh \"{$branch['province_name']}\" trong DB — chi nhánh {$branch['name']} sẽ KHÔNG hiện ở trang chủ (thiếu ProvinceBranch).");
            }

            $this->command?->info("Đã tạo chi nhánh: {$branch['name']} ({$branch['latitude']}, {$branch['longitude']}) — {$timeSlotIds->count()} khung giờ, ảnh " . ($sourceImageExists ? 'OK' : 'THIẾU') . ', tỉnh ' . ($province ? 'OK' : 'THIẾU'));
        }
    }
}
