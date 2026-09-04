<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\PackageOrderResource\Pages;

use App\Models\CustomerPackage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Payment\App\Filament\Resources\OrderResource\Tables\OrderTable;
use Modules\Payment\App\Filament\Resources\PackageOrderResource;
use Modules\Payment\Entities\Order;

class EditPackageOrder extends EditRecord
{
    protected static string $resource = PackageOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Header action page-level BẮT BUỘC \Filament\Actions\Action (khác Filament\Tables\
            // Actions\Action dùng ở table row) — xem giải thích chi tiết tại
            // OrderTable::confirmBankTransferAction() (comment gốc, cùng lý do áp dụng ở đây).
            OrderTable::confirmBankTransferAction(\Filament\Actions\Action::class)
                ->record(fn () => $this->record),

            // Xác nhận thanh toán tay cho MỌI phương thức (PayOS, tiền mặt...) — không chỉ chuyển
            // khoản ngân hàng. Xem PackageOrderResource::markPaidAction().
            PackageOrderResource::markPaidAction(\Filament\Actions\Action::class)
                ->record(fn () => $this->record),

            DeleteAction::make(),
        ];
    }

    // Nạp lại 3 field ẢO "Gói đã kích hoạt" (không map cột nào của Order) từ CustomerPackage đã
    // kích hoạt của đơn này — cùng pattern 'booking_partner_id' ảo mà EditOrder.php đang dùng.
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Order $record */
        $record = $this->record;
        $cp = CustomerPackage::where('order_id', $record->id)->first();

        if ($cp) {
            $data['cp_sessions_total']     = $cp->sessions_total;
            $data['cp_sessions_remaining'] = $cp->sessions_remaining;
            $data['cp_expires_at']         = $cp->expires_at;
        }

        return $data;
    }

    // Lưu ngược 'cp_sessions_remaining'/'cp_expires_at' xuống ĐÚNG bản ghi CustomerPackage đã kích
    // hoạt (không phải cột nào trên Order) — 'status' đã cố tình dehydrated(false) trên field Select
    // (tự ghi ngay lúc đổi qua afterStateUpdated(), xem PackageOrderResource::form()) nên không có
    // mặt trong $data ở đây, không lo ghi đè lại.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $sessionsRemaining = $data['cp_sessions_remaining'] ?? null;
        $expiresAt         = $data['cp_expires_at'] ?? null;
        unset($data['cp_sessions_total'], $data['cp_sessions_remaining'], $data['cp_expires_at']);

        $record->update($data);

        $cp = CustomerPackage::where('order_id', $record->id)->first();
        if ($cp && $sessionsRemaining !== null) {
            $cp->update([
                'sessions_remaining' => (int) $sessionsRemaining,
                'expires_at'         => $expiresAt,
            ]);
        }

        return $record;
    }
}
