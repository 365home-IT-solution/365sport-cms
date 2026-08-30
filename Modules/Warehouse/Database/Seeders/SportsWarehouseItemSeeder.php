<?php

declare(strict_types=1);

namespace Modules\Warehouse\Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;
use Modules\Warehouse\App\Models\WarehouseCategory;
use Modules\Warehouse\App\Models\WarehouseItem;
use Modules\Warehouse\App\Models\WarehouseUnit;

// Danh mục vật tư khởi tạo dành riêng cho vận hành SÂN THỂ THAO (khác hẳn WarehouseItemSeeder gốc
// — đó là hàng housekeeping/khách sạn thừa kế từ 365home, không hợp với dự án này). CHỈ seed cho
// ĐÚNG 1 đối tác "Mamble Sport" theo yêu cầu — không chạy cho toàn bộ đối tác như seeder gốc, vì
// đây là dữ liệu demo cho 1 tài khoản cụ thể, không phải dữ liệu khởi tạo mặc định cho hệ thống.
class SportsWarehouseItemSeeder extends Seeder
{
    private const PARTNER_NAME = 'Mamble Sport';

    public function run(): void
    {
        $partner = Partner::where('name', self::PARTNER_NAME)->first();

        if (! $partner) {
            $this->command?->warn('Không tìm thấy đối tác "' . self::PARTNER_NAME . '" — bỏ qua seed vật tư sân thể thao.');

            return;
        }

        $categoryCache = [];
        $unitCache     = [];

        foreach ($this->items() as $item) {
            $categoryId = $categoryCache[$item['category']] ??= WarehouseCategory::firstOrCreate(
                ['partner_id' => $partner->id, 'name' => $item['category']]
            )->id;

            $unitId = $unitCache[$item['unit']] ??= WarehouseUnit::firstOrCreate(
                ['partner_id' => $partner->id, 'name' => $item['unit']]
            )->id;

            WarehouseItem::firstOrCreate(
                [
                    'partner_id' => $partner->id,
                    'sku'        => $item['sku'],
                ],
                [
                    'name'                  => $item['name'],
                    'warehouse_category_id' => $categoryId,
                    'warehouse_unit_id'     => $unitId,
                    'quantity'              => $item['quantity'],
                    'min_quantity'          => $item['min_quantity'],
                    'unit_price'            => $item['unit_price'],
                    'status'                => true,
                ]
            );
        }

        $this->command?->info('Đã seed vật tư sân thể thao cho đối tác "' . self::PARTNER_NAME . '".');
    }

