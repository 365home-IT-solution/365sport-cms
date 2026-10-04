<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 34 tỉnh/thành theo địa giới hành chính 2025 (mã `code` = mã provinces.open-api.vn v2, khớp
 * WardSeeder::PROVINCE_CODES) kèm toạ độ trung tâm — dùng cho lọc "Khu vực", tìm tỉnh gần nhất
 * theo GPS và SportVenueDemoSeeder. Trước đây bảng này chỉ nạp được bằng nút import trong admin
 * (gọi API ngoài) nên cài mới từ đầu không có tỉnh nào. Chạy lại an toàn (updateOrCreate theo code).
 */
class ProvinceSeeder extends Seeder
{
    // [code, tên, loại đơn vị, lat, lng]
    private const PROVINCES = [
        [1,  'Hà Nội',    'thành phố trung ương', 21.0285, 105.8542],
        [4,  'Cao Bằng',  'tỉnh', 22.6666, 106.2639],
        [8,  'Tuyên Quang', 'tỉnh', 21.8237, 105.2280],
        [11, 'Điện Biên', 'tỉnh', 21.3856, 103.0169],
        [12, 'Lai Châu',  'tỉnh', 22.3964, 103.4590],
        [14, 'Sơn La',    'tỉnh', 21.3256, 103.9188],
        [15, 'Lào Cai',   'tỉnh', 22.4809, 103.9755],
        [19, 'Thái Nguyên', 'tỉnh', 21.5928, 105.8442],
        [20, 'Lạng Sơn',  'tỉnh', 21.8537, 106.7615],
        [22, 'Quảng Ninh', 'tỉnh', 20.9599, 107.0416],
        [24, 'Bắc Ninh',  'tỉnh', 21.1861, 106.0763],
        [25, 'Phú Thọ',   'tỉnh', 21.3227, 105.4020],
        [31, 'Hải Phòng', 'thành phố trung ương', 20.8449, 106.6881],
        [33, 'Hưng Yên',  'tỉnh', 20.6464, 106.0511],
        [37, 'Ninh Bình', 'tỉnh', 20.2506, 105.9745],
        [38, 'Thanh Hóa', 'tỉnh', 19.8067, 105.7852],
        [40, 'Nghệ An',   'tỉnh', 18.6796, 105.6813],
        [42, 'Hà Tĩnh',   'tỉnh', 18.3560, 105.8877],
        [44, 'Quảng Trị', 'tỉnh', 16.8163, 107.1003],
        [46, 'Huế',       'thành phố trung ương', 16.4637, 107.5909],
        [48, 'Đà Nẵng',   'thành phố trung ương', 16.0544, 108.2022],
        [51, 'Quảng Ngãi', 'tỉnh', 15.1214, 108.8044],
        [52, 'Gia Lai',   'tỉnh', 13.9833, 108.0000],
        [56, 'Khánh Hòa', 'tỉnh', 12.2388, 109.1967],
        [66, 'Đắk Lắk',   'tỉnh', 12.6667, 108.0383],
        [68, 'Lâm Đồng',  'tỉnh', 11.9404, 108.4583],
        [75, 'Đồng Nai',  'tỉnh', 10.9450, 106.8241],
        [79, 'Hồ Chí Minh', 'thành phố trung ương', 10.8231, 106.6297],
        [80, 'Tây Ninh',  'tỉnh', 11.3100, 106.0989],
        [82, 'Đồng Tháp', 'tỉnh', 10.4938, 105.6881],
        [86, 'Vĩnh Long', 'tỉnh', 10.2537, 105.9722],
        [91, 'An Giang',  'tỉnh', 10.3860, 105.4351],
        [92, 'Cần Thơ',   'thành phố trung ương', 10.0452, 105.7469],
        [96, 'Cà Mau',    'tỉnh', 9.1769, 105.1524],
    ];

    public function run(): void
    {
        foreach (self::PROVINCES as [$code, $name, $type, $lat, $lng]) {
            $prefix = $type === 'tỉnh' ? 'Tỉnh' : 'Thành phố';
            $slug   = Str::slug($prefix . ' ' . $name); // vd: thanh-pho-ha-noi, tinh-thanh-hoa

            Province::updateOrCreate(['code' => $code], [
                'name'          => $name,
                'slug'          => $slug,
                'division_type' => $type,
                'codename'      => str_replace('-', '_', $slug),
                'lat'           => $lat,
                'lng'           => $lng,
            ]);
        }

        $this->command?->info('Đã nạp ' . count(self::PROVINCES) . ' tỉnh/thành.');
    }
}
