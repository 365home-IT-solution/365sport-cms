<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\OrderResource\Tables\Actions;

use App\Services\OrderLifecycleService;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Modules\Payment\Entities\Order;

// Đổi giờ cho đơn đã thanh toán: dịch toàn bộ khung giờ sang giờ bắt đầu mới (cùng sân, cùng độ dài). Khung mới phải có trong danh
// mục khung giờ của sân và còn trống. Nhân viên được phép đổi sang khung đắt hơn (phần chênh thu riêng qua "Thu thêm").
// Logic ở App\Services\OrderLifecycleService::reschedule().
class RescheduleAction
{
    public static function make(): Action
    {
        return Action::make('reschedule')
            ->label('Đổi giờ')
            ->color('warning')
            ->icon('heroicon-o-calendar-days')
            ->visible(fn (Order $record): bool => in_array($record->status, ['paid', 'deposit'], true)
                && is_null($record->cancelled_at) && is_null($record->no_show_at)
                && in_array($record->order_status ?? 'pending', ['pending'], true))
            ->form([
                DateTimePicker::make('new_start')->label('Giờ bắt đầu mới')->required()->seconds(false)->minDate(now()),
                Toggle::make('allow_price_difference')->label('Cho phép khung mới đắt hơn (thu phần chênh riêng)')->default(true),
            ])
            ->modalHeading(fn (Order $record) => 'Đổi giờ — Đơn #' . $record->order_code)
            ->modalSubmitActionLabel('Đổi giờ')
            ->action(function (array $data, Order $record): void {
                $result = app(OrderLifecycleService::class)->reschedule(
                    $record,
                    Carbon::parse($data['new_start']),
                    auth()->user(),
                    false,
                    (bool) ($data['allow_price_difference'] ?? false),
                );

                $result['ok']
                    ? Notification::make()->success()->title('Đã đổi giờ — Đơn #' . $record->order_code)->send()
                    : Notification::make()->warning()->title(self::vi($result['code']))->send();
            });
    }

    private static function vi(string $code): string
    {
        return match ($code) {
            'slot_unavailable' => 'Giờ mới không nằm trong các khung giờ của sân này.',
            'slot_taken' => 'Khung giờ mới đã có người đặt.',
            'price_higher' => 'Khung giờ mới đắt hơn khung hiện tại.',
            'past' => 'Giờ mới phải ở tương lai.',
            'same_time' => 'Giờ mới trùng giờ hiện tại.',
            'already_started' => 'Đơn đã bắt đầu, không thể đổi giờ.',
            default => 'Không thể đổi giờ cho đơn này.',
        };
    }
}
