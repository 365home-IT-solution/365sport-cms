<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\OrderResource\Tables\Actions;

use App\Services\WeatherPolicyService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Modules\Payment\Entities\Order;

// Hoãn/hoàn tiền do THỜI TIẾT cho đơn sân NGOÀI TRỜI (room_types.is_outdoor, qua Product::
// isOutdoor()) — trước đây trời mưa
// làm khách không chơi được phải xử lý tay ngoài hệ thống (gọi điện/nhắn tin), không có nút nào ở
// admin panel. Toàn bộ logic điều kiện áp dụng/ghi DB nằm ở App\Services\WeatherPolicyService —
// action này chỉ hiện form chọn "Hoàn tiền" hay "Đổi lịch" rồi gọi thẳng service, cùng khuôn mẫu
// với cancel_order/confirmBankTransferAction() ở OrderTable.php (form nhỏ trong modal xác nhận).
class WeatherPolicyAction
{
    public static function make(): Action
    {
        return Action::make('weather_policy')
            ->label('Hoãn/hoàn tiền do thời tiết')
            ->icon('heroicon-o-cloud')
            ->color('warning')
            ->visible(function (Order $record): bool {
                if (! is_null($record->cancelled_at) || $record->isWeatherAffected()) {
                    return false;
                }

                if (! in_array($record->status, ['paid', 'deposit'], true)) {
                    return false;
                }

                $record->loadMissing('items.product.roomType');

                return $record->items->contains(fn ($item) => $item->product?->isOutdoor() ?? false);
            })
            ->form([
                Radio::make('resolution')
                    ->label('Cách xử lý')
                    ->options([
                        'refunded'    => 'Hoàn tiền toàn bộ (huỷ, không giữ chỗ nữa)',
                        'rescheduled' => 'Đổi lịch (dời sang khung giờ/ngày khác, không thu/hoàn thêm gì)',
                    ])
                    ->default('refunded')
                    ->required()
                    ->live(),
                DateTimePicker::make('new_checkin_date')
                    ->label('Ngày giờ nhận sân mới')
                    ->seconds(false)
                    ->native(false)
                    ->required(fn ($get) => $get('resolution') === 'rescheduled')
                    ->visible(fn ($get) => $get('resolution') === 'rescheduled'),
                DateTimePicker::make('new_checkout_date')
                    ->label('Ngày giờ trả sân mới')
                    ->seconds(false)
                    ->native(false)
                    ->required(fn ($get) => $get('resolution') === 'rescheduled')
                    ->visible(fn ($get) => $get('resolution') === 'rescheduled')
                    ->after('new_checkin_date'),
            ])
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => 'Hoãn/hoàn tiền do thời tiết — Đơn #' . $record->order_code)
            ->modalDescription('Chỉ áp dụng cho đơn sân ngoài trời có ngày nhận sân trong vài ngày tới. Hoàn tiền sẽ ghi nhận "chờ hoàn tiền" (xử lý tiếp ở trang Hoàn tiền chờ xử lý), đổi lịch sẽ dời khung giờ/ngày nếu còn trống.')
            ->modalSubmitActionLabel('Xác nhận')
            ->action(function (array $data, Order $record) {
                $admin = auth()->user();

                try {
                    if ($data['resolution'] === 'refunded') {
                        $result = app(WeatherPolicyService::class)->refund($record, $admin);

                        if (! $result['ok']) {
                            Notification::make()->danger()->title($result['error'])->send();
                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Đã ghi nhận hoàn tiền do thời tiết' . ($result['refund_amount'] > 0 ? ' — chờ xử lý ở trang Hoàn tiền chờ xử lý' : ''))
                            ->send();

                        return;
                    }

                    $result = app(WeatherPolicyService::class)->reschedule(
                        $record,
                        (string) $data['new_checkin_date'],
                        (string) $data['new_checkout_date'],
                        $admin,
                    );

                    if (! $result['ok']) {
                        Notification::make()->danger()->title($result['error'])->send();
                        return;
                    }

                    Notification::make()->success()->title('Đã dời lịch do thời tiết')->send();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Lỗi: ' . $e->getMessage())->send();
                }
            });
    }
}
