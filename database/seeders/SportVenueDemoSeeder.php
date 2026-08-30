<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Province;
use App\Models\ProvinceBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Product\App\Models\TimeSlot;

/**
 * Thêm nhiều chi nhánh/sân mẫu hơn (ngoài 6 sân có sẵn từ PartnerSeeder) để lưới sân ở trang chủ
 * (@livewire('bladethemev1::venue-grid')) có đủ số lượng thẻ hiển thị giống ảnh mẫu tham khảo.
 *
 * Mỗi chi nhánh (Category, category_type=product) có NHIỀU sân con (nhiều Product, giống
 * "Pickleball 1/2/3" trong bảng "ĐẶT LỊCH THEO SÂN") — 1 chi nhánh KHÔNG map 1-1 với 1 Product,
 * xem BranchBookConfig::build() (Modules/BladeThemeV1/Support/BranchBookConfig.php): bảng đặt
 * lịch chỉ hiển thị sân nào có ít nhất 1 dòng room_time_slots (bảng room_time_slots, qua
 * TimeSlot catalog ở bảng time_slots) — Category.checkin_time/checkout_time CHỈ để hiển thị
 * (VenueGrid::formatHours()), KHÔNG tự sinh khung giờ, nên phải tạo thủ công ở đây: mỗi sân con có
 * khung giờ 1 tiếng/khung, trải đều từ giờ mở đến giờ đóng cửa của chi nhánh.
 *
 * Idempotent (updateOrCreate theo slug/khung giờ) — chạy lại nhiều lần không tạo trùng.
 * Chạy: php artisan db:seed --class=SportVenueDemoSeeder
 */
class SportVenueDemoSeeder extends Seeder
{
    private const OPEN_HOUR  = 5;  // 05:00
    private const CLOSE_HOUR = 22; // 22:00
    private const COURTS_PER_VENUE = 3;
    private const PEAK_START_HOUR = 16; // Từ 16h là "giờ vàng" — giá cao hơn khung ban ngày.
    private const PEAK_MULTIPLIER = 1.25;

    // [tên CLB, địa chỉ, lat, lng, giá/khung giờ, slug tỉnh/thành THẬT khớp với địa chỉ — trước
    // đây KHÔNG có cột này, tỉnh được gán xoay vòng theo index (bug: hoàn toàn không liên quan gì
    // tới địa chỉ, khiến lọc "Khu vực" trên /ban-do trả về sai chi nhánh, xem MapPage::mount()).
    // "TP. Vũng Tàu" map vào "thanh-pho-ho-chi-minh" vì Bà Rịa - Vũng Tàu đã sáp nhập vào TP.HCM
    // theo địa giới hành chính mới (không còn Province riêng trong bảng provinces).
    // Cột cuối: code phường/xã thật (App\Models\Ward.code) khớp tên trong địa chỉ — null nếu tên
    // quận/phường CŨ (trước sáp nhập 2025) không còn tồn tại y hệt trong danh mục phường mới.
    private const VENUES = [
        ['Catchy Pickleball Club', 'Q. Hoàng Mai, Hà Nội', 21.0025, 105.8565, 120000, 'thanh-pho-ha-noi', 331],
        ['Family Pickleball',       'Q. Ngô Quyền, Hải Phòng', 20.8590, 106.6890, 100000, 'thanh-pho-hai-phong', null],
        ['Hướng Đỗ Pickleball Central', 'P. Bà Triệu, Thanh Hóa', 19.8067, 105.7764, 110000, 'tinh-thanh-hoa', null],
        ['Sân Pickleball Nhóm Bạn', 'Q. Tân Phú, TP.HCM', 10.7900, 106.6280, 130000, 'thanh-pho-ho-chi-minh', 27031],
        ['USC Tennis Thạnh Mỹ Lợi', 'TP. Thủ Đức, TP.HCM', 10.7620, 106.7480, 180000, 'thanh-pho-ho-chi-minh', 26824],
        ['002 Pickleball Club Thảo Điền', 'Q.2, TP.HCM', 10.8040, 106.7350, 140000, 'thanh-pho-ho-chi-minh', null],
        ['002 Pickleball & Coffee', 'TP. Vũng Tàu', 10.3460, 107.0840, 120000, 'thanh-pho-ho-chi-minh', 26506],
        ['Cầu Lông Golden Bee Trung Tâm', 'Q. Ninh Kiều, Cần Thơ', 10.0340, 105.7880, 90000, 'thanh-pho-can-tho', 31135],
        ['Bóng Đá Mini Sunrise Arena', 'Q. Cái Răng, Cần Thơ', 10.0090, 105.7660, 350000, 'thanh-pho-can-tho', 31186],
        ['Tennis Ocean View Club', 'Q. Bình Thủy, Cần Thơ', 10.0530, 105.7480, 180000, 'thanh-pho-can-tho', 31168],
        ['Pickleball Đại Kim Complex', 'Q. Hoàng Mai, Hà Nội', 20.9740, 105.8280, 120000, 'thanh-pho-ha-noi', 331],
        ['Cầu Lông TDT Thanh Xuân', 'Q. Thanh Xuân, Hà Nội', 20.9970, 105.8020, 90000, 'thanh-pho-ha-noi', 367],
    ];