    private function items(): array
    {
        return [
            // ── Dụng cụ thi đấu ─────────────────────────────────────────
            ['sku' => 'SP-001', 'category' => 'Dụng cụ thi đấu', 'name' => 'Vợt cầu lông cho thuê',     'unit' => 'cây',  'quantity' => 20, 'min_quantity' => 5,  'unit_price' => 250000],
            ['sku' => 'SP-002', 'category' => 'Dụng cụ thi đấu', 'name' => 'Vợt pickleball cho thuê',   'unit' => 'cây',  'quantity' => 15, 'min_quantity' => 4,  'unit_price' => 350000],
            ['sku' => 'SP-003', 'category' => 'Dụng cụ thi đấu', 'name' => 'Cầu lông (ống)',            'unit' => 'ống',  'quantity' => 50, 'min_quantity' => 10, 'unit_price' => 220000],
            ['sku' => 'SP-004', 'category' => 'Dụng cụ thi đấu', 'name' => 'Bóng pickleball',           'unit' => 'quả',  'quantity' => 100,'min_quantity' => 20, 'unit_price' => 25000],
            ['sku' => 'SP-005', 'category' => 'Dụng cụ thi đấu', 'name' => 'Bóng tennis',               'unit' => 'quả',  'quantity' => 60, 'min_quantity' => 15, 'unit_price' => 30000],
            ['sku' => 'SP-006', 'category' => 'Dụng cụ thi đấu', 'name' => 'Bóng đá mini',              'unit' => 'quả',  'quantity' => 10, 'min_quantity' => 2,  'unit_price' => 350000],
            ['sku' => 'SP-007', 'category' => 'Dụng cụ thi đấu', 'name' => 'Lưới cầu lông',             'unit' => 'tấm',  'quantity' => 6,  'min_quantity' => 2,  'unit_price' => 450000],
            ['sku' => 'SP-008', 'category' => 'Dụng cụ thi đấu', 'name' => 'Lưới pickleball',           'unit' => 'tấm',  'quantity' => 4,  'min_quantity' => 1,  'unit_price' => 850000],

            // ── Vật tư sân bãi ──────────────────────────────────────────
            ['sku' => 'CT-001', 'category' => 'Vật tư sân bãi', 'name' => 'Băng keo kẻ vạch sân',      'unit' => 'cuộn', 'quantity' => 10, 'min_quantity' => 2,  'unit_price' => 120000],
            ['sku' => 'CT-002', 'category' => 'Vật tư sân bãi', 'name' => 'Cọc lưới di động',          'unit' => 'cặp',  'quantity' => 4,  'min_quantity' => 1,  'unit_price' => 950000],
            ['sku' => 'CT-003', 'category' => 'Vật tư sân bãi', 'name' => 'Dung dịch vệ sinh mặt sân', 'unit' => 'chai', 'quantity' => 15, 'min_quantity' => 3,  'unit_price' => 65000],
            ['sku' => 'CT-004', 'category' => 'Vật tư sân bãi', 'name' => 'Chổi cao su gạt nước sân',  'unit' => 'cái',  'quantity' => 6,  'min_quantity' => 2,  'unit_price' => 180000],
            ['sku' => 'CT-005', 'category' => 'Vật tư sân bãi', 'name' => 'Bóng đèn pha sân (LED)',    'unit' => 'bóng', 'quantity' => 12, 'min_quantity' => 3,  'unit_price' => 280000],

            // ── Đồ uống & giải khát ─────────────────────────────────────
            ['sku' => 'DR-001', 'category' => 'Đồ uống & giải khát', 'name' => 'Nước suối chai 500ml',    'unit' => 'chai', 'quantity' => 300, 'min_quantity' => 50, 'unit_price' => 5000],
            ['sku' => 'DR-002', 'category' => 'Đồ uống & giải khát', 'name' => 'Nước điện giải (isotonic)','unit' => 'chai', 'quantity' => 150, 'min_quantity' => 30, 'unit_price' => 12000],
            ['sku' => 'DR-003', 'category' => 'Đồ uống & giải khát', 'name' => 'Trà đá / trà chanh',      'unit' => 'ly',   'quantity' => 200, 'min_quantity' => 40, 'unit_price' => 8000],
            ['sku' => 'DR-004', 'category' => 'Đồ uống & giải khát', 'name' => 'Cà phê đóng lon',         'unit' => 'lon',  'quantity' => 100, 'min_quantity' => 20, 'unit_price' => 12000],

            // ── Vệ sinh & an toàn ───────────────────────────────────────
            ['sku' => 'SF-001', 'category' => 'Vệ sinh & an toàn', 'name' => 'Khăn lau mồ hôi cho thuê',   'unit' => 'cái',  'quantity' => 50, 'min_quantity' => 10, 'unit_price' => 25000],
            ['sku' => 'SF-002', 'category' => 'Vệ sinh & an toàn', 'name' => 'Nước rửa tay khô',           'unit' => 'chai', 'quantity' => 20, 'min_quantity' => 5,  'unit_price' => 30000],
            ['sku' => 'SF-003', 'category' => 'Vệ sinh & an toàn', 'name' => 'Hộp sơ cứu y tế',            'unit' => 'hộp',  'quantity' => 3,  'min_quantity' => 1,  'unit_price' => 250000],
            ['sku' => 'SF-004', 'category' => 'Vệ sinh & an toàn', 'name' => 'Băng cá nhân',               'unit' => 'hộp',  'quantity' => 10, 'min_quantity' => 2,  'unit_price' => 20000],
            ['sku' => 'SF-005', 'category' => 'Vệ sinh & an toàn', 'name' => 'Xịt lạnh giảm đau cơ',       'unit' => 'chai', 'quantity' => 8,  'min_quantity' => 2,  'unit_price' => 65000],

            // ── Lễ tân / Văn phòng phẩm ─────────────────────────────────
            ['sku' => 'OF-101', 'category' => 'Lễ tân / Văn phòng phẩm', 'name' => 'Giấy in hoá đơn nhiệt', 'unit' => 'cuộn', 'quantity' => 20, 'min_quantity' => 5,  'unit_price' => 12000],
            ['sku' => 'OF-102', 'category' => 'Lễ tân / Văn phòng phẩm', 'name' => 'Bút bi',                'unit' => 'cái',  'quantity' => 30, 'min_quantity' => 5,  'unit_price' => 3000],
            ['sku' => 'OF-103', 'category' => 'Lễ tân / Văn phòng phẩm', 'name' => 'Sổ ghi lịch đặt sân',   'unit' => 'cuốn', 'quantity' => 5,  'min_quantity' => 1,  'unit_price' => 25000],
        ];
    }
}
