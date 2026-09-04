<?php

declare(strict_types=1);

namespace Modules\Payment\App\Filament\Resources;

use App\Models\CustomerPackage;
use App\Services\BankTransferConfirmationService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\AuditLog\Services\AuditLogger;
use Modules\Payment\App\Filament\Resources\OrderResource\Tables\OrderTable;
use Modules\Payment\App\Filament\Resources\PackageOrderResource\Pages;
use Modules\Payment\Entities\Order;

// Đơn MUA GÓI nhiều buổi (order_type='package_purchase') — TÁCH HẲN khỏi OrderResource ("BOOKING")
// theo đúng yêu cầu: không chỉ ẩn khỏi danh sách mà còn có Form/Table/trang Sửa RIÊNG, không dùng
// chung form đặt sân (không có khung giờ, check-in/check-out, CCCD... — những thứ
// EditOrder.php xử lý chỉ có ý nghĩa với đơn đặt sân thật). Đơn dạng này chỉ là PHƯƠNG TIỆN THU
// TIỀN để kích hoạt App\Models\CustomerPackage (xem CustomerPackage::activateFromOrder()) — sau khi
// thanh toán xong, xem/quản lý gói ĐÃ KÍCH HOẠT ở App\Filament\Resources\CustomerPackageResource
// ("Khách đã mua gói"), khác hẳn resource này (nơi quản lý CHÍNH ĐƠN THANH TOÁN, trước/trong lúc
// chờ xác nhận tiền).
class PackageOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'package-orders';

    protected static ?string $navigationIcon   = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Đơn mua gói';
    protected static ?string $modelLabel       = 'Đơn mua gói';
    protected static ?string $pluralModelLabel = 'Đơn mua gói';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->where('status', 'pending')->count();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->can('view_any_order') ?? false) && ($user?->partnerHasFeature('orders') ?? false);
    }

    // Không cho tạo tay — đơn mua gói chỉ phát sinh khi khách tự mua trên web (ProductDetail::
    // buyPackage()/Book::buyPackage()), giống hệt quy ước ở CustomerPackageResource::canCreate().
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update_order') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete_order') ?? false;
    }

    // Cùng logic phân quyền partner/chi nhánh với OrderResource — CHỈ khác đúng 1 điều kiện lọc
    // order_type='package_purchase' (ngược lại với OrderResource lọc 'booking'), để 2 resource
    // không bao giờ hiển thị trùng 1 bản ghi nào.
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user && $user->belongsToPlatformPartner()) {
            return Order::withoutGlobalScope('partner')
                ->where(function (Builder $q) use ($user) {
                    $q->where('partner_id', $user->partner_id)
                        ->orWhere('created_by', $user->id);
                })
                ->where('order_type', 'package_purchase');
        }

        $query = parent::getEloquentQuery()->where('order_type', 'package_purchase');

        if (! $user) {
            return $query;
        }

        $branchIds = $user->effectiveBranchIds();

        if (empty($branchIds)) {
            return $user->isSuperAdmin() ? $query : $query->whereRaw('1 = 0');
        }

        $categoryIds = $user->visibleProductCategoryIds($branchIds);

        if (empty($categoryIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('category_id', $categoryIds);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Thông tin đơn mua gói')
                ->schema([
                    TextInput::make('order_code')
                        ->label('Mã đơn')
                        ->disabled(),

                    // Sửa được TRƯỚC khi kích hoạt (đổi ý mua gói khác) — sau khi đã kích hoạt
                    // (status=paid), sửa lại ở đây KHÔNG hồi tố CustomerPackage đã tạo (đã snapshot
                    // tên/số buổi lúc kích hoạt — xem CustomerPackage::activateFromOrder()), nên
                    // khoá lại, sửa số buổi/hạn dùng THẬT ở Section "Gói đã kích hoạt" bên dưới.
                    Select::make('package_plan_id')
                        ->label('Tên gói')
                        ->relationship('packagePlan', 'name')
                        ->searchable()
                        ->preload()
                        ->disabled(fn (?Order $record) => $record && $record->status === 'paid')
                        ->helperText(fn (?Order $record) => $record && $record->status === 'paid'
                            ? 'Gói đã kích hoạt — đổi gói ở đây không ảnh hưởng gói khách đang dùng.'
                            : null),

                    TextInput::make('buyer_name')
                        ->label('Tên khách hàng')
                        ->required(),

                    TextInput::make('buyer_phone')
                        ->label('Số điện thoại')
                        ->tel()
                        ->required(),

                    TextInput::make('buyer_email')
                        ->label('Email')
                        ->email(),

                    TextInput::make('full_amount')
                        ->label('Số tiền')
                        ->disabled()
                        ->numeric()
                        ->suffix('đ'),

                    TextInput::make('payment_method')
                        ->label('Phương thức thanh toán')
                        ->disabled(),

                    // Editable + live — đổi trực tiếp tại đây cũng phải kích hoạt gói đúng cách khi
                    // chuyển sang "Đã thanh toán" (KHÔNG chỉ update() cột status trần), dùng chung
                    // BankTransferConfirmationService::confirm() với 2 nút "Duyệt chuyển khoản"/
                    // "Xác nhận đã thanh toán" bên dưới bảng — nếu chỉ update() thường, đơn chuyển
                    // "paid" nhưng KHÔNG có CustomerPackage nào được tạo (bug thật cần tránh, xem
                    // comment ở markPaidAction()).
                    Select::make('status')
                        ->label('Trạng thái thanh toán')
                        ->options([
                            'pending'           => 'Đang xử lý',
                            'paid'              => 'Đã thanh toán',
                            'failed'            => 'Thất bại',
                            'cancelled_payment' => 'Hủy QR',
                            'refunded'          => 'Hoàn tiền',
                        ])
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (?string $state, ?string $old, ?Order $record) {
                            if (! $record || $state === $old) {
                                return;
                            }

                            if ($state === 'paid') {
                                app(BankTransferConfirmationService::class)->confirm($record, auth()->id());
                            } else {
                                $record->update(['status' => $state]);
                                AuditLogger::log('update', 'Order', $record, ['status' => $old], ['status' => $state], 'Đơn #' . $record->order_code);
                            }

                            Notification::make()->success()->title('Đã cập nhật trạng thái')->send();
                        }),
                ])
                ->columns(2),

            // Chỉ hiện SAU KHI gói đã được kích hoạt (1 CustomerPackage đã tạo từ đơn này) — số
            // buổi còn lại/hạn dùng THẬT nằm ở bảng customer_packages, KHÔNG phải cột nào trên
            // Order. 3 field bên dưới là field ẢO (không map cột nào của Order — dehydrated(false)
            // với cp_sessions_total vì chỉ để xem, còn lại tự nạp/tự lưu tay ở
            // Pages/EditPackageOrder.php::mutateFormDataBeforeFill()/handleRecordUpdate(), giống
            // đúng pattern 'booking_partner_id' ảo mà EditOrder.php đang dùng).
            Section::make('Gói đã kích hoạt')
                ->visible(fn (?Order $record) => $record && CustomerPackage::where('order_id', $record->id)->exists())
                ->schema([
                    TextInput::make('cp_sessions_total')
                        ->label('Tổng số buổi')
                        ->disabled()
                        ->dehydrated(false),

                    TextInput::make('cp_sessions_remaining')
                        ->label('Số buổi còn lại')
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->helperText('Sửa tay khi cần bù/trừ buổi thủ công.'),

                    DateTimePicker::make('cp_expires_at')
                        ->label('Hạn dùng')
                        ->native(false)
                        ->displayFormat('d/m/Y H:i')
                        ->placeholder('Không hết hạn'),
                ])
                ->columns(3),

            Section::make('Ghi chú nội bộ')
                ->schema([
                    Textarea::make('note_for_admin')
                        ->label('Ghi chú (admin)')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Bấm vào cả dòng vào THẲNG trang Sửa (không qua trang "Xem" trung gian nữa) — theo
            // đúng yêu cầu, không còn 'view' trong getPages() nữa nên mặc định Filament sẽ tự rơi
            // về 'edit' cho record URL, nhưng khai báo tường minh ở đây để chắc chắn đúng ý kể cả
            // khi Filament nâng version đổi hành vi mặc định.
            ->recordUrl(fn (Order $record) => static::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('order_code')
                    ->label('Mã đơn')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('buyer_name')
                    ->label('Khách hàng')
                    ->searchable(),

                TextColumn::make('buyer_phone')
                    ->label('Số điện thoại')
                    ->searchable(),

                TextColumn::make('packagePlan.name')
                    ->label('Gói')
                    ->placeholder('—'),

                TextColumn::make('full_amount')
                    ->label('Số tiền')
                    ->money('VND')
                    ->sortable(),

                TextColumn::make('payment_status_label')
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn ($record) => match ($record->payment_status) {
                        'paid'     => 'success',
                        'deposit'  => 'warning',
                        'refunded' => 'gray',
                        default    => 'danger',
                    }),

                TextColumn::make('payment_method')
                    ->label('Phương thức')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái thanh toán')
                    ->options([
                        'pending'           => 'Đang xử lý',
                        'paid'              => 'Đã thanh toán',
                        'deposit'           => 'Đã đặt cọc',
                        'failed'            => 'Thất bại',
                        'cancelled_payment' => 'Hủy QR',
                        'refunded'          => 'Hoàn tiền',
                    ]),

                SelectFilter::make('payment_method')
                    ->label('Phương thức thanh toán')
                    ->options([
                        'PayOS'         => 'PayOS',
                        'bank_transfer' => 'Chuyển khoản',
                    ]),
            ])
            ->actions([
                // Dùng CHUNG action "Duyệt chuyển khoản" với OrderResource (BankTransferConfirmationService
                // tự nhánh đúng theo order_type — xem confirm(), chỗ gọi CustomerPackage::activateFromOrder()
                // khi order_type='package_purchase') — không viết trùng logic duyệt tiền ở 2 nơi. Chỉ hiện
                // khi payment_method='bank_transfer' VÀ có ảnh biên lai đang chờ duyệt.
                OrderTable::confirmBankTransferAction(),

                // Đơn PayOS (hoặc bất kỳ phương thức nào khác không phải chuyển khoản) KHÔNG có ảnh
                // biên lai để "duyệt" — nếu webhook PayOS lỗi/khách báo đã chuyển tiền qua kênh khác,
                // admin cần 1 cách XÁC NHẬN TAY tổng quát. Dùng chung
                // BankTransferConfirmationService::confirm() (dù tên gọi "chuyển khoản", hàm này chỉ
                // đơn thuần set status=paid + kích hoạt CustomerPackage, không đòi hỏi payment_method
                // nào cụ thể) để KHÔNG viết lại logic kích hoạt gói lần thứ 2 ở đây (tránh lệch nhau).
                static::markPaidAction(),
            ])
            ->bulkActions([]);
    }

    // Public + static để dùng được ở cả bảng danh sách LẪN trang Sửa/Xem (Pages/EditPackageOrder.php,
    // Pages/ViewPackageOrder.php) — cùng lý do/cùng pattern với OrderTable::confirmBankTransferAction()
    // (2 class Action khác nhau tuỳ ngữ cảnh gọi, action con PHẢI cùng loại với action cha).
    public static function markPaidAction(string $actionClass = Action::class): Action|\Filament\Actions\Action
    {
        return $actionClass::make('mark_paid')
            ->label('Xác nhận đã thanh toán')
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn ($record) => ! in_array($record->status, ['paid', 'refunded'], true))
            ->requiresConfirmation()
            ->modalHeading(fn ($record) => 'Xác nhận đã thanh toán — Đơn ' . $record->order_code)
            ->modalDescription(fn ($record) => 'Dùng khi khách đã thanh toán qua '
                . ($record->payment_method ?: 'kênh khác') . ' nhưng hệ thống chưa tự cập nhật (vd webhook lỗi).'
                . ' Sau khi xác nhận, gói sẽ được KÍCH HOẠT NGAY cho khách.')
            ->modalSubmitActionLabel('Xác nhận đã thanh toán')
            ->action(function ($record) {
                try {
                    app(BankTransferConfirmationService::class)->confirm($record, auth()->id());

                    Notification::make()->success()->title('Đã xác nhận thanh toán — gói đã được kích hoạt')->send();
                } catch (\Throwable $e) {
                    Notification::make()->danger()->title('Lỗi: ' . $e->getMessage())->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackageOrders::route('/'),
            'edit'  => Pages\EditPackageOrder::route('/{record}/edit'),
        ];
    }
}
