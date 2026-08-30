<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Warehouse\App\Models\WarehouseItem;

// Nhận thẳng Builder từ trang List (ListWarehouseItem::getFilteredTableQuery()) — xuất ĐÚNG những
// gì đang lọc/tìm trên bảng lúc bấm "Xuất Excel" (kể cả bộ lọc Chi nhánh mới thêm), không phải
// toàn bộ dữ liệu không lọc. Cùng style/logic chống CSV injection với
// Modules\Payment\App\Exports\OrdersExport (header xanh #00B050, prefix ' cho giá trị bắt đầu bằng
// =+-@).
class WarehouseItemExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    public function __construct(
        private readonly Builder $query,
        private readonly bool $showPartnerColumn = false,
    ) {
    }

    public function collection()
    {
        return $this->query->with(['category', 'unit', 'branch', 'partner'])->get();
    }

    public function headings(): array
    {
        $headings = ['SKU', 'Tên vật tư', 'Nhóm', 'Đơn vị tính', 'Tồn kho', 'Đang sử dụng', 'Dự phòng', 'Ngưỡng tối thiểu', 'Đơn giá', 'Đang dùng', 'Ghi chú', 'Chi nhánh'];
        if ($this->showPartnerColumn) {
            $headings[] = 'Đối tác';
        }

        return $headings;
    }

    public function map($item): array
    {
        $row = [
            $this->sanitize($item->sku),
            $this->sanitize($item->name),
            $this->sanitize($item->category?->name),
            $this->sanitize($item->unit?->name),
            (float) $item->quantity,
            (float) $item->quantity_in_use,
            (float) $item->quantity_reserve,
            (float) $item->min_quantity,
            (float) $item->unit_price,
            $item->status ? 'Có' : 'Không',
            $this->sanitize($item->description, true),
            $this->sanitize($item->branch?->name),
        ];

        if ($this->showPartnerColumn) {
            $row[] = $this->sanitize($item->partner?->name);
        }

        return $row;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $lastCol    = $this->showPartnerColumn ? 'M' : 'L';

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '00B050']],
                    'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
                ]);

                if ($highestRow >= 2) {
                    $sheet->getStyle("A1:{$lastCol}{$highestRow}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => 'thin']],
                        'alignment' => ['vertical' => 'center'],
                    ]);
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    private function sanitize($value, bool $multiline = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;
        $value = $multiline
            ? preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value)
            : preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        if (preg_match('/^[=+\-@]/', $value)) {
            $value = "'" . $value;
        }

        return $value;
    }
}
