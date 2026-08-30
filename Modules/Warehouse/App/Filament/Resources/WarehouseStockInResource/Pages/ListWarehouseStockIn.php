<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Filament\Resources\WarehouseStockInResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Warehouse\App\Exports\WarehouseStockInExport;
use Modules\Warehouse\App\Filament\Resources\WarehouseStockInResource;

class ListWarehouseStockIn extends ListRecords
{
    protected static string $resource = WarehouseStockInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Xuất ĐÚNG những gì đang lọc/tìm trên bảng lúc bấm (kể cả bộ lọc Chi nhánh) — xem
            // giải thích chung ở ListWarehouseItem::exportItemsExcel.
            Action::make('exportStockInsExcel')
                ->label('Xuất Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(
                    new WarehouseStockInExport($this->getFilteredTableQuery(), showPartnerColumn: auth()->user()?->isSuperAdmin() ?? false),
                    'phieu-nhap-kho-' . now()->format('Y-m-d_His') . '.xlsx'
                )),

            CreateAction::make(),
        ];
    }
}
