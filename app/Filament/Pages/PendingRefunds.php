<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\ExtraChargeService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\AuditLog\Services\AuditLogger;
use Modules\Payment\Entities\Order;

// Danh sách đơn có tiền cần hoàn nhưng CHƯA hoàn (extra_refund_amount > 0, extra_refund_paid_at
// NULL) — phát sinh từ Huỷ đơn phía admin (OrderTable::cancel_order) hoặc khách tự huỷ
// (OrderCancellationService::cancelByCustomer()). Order đã tự lọc theo đối tác qua
// BelongsToPartner (global scope), không cần lọc tay ở đây.
class PendingRefunds extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-refund';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Hoàn tiền chờ xử lý';

    protected static ?string $title = 'Hoàn tiền chờ xử lý';

    protected static string $view = 'filament.pages.pending-refunds';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_PendingRefunds') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->where('extra_refund_amount', '>', 0)
                    ->whereNull('extra_refund_paid_at')
            )
            ->columns([
                TextColumn::make('order_code')
                    ->label('Mã đơn')
                    ->searchable(),
                TextColumn::make('buyer_name')
                    ->label('Khách hàng')
                    ->searchable(),
                TextColumn::make('buyer_phone')
                    ->label('SĐT')
                    ->searchable(),
                TextColumn::make('extra_refund_amount')
                    ->label('Số tiền cần hoàn')
                    ->numeric()
                    ->money('VND')
                    ->color('danger')
                    ->weight('bold'),
                TextColumn::make('cancel_reason')
                    ->label('Lý do huỷ')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('cancelled_at')
                    ->label('Thời điểm huỷ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Action::make('mark_refunded')
                    ->label('Đánh dấu đã hoàn')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->form([
                        Select::make('method')
                            ->label('Hình thức hoàn tiền')
                            ->options([
                                'cash'     => 'Tiền mặt',
                                'transfer' => 'Chuyển khoản',
                            ])
                            ->default('cash')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => 'Xác nhận đã hoàn tiền — Đơn #' . $record->order_code)
                    ->modalSubmitActionLabel('Xác nhận')
                    ->action(function (array $data, Order $record) {
                        try {
                            $amount = (int) $record->extra_refund_amount;

                            app(ExtraChargeService::class)->markRefundAsDone($record, $amount, $data['method']);

                            AuditLogger::log(
                                'update', 'Order', $record,
                                ['extra_refund_paid_at' => null],
                                [
                                    'extra_refund_paid_at'  => now()->toDateTimeString(),
                                    'extra_refund_method'   => $data['method'],
                                    'extra_refund_amount'   => $amount,
                                ],
                                'Đơn #' . $record->order_code
                            );

                            Notification::make()
                                ->success()
                                ->title('Đã đánh dấu hoàn tiền thành công')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Lỗi: ' . $e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('cancelled_at', 'desc');
    }
}
