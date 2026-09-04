<?php

declare(strict_types=1);

namespace Modules\Book\App\Filament\Resources\PriceBoardResource\Tables;

use App\Services\PriceBoardSyncService;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Modules\Book\App\Filament\Resources\PriceBoardResource;
use Modules\Product\App\Models\PriceBoard;
use Modules\Product\App\Models\PriceBoardPriceLog;

// Port từ 365home-cms.
class PriceBoardTable
{
    public static function table(Table $table): Table
    {
        // Chia sẻ giữa before()/after() của DeleteAction bên dưới.
        $affectedProducts = collect();

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tên bảng giá')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Số sân áp dụng')
                    ->counts('items')
                    ->badge(),

                TextColumn::make('start_date')
                    ->label('Ngày bắt đầu')
                    ->date('d/m/Y')
                    ->placeholder('Không giới hạn'),

                TextColumn::make('end_date')
                    ->label('Ngày kết thúc')
                    ->date('d/m/Y')
                    ->placeholder('Không giới hạn'),

                TextColumn::make('trang_thai')
                    ->label('Trạng thái')
                    ->getStateUsing(fn (PriceBoard $record) => match (true) {
                        ! $record->is_active => 'Đã tắt',
                        $record->coversDate() => 'Đang áp dụng',
                        $record->start_date && now()->startOfDay()->lt($record->start_date) => 'Chờ áp dụng',
                        default => 'Hết hạn',
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Đang áp dụng' => 'success',
                        'Chờ áp dụng'  => 'warning',
                        'Hết hạn'      => 'gray',
                        default        => 'danger',
                    }),

                ToggleColumn::make('is_active')
                    ->label('Kích hoạt')
                    ->disabled(fn (PriceBoard $record) => ! PriceBoardResource::canEdit($record))
                    ->afterStateUpdated(function (PriceBoard $record) {
                        $service = app(PriceBoardSyncService::class);

                        // Bật lại phải qua đúng kiểm tra trùng ngày như khi Tạo/Sửa.
                        if ($record->is_active) {
                            try {
                                $service->assertNoOverlap($record);
                            } catch (\RuntimeException $e) {
                                $record->update(['is_active' => false]);

                                Notification::make()
                                    ->title('Không thể kích hoạt')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        // Bật/tắt phải có hiệu lực NGAY — không chờ job price-boards:sync-due chạy
                        // lúc nửa đêm mới thấy giá đổi.
                        $service->resyncBoardProducts($record);

                        Notification::make()
                            ->title($record->is_active ? 'Đã kích hoạt, giá đã được áp dụng ngay' : 'Đã tắt, giá đã được khôi phục')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('history')
                    ->label('Lịch sử')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn (PriceBoard $record) => 'Lịch sử thay đổi giá — ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng')
                    ->modalContent(fn (PriceBoard $record) => view('book::filament.resources.price-board-resource.history-modal', [
                        'logs' => PriceBoardPriceLog::where('price_board_id', $record->id)
                            ->with(['product', 'changedByUser'])
                            ->orderByDesc('created_at')
                            ->limit(200)
                            ->get(),
                    ])),
                Action::make('apply_now')
                    ->label('Áp dụng ngay')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Áp ngay giá của bảng này xuống các sân đã chọn, bất kể ngày hiệu lực.')
                    ->visible(fn (PriceBoard $record) => PriceBoardResource::canEdit($record))
                    ->action(function (PriceBoard $record) {
                        app(PriceBoardSyncService::class)->applyBoard($record);

                        Notification::make()
                            ->title('Đã áp dụng bảng giá "' . $record->name . '"')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                // Tính lại giá ĐÚNG cho mọi sân trong bảng SAU khi xoá — xem giải thích ở
                // App\Services\PriceBoardSyncService::resyncProductsAfterBoardDeleted().
                DeleteAction::make()
                    ->before(function (PriceBoard $record) use (&$affectedProducts) {
                        $affectedProducts = app(PriceBoardSyncService::class)->productsAffectedByBoard($record);
                    })
                    ->after(function () use (&$affectedProducts) {
                        app(PriceBoardSyncService::class)->resyncProductsAfterBoardDeleted($affectedProducts);
                    }),
            ]);
    }
}
