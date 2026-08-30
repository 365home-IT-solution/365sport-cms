<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use Modules\Warehouse\App\Filament\Support\CurrentUserDisplay;

// Xem giải thích chung ở WarehouseItemExport (nhận Builder đã lọc từ trang List, chống CSV
// injection, style header xanh #00B050).
class WarehouseStockInExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    public function __construct(
        private readonly Builder $query,
        private readonly bool $showPartnerColumn = false,
    ) {
    }

    public function collection()
    {
        return $this->query->with(['creator', 'branch', 'partner'])->withCount('items')->get();
    }

    public function headings(): array
    {
        $headings = ['Mã phiếu', 'Ngày nhập', 'Số dòng hàng', 'Tổng tiền', 'Người nhập', 'Ghi chú', 'Chi nhánh'];
        if ($this->showPartnerColumn) {
            $headings[] = 'Đối tác';
        }

        return $headings;
    }

    public function map($stockIn): array
    {
        $row = [
            $this->sanitize($stockIn->code),
            $stockIn->received_at?->format('d/m/Y H:i'),
            $stockIn->items_count,
            (float) $stockIn->total_amount,
            $this->sanitize(CurrentUserDisplay::forUser($stockIn->creator)),
            $this->sanitize($stockIn->note, true),
            $this->sanitize($stockIn->branch?->name),
        ];

        if ($this->showPartnerColumn) {
            $row[] = $this->sanitize($stockIn->partner?->name);
        }

        return $row;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $lastCol    = $this->showPartnerColumn ? 'H' : 'G';

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
