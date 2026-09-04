<?php

namespace App\Console\Commands\PriceBoards;

use App\Services\PriceBoardSyncService;
use Illuminate\Console\Command;
use Modules\Product\App\Models\Product;

/**
 * Port từ 365home-cms — chạy 1 LẦN sau khi deploy migration bảng giá: tạo "Bảng giá mặc định" từ
 * dữ liệu products/room_time_slots hiện có của từng sân, để trang "Hệ thống giá" và luồng đặt sân
 * không bị gián đoạn (chưa chạy lệnh này thì PriceBoardSyncService::applyForProduct() không có gì
 * để khôi phục về khi 1 bảng giá đặt tên hết hạn, với sân chưa từng gắn bảng nào).
 */
class SeedDefaultBoards extends Command
{
    protected $signature = 'price-boards:seed-defaults';

    protected $description = 'Khởi tạo bảng giá mặc định cho toàn bộ sân từ dữ liệu giá hiện có (chạy 1 lần)';

    public function handle(PriceBoardSyncService $service): int
    {
        $count = 0;

        Product::query()->chunkById(100, function ($products) use ($service, &$count) {
            foreach ($products as $product) {
                $service->seedDefaultBoard($product);
                $count++;
            }
        });

        $this->info("Đã tạo bảng giá mặc định cho {$count} sân.");

        return self::SUCCESS;
    }
}
