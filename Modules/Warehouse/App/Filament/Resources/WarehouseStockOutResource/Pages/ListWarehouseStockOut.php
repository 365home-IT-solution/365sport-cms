<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Filament\Resources\WarehouseStockOutResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Warehouse\App\Exports\WarehouseStockOutExport;
use Modules\Warehouse\App\Filament\Resources\WarehouseStockOutResource;

class ListWarehouseStockOut extends ListRecords
{
    protected static string $resource = WarehouseStockOutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Xuất ĐÚNG những gì đang lọc/tìm trên bảng lúc bấm (kể cả bộ lọc Chi nhánh) — xem
            // giải thích chung ở ListWarehouseItem::exportItemsExcel.
            Action::make('exportStockOutsExcel')
                ->label('Xuất Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(
                    new WarehouseStockOutExport($this->getFilteredTableQuery(), showPartnerColumn: auth()->user()?->isSuperAdmin() ?? false),
                    'phieu-xuat-kho-' . now()->format('Y-m-d_His') . '.xlsx'
                )),

            CreateAction::make(),
        ];
    }
}
