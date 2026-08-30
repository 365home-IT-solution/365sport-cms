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
use Modules\Warehouse\App\Models\WarehouseStockCheck;

// Xem giải thích chung ở WarehouseItemExport.
class WarehouseStockCheckExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    public function __construct(
        private readonly Builder $query,
        private readonly bool $showPartnerColumn = false,
    ) {
    }

    public function collection()
    {
        return $this->query->with(['creator', 'branch', 'partner', 'items'])->withCount('items')->get();
    }

    public function headings(): array
    {
        $headings = ['Mã phiếu', 'Ngày kiểm kê', 'Số dòng', 'Tổng chênh lệch', 'Người kiểm kê', 'Trạng thái bàn giao', 'Ghi chú', 'Chi nhánh'];
        if ($this->showPartnerColumn) {
            $headings[] = 'Đối tác';
        }

        return $headings;
    }

    public function map($stockCheck): array
    {
        $handoverLabel = $stockCheck->handover_status
            ? (WarehouseStockCheck::HANDOVER_LABELS[$stockCheck->handover_status] ?? $stockCheck->handover_status)
            : 'Chưa bàn giao';

        $row = [
            $this->sanitize($stockCheck->code),
            $stockCheck->checked_at?->format('d/m/Y H:i'),
            $stockCheck->items_count,
            (float) $stockCheck->items->sum('difference'),
            $this->sanitize(CurrentUserDisplay::forUser($stockCheck->creator)),
            $this->sanitize($handoverLabel),
            $this->sanitize($stockCheck->note, true),
            $this->sanitize($stockCheck->branch?->name),
        ];

        if ($this->showPartnerColumn) {
            $row[] = $this->sanitize($stockCheck->partner?->name);
        }

        return $row;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet      = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $lastCol    = $this->showPartnerColumn ? 'I' : 'H';

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
