<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\OrderResource\Tables\Actions;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Modules\Payment\Entities\Order;

/**
 * Đánh dấu khách ĐÃ đặt sân (paid/deposit) nhưng KHÔNG đến nhận sân — khác hẳn "Huỷ đơn"
 * (cancel_order ở OrderTable.php: khách/admin chủ động huỷ TRƯỚC giờ nhận, giải phóng khung giờ).
 * Ở đây khung giờ ĐÃ trôi qua mà khách không xuất hiện, nên chỉ hiện khi:
 *   - Đơn đã thanh toán (paid/deposit) — đơn pending/failed/cancelled chưa từng thật sự giữ chỗ.
 *   - Khung giờ/ngày ĐẦU TIÊN của đơn đã QUA (checkin_date đã ở quá khứ) — chưa tới giờ thì chưa
 *     biết khách có đến hay không, đánh dấu sớm là vô nghĩa.
 *   - CHƯA check-in (checked_in_at null) — đã check-in nghĩa là khách CÓ đến, không phải no-show.
 *   - CHƯA từng đánh dấu no-show / CHƯA bị huỷ (is_null no_show_at, is_null cancelled_at).
 * Toàn bộ logic ghi dữ liệu (tăng Customer.no_show_count, chặn double-mark) nằm ở
 * Order::markNoShow() — dùng CHUNG với App\Http\Controllers\Api\Admin\OrderController::markNoShow()
 * để Filament admin và API admin luôn xử lý y hệt nhau, action này chỉ lo phần hiển thị/xác nhận.
 */
class MarkNoShowAction
{
    public static function make(): Action
    {
        return Action::make('mark_no_show')
            ->label('Đánh dấu không đến')
            ->color('gray')
            ->icon('heroicon-o-user-minus')
            ->visible(function (Order $record): bool {
                if (! is_null($record->cancelled_at) || ! is_null($record->no_show_at)) {
                    return false;
                }
                if (! in_array($record->status, ['paid', 'deposit'], true)) {
                    return false;
                }
                if (! is_null($record->checked_in_at)) {
                    return false;
                }

                $earliestCheckin = $record->items->pluck('checkin_date')->filter()->min();

                return $earliestCheckin && $earliestCheckin->isPast();
            })
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => 'Đánh dấu không đến — Đơn #' . $record->order_code)
            ->modalDescription('Đơn vẫn giữ nguyên trạng thái thanh toán (không đổi status, không hoàn tiền) — chỉ ghi nhận khách đã không đến nhận sân, dùng để theo dõi/thống kê.')
            ->modalSubmitActionLabel('Xác nhận không đến')
            ->action(function (Order $record): void {
                $result = $record->markNoShow(auth()->user());

                if (! $result['ok']) {
                    Notification::make()
                        ->warning()
                        ->title($result['reason'] === 'already_marked'
                            ? 'Đơn này đã được đánh dấu không đến trước đó.'
                            : 'Chỉ đánh dấu được cho đơn đã thanh toán (paid/deposit).')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Đã đánh dấu không đến — Đơn #' . $record->order_code)
                    ->send();
            });
    }
}
