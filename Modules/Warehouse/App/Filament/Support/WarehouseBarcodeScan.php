<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Filament\Support;

use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Modules\Warehouse\App\Models\WarehouseItem;

// Field dùng chung cho 3 form Nhập/Xuất/Kiểm kê kho — quét mã QR/vạch (camera điện thoại/webcam
// hoặc máy quét vật lý gõ như bàn phím) để tự điền vật tư vào Repeater 'items', thay vì phải tìm
// tay từng vật tư trong danh sách dài. Đặt NGAY PHÍA TRÊN Repeater::make('items') trong mỗi form.
class WarehouseBarcodeScan
{
    // Id cố định để barcode-camera.blade.php (JS) tìm đúng input này mà đẩy giá trị quét được vào.
    public const INPUT_ID = 'warehouse-barcode-scan-input';

    public static function field(\Closure $newRowFactory, string $quantityField = 'quantity'): TextInput
    {
        return TextInput::make('barcode_scan')
            ->label('Quét mã vật tư')
            ->id(self::INPUT_ID)
            ->placeholder('Quét mã QR/vạch hoặc gõ SKU rồi Enter...')
            ->dehydrated(false)
            ->live(onBlur: true)
            ->suffixIcon('heroicon-o-camera')
            ->suffixAction(
                Action::make('open_barcode_camera')
                    ->icon('heroicon-o-camera')
                    ->modalHeading('Quét mã bằng camera')
                    ->modalContent(fn () => view('warehouse::filament.forms.barcode-camera'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng')
            )
            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) use ($newRowFactory, $quantityField) {
                $code = trim((string) $state);
                $set('barcode_scan', null);

                if ($code === '') {
                    return;
                }

                static::handle($code, $set, $get, $newRowFactory, $quantityField);
            });
    }

    private static function handle(string $code, Set $set, Get $get, \Closure $newRowFactory, string $quantityField): void
    {
        $branchId = $get('branch_id');

        $item = WarehouseItem::query()
            ->where('sku', $code)
            ->where('status', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->first();

        if (! $item) {
            Notification::make()
                ->title("Không tìm thấy vật tư với mã: {$code}")
                ->body('Kiểm tra lại mã vạch, hoặc mã này chưa được khai báo ở "Mã SKU" của vật tư.')
                ->danger()
                ->send();

            return;
        }

        $items = $get('items') ?? [];

        foreach ($items as $key => $row) {
            if ((string) ($row['warehouse_item_id'] ?? null) === (string) $item->id) {
                $items[$key][$quantityField] = (float) ($row[$quantityField] ?? 0) + 1;

                if (array_key_exists('checked', $row)) {
                    $items[$key]['checked'] = true;
                }

                $set('items', $items);

                Notification::make()
                    ->title("Đã +1: {$item->name}")
                    ->success()
                    ->send();

                return;
            }
        }

        $items[(string) Str::uuid()] = $newRowFactory($item, $get);
        $set('items', $items);

        Notification::make()
            ->title("Đã thêm: {$item->name}")
            ->success()
            ->send();
    }
}
