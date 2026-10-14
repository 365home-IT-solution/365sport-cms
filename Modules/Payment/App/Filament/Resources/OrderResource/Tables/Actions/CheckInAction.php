<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\OrderResource\Tables\Actions;

use App\Services\OrderLifecycleService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Modules\Payment\Entities\Order;

// Nhận sân (check-in): nhân viên bấm khi khách đến, hoặc nhập mã nhận sân khách đọc/quét. Toàn bộ điều kiện và ghi dữ liệu nằm ở
// App\Services\OrderLifecycleService::checkIn() — dùng CHUNG với API admin (POST /api/admin/orders/{code}/check-in).
class CheckInAction
{
    public static function make(): Action
    {
        return Action::make('check_in')
            ->label('Nhận sân')
            ->color('success')
            ->icon('heroicon-o-arrow-right-end-on-rectangle')
            ->visible(fn (Order $record): bool => in_array($record->status, ['paid', 'deposit'], true)
                && is_null($record->cancelled_at) && is_null($record->no_show_at)
                && in_array($record->order_status ?? 'pending', ['pending'], true))
            ->form([
                TextInput::make('code')->label('Mã nhận sân của khách (không bắt buộc)')->maxLength(8)
                    ->helperText('Khách xem mã này trong app ở mục đơn của họ. Để trống nếu xác nhận trực tiếp tại quầy.'),
                Toggle::make('force')->label('Bỏ qua khung giờ nhận sân (chỉ dùng để sửa sai sót)')->default(false),
            ])
            ->modalHeading(fn (Order $record) => 'Nhận sân — Đơn #' . $record->order_code)
            ->modalSubmitActionLabel('Xác nhận nhận sân')
            ->action(function (array $data, Order $record): void {
                if (filled($data['code'] ?? null) && ! hash_equals($record->checkInCode(), strtoupper(trim($data['code'])))) {
                    Notification::make()->danger()->title('Mã nhận sân không khớp với đơn này.')->send();

                    return;
                }

                $result = app(OrderLifecycleService::class)->checkIn($record, auth()->user(), (bool) ($data['force'] ?? false));

                $result['ok']
                    ? Notification::make()->success()->title('Đã nhận sân — Đơn #' . $record->order_code)->send()
                    : Notification::make()->warning()->title(self::vi($result['code']))->send();
            });
    }

    private static function vi(string $code): string
    {
        return match ($code) {
            'too_early' => 'Chưa tới giờ nhận sân (mở trước giờ chơi ' . OrderLifecycleService::CHECKIN_EARLY_MINUTES . ' phút).',
            'too_late' => 'Đã quá giờ chơi của đơn này.',
            'already_checked_in' => 'Đơn này đã nhận sân.',
            'already_checked_out' => 'Đơn này đã trả sân.',
            'cancelled' => 'Đơn đã huỷ.',
            'no_show' => 'Đơn đã bị đánh dấu không đến.',
            'refunded' => 'Đơn đã được hoàn tiền do thời tiết.',
            default => 'Không thể nhận sân cho đơn này.',
        };
    }
}
