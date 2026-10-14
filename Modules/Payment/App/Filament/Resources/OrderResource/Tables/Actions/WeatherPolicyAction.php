<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\OrderResource\Tables\Actions;

use App\Services\WeatherPolicyService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Product\App\Models\TimeSlot;

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
                        'rescheduled' => 'Đổi lịch (dời sang khung giờ/ngày khác, có thu thêm phí đổi lịch)',
                    ])
                    ->default('refunded')
                    ->required()
                    ->live(),
                DatePicker::make('new_date')
                    ->label('Ngày chơi mới')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->minDate(today())
                    ->live()
                    ->afterStateUpdated(fn ($set) => $set('new_timeslot_id', null))
                    ->required(fn ($get) => $get('resolution') === 'rescheduled')
                    ->visible(fn ($get) => $get('resolution') === 'rescheduled'),
                Select::make('new_timeslot_id')
                    ->label('Khung giờ mới')
                    ->helperText('Chỉ hiện các khung giờ của sân còn trống trong ngày đã chọn.')
                    ->options(fn ($get, Order $record) => self::availableSlotOptions($record, $get('new_date')))
                    ->searchable()
                    ->required(fn ($get) => $get('resolution') === 'rescheduled')
                    ->visible(fn ($get) => $get('resolution') === 'rescheduled'),
                TextInput::make('reschedule_fee')
                    ->label('Phí đổi lịch')
                    ->helperText('Đổi lịch sẽ thu thêm phí này. Số tiền được ghi vào khoản phát sinh của đơn; nhập 0 nếu miễn phí.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('VND')
                    ->required(fn ($get) => $get('resolution') === 'rescheduled')
                    ->visible(fn ($get) => $get('resolution') === 'rescheduled'),
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

                    $slot = RoomTimeSlot::with('timeSlot')->find($data['new_timeslot_id'] ?? null);
                    if (! $slot || ! $slot->timeSlot) {
                        Notification::make()->danger()->title('Vui lòng chọn khung giờ mới.')->send();
                        return;
                    }

                    [$newCheckin, $newCheckout] = self::slotRange((string) $data['new_date'], $slot->timeSlot);

                    $result = app(WeatherPolicyService::class)->reschedule(
                        $record,
                        $newCheckin->toDateTimeString(),
                        $newCheckout->toDateTimeString(),
                        $admin,
                        (int) ($data['reschedule_fee'] ?? 0),
                        $slot->timeSlot->label,
                    );

                    if (! $result['ok']) {
                        Notification::make()->danger()->title($result['error'])->send();
                        return;
                    }

                    $fee = (int) ($data['reschedule_fee'] ?? 0);

                    Notification::make()
                        ->success()
                        ->title('Đã dời lịch do thời tiết' . ($fee > 0 ? ' — đã ghi phí đổi lịch ' . number_format($fee) . 'đ vào khoản phát sinh của đơn' : ''))
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Lỗi: ' . $e->getMessage())->send();
                }
            });
    }

    /** [checkin, checkout] của 1 khung giờ vào 1 ngày cụ thể (khung qua đêm → checkout sang ngày sau). */
    private static function slotRange(string $date, TimeSlot $timeSlot): array
    {
        $day      = Carbon::parse($date)->startOfDay();
        $checkin  = $day->copy()->setTimeFromTimeString((string) $timeSlot->start_time);
        $checkout = $day->copy()->setTimeFromTimeString((string) $timeSlot->end_time);

        if ($checkout->lte($checkin)) {
            $checkout->addDay();
        }

        return [$checkin, $checkout];
    }

    /** Các khung giờ của sân (của đơn) còn trống vào ngày $date — loại trừ chính đơn đang dời. */
    private static function availableSlotOptions(Order $order, mixed $date): array
    {
        if (blank($date)) {
            return [];
        }

        $productId = $order->items()->whereNotNull('product_id')->value('product_id');
        if (! $productId) {
            return [];
        }

        $options = [];

        foreach (RoomTimeSlot::with('timeSlot')->where('room_id', $productId)->get() as $rts) {
            if (! $rts->timeSlot) {
                continue;
            }

            [$in, $out] = self::slotRange((string) $date, $rts->timeSlot);

            if ($in->lt(now())) {
                continue;
            }

            $taken = OrderItem::where('product_id', $productId)
                ->whereNotNull('checkin_date')
                ->where('checkin_date', '<', $out)
                ->where('checkout_date', '>', $in)
                ->where('order_id', '!=', $order->id)
                ->whereHas('order', fn ($q) => $q->whereIn('status', ['pending', 'paid', 'deposit', 'shipped']))
                ->exists();

            if (! $taken) {
                $options[$rts->id] = $rts->timeSlot->label . ' (' . $in->format('H:i') . ' - ' . $out->format('H:i') . ') — ' . number_format((int) $rts->price) . 'đ';
            }
        }

        return $options;
    }
}