    public function run(): void
    {
        $partners  = Partner::orderBy('created_at')->limit(3)->get();
        $provinces = Province::whereIn('slug', array_unique(array_column(self::VENUES, 5)))->get()->keyBy('slug');

        if ($partners->isEmpty() || $provinces->isEmpty()) {
            $this->command?->warn('SportVenueDemoSeeder: thiếu Partner/Province mẫu (chạy PartnerSeeder trước) — bỏ qua.');

            return;
        }

        // Khung giờ 1 tiếng, dùng chung cho mọi sân (05:00-22:00 = 17 khung) — catalog time_slots
        // chia sẻ toàn hệ thống (không thuộc riêng sân/chi nhánh nào), nên chỉ cần tạo 1 lần.
        $timeSlots = $this->ensureHourlyTimeSlots();

        // Dọn Product "1 chi nhánh = 1 sân" tạo bởi bản seeder trước (slug "...-demo-san", không
        // có số) — bản hiện tại đổi sang "1 chi nhánh = nhiều sân con" ("...-demo-san-1/2/3"),
        // tránh để lại product cũ không khung giờ, gây trùng/rác trong danh mục.
        foreach (self::VENUES as [$name]) {
            $oldSlug = Str::slug($name) . '-demo-san';
            $old = Product::where('slug', $oldSlug)->first();
            if ($old) {
                $old->categories()->detach();
                $old->delete();
            }
        }

        foreach (self::VENUES as $index => [$name, $address, $lat, $lng, $slotPrice, $provinceSlug, $wardCode]) {
            $partner  = $partners[$index % $partners->count()];
            $province = $provinces[$provinceSlug];
            $slug     = Str::slug($name) . '-demo';

            $category = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'          => $name,
                    'description'   => $address,
                    'category_type' => 'product',
                    'status'        => true,
                    'partner_id'    => $partner->id,
                    'checkin_time'  => sprintf('%02d:00:00', self::OPEN_HOUR),
                    'checkout_time' => sprintf('%02d:00:00', self::CLOSE_HOUR),
                ]
            );

            $coverImage = $this->coverImageFor($name);

