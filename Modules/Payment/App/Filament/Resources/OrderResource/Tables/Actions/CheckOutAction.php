<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources\OrderResource\Tables\Actions;

use App\Services\OrderLifecycleService;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Modules\Payment\Entities\Order;

// Trả sân (check-out): chỉ hiện với đơn đã nhận sân. Logic ở App\Services\OrderLifecycleService::checkOut().
class CheckOutAction
{
    public static function make(): Action
    {
        return Action::make('check_out')
            ->label('Trả sân')
            ->color('gray')
            ->icon('heroicon-o-arrow-left-start-on-rectangle')
            ->visible(fn (Order $record): bool => in_array($record->order_status, ['checked_in', 'staying'], true))
            ->requiresConfirmation()
            ->modalHeading(fn (Order $record) => 'Trả sân — Đơn #' . $record->order_code)
            ->modalSubmitActionLabel('Xác nhận trả sân')
            ->action(function (Order $record): void {
                $result = app(OrderLifecycleService::class)->checkOut($record, auth()->user());

                $result['ok']
                    ? Notification::make()->success()->title('Đã trả sân — Đơn #' . $record->order_code)->send()
                    : Notification::make()->warning()->title('Không thể trả sân cho đơn này.')->send();
            });
    }
}
