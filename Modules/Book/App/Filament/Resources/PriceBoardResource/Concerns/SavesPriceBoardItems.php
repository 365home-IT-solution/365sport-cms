<?php

declare(strict_types=1);

namespace Modules\Book\App\Filament\Resources\PriceBoardResource\Concerns;

use App\Services\PriceBoardSyncService;
use Modules\Product\App\Models\PriceBoard;

// Port từ 365home-cms — uỷ quyền việc lưu dữ liệu sân/giá của form PriceBoardResource cho
// App\Services\PriceBoardSyncService::saveItems() (logic thật nằm ở service, dùng chung được cho
// cả Create/Edit).
trait SavesPriceBoardItems
{
    private function savePriceBoardItems(PriceBoard $board, array $data): void
    {
        app(PriceBoardSyncService::class)->saveItems($board, $data);
    }
}