            for ($c = 1; $c <= self::COURTS_PER_VENUE; $c++) {
                $courtSlug = $slug . '-san-' . $c;

                // Sân cuối (Sân 3) của các chi nhánh SỐ LẺ là "sân cao cấp" — giá nhỉnh hơn hẳn 2
                // sân còn lại ở MỌI khung giờ, để bảng giá/khung giờ có dữ liệu thật kiểm tra được
                // trường hợp các sân trong cùng chi nhánh KHÁC giá nhau (không được gộp chung 1
                // bảng — xem MapPage::getServicesAndPricing()).
                $isPremiumCourt = $index % 2 === 1 && $c === self::COURTS_PER_VENUE;
                $courtBasePrice = $isPremiumCourt ? (int) round($slotPrice * 1.15 / 5000) * 5000 : $slotPrice;

                $product = Product::updateOrCreate(
                    ['slug' => $courtSlug],
                    [
                        'partner_id'   => $partner->id,
                        'name'         => "{$name} - Sân {$c}" . ($isPremiumCourt ? ' (Cao cấp)' : ''),
                        'address'      => $address,
                        'latitude'     => $lat,
                        'longitude'    => $lng,
                        'price'        => $courtBasePrice,
                        'styles'       => 1, // Sân theo giờ (khác style=2 phòng nghỉ qua đêm)
                        'rating_score' => round(4.0 + ($index % 5) * 0.2, 1),
                        'is_in_stock'  => true,
                        'is_activated' => true,
                    ]
                );

                if (! $product->categories()->where('categories.id', $category->id)->exists()) {
                    $product->categories()->attach($category->id);
                }

                if ($coverImage && ! $product->getFirstMedia('Ảnh bìa')) {
                    $product->addMedia($coverImage)->preservingOriginal()->toMediaCollection('Ảnh bìa');
                }

                foreach ($timeSlots as $timeSlot) {
                    $startHour = (int) substr($timeSlot->start_time, 0, 2);
                    $isPeak    = $startHour >= self::PEAK_START_HOUR;
                    $price     = $isPeak
                        ? (int) round($courtBasePrice * self::PEAK_MULTIPLIER / 5000) * 5000
                        : $courtBasePrice;

                    RoomTimeSlot::updateOrCreate(
                        ['room_id' => $product->id, 'timeslot_id' => $timeSlot->id],
                        [
                            'price'      => $price,
                            'status'     => 'available',
                            'over_night' => false,
                        ]
                    );
                }
            }

            // Khoá theo categorie_id (1 category = đúng 1 chi nhánh) — TRƯỚC ĐÂY khoá theo cả
            // province_id lẫn categorie_id, nên đổi đúng lại tỉnh cho 1 chi nhánh (bug ở trên) chỉ
            // TẠO THÊM 1 dòng ProvinceBranch mới với tỉnh mới, để lại dòng cũ (tỉnh sai) mồ côi
            // trong bảng thay vì cập nhật đè lên nó.
            ProvinceBranch::updateOrCreate(
                ['categorie_id' => $category->id],
                ['province_id' => $province->id, 'ward_code' => $wardCode, 'status' => true]
            );
        }

        $this->command?->info('SportVenueDemoSeeder: đã tạo/cập nhật ' . count(self::VENUES) . ' chi nhánh mẫu, mỗi chi nhánh ' . self::COURTS_PER_VENUE . ' sân, khung giờ ' . self::OPEN_HOUR . 'h-' . self::CLOSE_HOUR . 'h (1h/khung, giá cao hơn từ ' . self::PEAK_START_HOUR . 'h — vài chi nhánh có 1 sân cao cấp giá khác 2 sân còn lại).');
    }

    /**
     * @return \Illuminate\Support\Collection<int, TimeSlot>
     */
    private function ensureHourlyTimeSlots(): \Illuminate\Support\Collection
    {
        $slots = collect();

        for ($h = self::OPEN_HOUR; $h < self::CLOSE_HOUR; $h++) {
            $start = sprintf('%02d:00:00', $h);
            $end   = sprintf('%02d:00:00', $h + 1);

            $slots->push(TimeSlot::firstOrCreate(
                ['start_time' => $start, 'end_time' => $end, 'over_night' => false],
                ['label' => sprintf('%02d:00 - %02d:00', $h, $h + 1), 'type' => null]
            ));
        }

        return $slots;
    }

    /**
     * Tái sử dụng ảnh sân thật đã có sẵn trong media library (từ PartnerSeeder — Ảnh bìa
     * badminton/futsal/tennis/pickleball) theo môn thể thao đoán từ tên CLB, thay vì để trống.
     */
    private function coverImageFor(string $venueName): ?string
    {
        $name = Str::lower($venueName);

        $keywordToFile = [
            'pickleball' => 'pickleball.jpg',
            'tennis'     => 'tennis.jpg',
            'cầu lông'   => 'badminton.jpg',
            'bóng đá'    => 'futsal.jpg',
        ];

        foreach ($keywordToFile as $keyword => $file) {
            if (Str::contains($name, $keyword)) {
                $path = $this->findExistingMediaFile($file);
                if ($path) {
                    return $path;
                }
            }
        }

        return null;
    }

    private function findExistingMediaFile(string $fileName): ?string
    {
        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::where('file_name', $fileName)->first();

        if (! $media) {
            return null;
        }

        $path = $media->getPath();

        return file_exists($path) ? $path : null;
    }
}
