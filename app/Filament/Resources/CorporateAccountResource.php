<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CorporateAccountResource\Pages;
use App\Filament\Resources\CorporateAccountResource\RelationManagers\EmployeesRelationManager;
use App\Models\CorporateAccount;
use App\Services\CorporateInvoiceRenderer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Payment\Entities\Order;

// Tài khoản khách hàng doanh nghiệp (B2B) — 1 công ty được cấp hạn mức GIỜ SÂN/tháng dùng chung
// cho nhiều nhân viên (xem EmployeesRelationManager) thay vì trả tiền per-booking, thanh toán 1
// hoá đơn tổng hợp cuối kỳ (action "Xuất hoá đơn" bên dưới). Xem đầy đủ nghiệp vụ ở
// App\Models\CorporateAccount. Dùng BelongsToPartner (gắn sẵn trên model) để tự lọc theo đối tác —
// super_admin xem toàn bộ, đối tác khác chỉ xem tài khoản B2B của mình, giống PackagePlanResource/
// CustomerPackageResource.
class CorporateAccountResource extends Resource
{
    protected static ?string $model = CorporateAccount::class;

    protected static ?string $navigationIcon   = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup  = 'Quản lý';
    protected static ?string $navigationLabel  = 'Khách hàng doanh nghiệp';
    protected static ?string $modelLabel       = 'Tài khoản doanh nghiệp';
    protected static ?string $pluralModelLabel = 'Khách hàng doanh nghiệp';
    protected static ?int    $navigationSort   = 8;

    // Cùng cách canViewAny() đơn giản như PackagePlanResource/CustomerPackageResource — resource
    // mới, chưa đăng ký permission qua Shield.
    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Thông tin công ty')
                ->schema([
                    // super_admin/nhân viên nền tảng quản lý nhiều đối tác nên phải TỰ CHỌN đối tác
                    // sở hữu (BelongsToPartner::bootBelongsToPartner() chỉ tự gán theo user đăng
                    // nhập khi field bị bỏ trống — user thường không thấy field này, tự gán ngầm).
                    Select::make('partner_id')
                        ->label('Đối tác (chủ sân)')
                        ->relationship('partner', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->visible(fn () => auth()->user()?->isSuperAdmin() || auth()->user()?->belongsToPlatformPartner())
                        ->helperText('Chỉ super_admin/nhân viên nền tảng thấy field này — user thường tự động gán theo đối tác của mình.'),

                    TextInput::make('company_name')
                        ->label('Tên công ty')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('tax_code')
                        ->label('Mã số thuế')
                        ->maxLength(50),

                    TextInput::make('contact_name')
                        ->label('Người liên hệ')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('contact_phone')
                        ->label('SĐT liên hệ')
                        ->tel()
                        ->required()
                        ->maxLength(20),

                    TextInput::make('contact_email')
                        ->label('Email liên hệ')
                        ->email()
                        ->maxLength(255),

                    Textarea::make('billing_address')
                        ->label('Địa chỉ xuất hoá đơn')
                        ->rows(2)
                        ->columnSpanFull(),

                    TextInput::make('monthly_hour_quota')
                        ->label('Hạn mức giờ/tháng')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.5)
                        ->suffix('giờ')
                        ->required(),

                    TextInput::make('hours_used_current_cycle')
                        ->label('Đã dùng trong kỳ')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('giờ')
                        ->helperText('Sửa tay khi cần điều chỉnh — dùng action "Chốt kỳ" để reset về 0 khi bắt đầu kỳ mới, không sửa tay thường xuyên ở đây.')
                        ->visible(fn (?CorporateAccount $record) => $record !== null)
                        ->disabled(fn (?CorporateAccount $record) => $record === null),

                    DatePicker::make('cycle_start_date')
                        ->label('Ngày bắt đầu kỳ hiện tại')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now())
                        ->required(),

                    Select::make('status')
                        ->label('Trạng thái')
                        ->options([
                            CorporateAccount::STATUS_ACTIVE    => 'Đang hoạt động',
                            CorporateAccount::STATUS_SUSPENDED => 'Tạm ngưng',
                        ])
                        ->default(CorporateAccount::STATUS_ACTIVE)
                        ->required(),

                    Textarea::make('notes')
                        ->label('Ghi chú')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Công ty')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('contact_name')
                    ->label('Người liên hệ')
                    ->description(fn (CorporateAccount $record) => $record->contact_phone),

                TextColumn::make('partner.name')
                    ->label('Đối tác')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('quota')
                    ->label('Hạn mức')
                    ->getStateUsing(fn (CorporateAccount $record) => number_format((float) $record->hours_used_current_cycle, 1) . ' / ' . number_format((float) $record->monthly_hour_quota, 1) . ' giờ')
                    ->badge()
                    ->color(fn (CorporateAccount $record) => $record->remainingHours() > 0 ? 'success' : 'danger'),

                TextColumn::make('cycle_start_date')
                    ->label('Kỳ hiện tại từ')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('active_employees_count')
                    ->label('Nhân viên')
                    ->counts('activeEmployees')
                    ->suffix(' người'),

                TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === CorporateAccount::STATUS_ACTIVE ? 'Đang hoạt động' : 'Tạm ngưng')
                    ->color(fn (string $state) => $state === CorporateAccount::STATUS_ACTIVE ? 'success' : 'gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Trạng thái')
                    ->options([
                        CorporateAccount::STATUS_ACTIVE    => 'Đang hoạt động',
                        CorporateAccount::STATUS_SUSPENDED => 'Tạm ngưng',
                    ]),
            ])
            ->actions([
                ViewAction::make()->label('Chi tiết'),
                EditAction::make()->label('Sửa'),

                // Xuất hoá đơn tổng hợp mọi Order đã trừ hạn mức TRONG KỲ HIỆN TẠI (từ
                // cycle_start_date tới lúc bấm) — xem App\Services\CorporateInvoiceRenderer. KHÔNG
                // tự "Chốt kỳ" sau khi xuất — 2 hành động tách riêng có chủ đích (admin có thể xuất
                // thử nhiều lần trong kỳ để xem trước, chỉ chốt khi thật sự chuyển sang kỳ mới).
                Action::make('export_invoice')
                    ->label('Xuất hoá đơn')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(function (CorporateAccount $record) {
                        $orders = Order::withoutGlobalScopes()
                            ->where('corporate_account_id', $record->id)
                            ->where('created_at', '>=', $record->cycle_start_date)
                            ->with(['items.product', 'customer'])
                            ->orderBy('created_at')
                            ->get();

                        return CorporateInvoiceRenderer::download($record, $orders);
                    }),

                // "Chốt kỳ" — reset hours_used_current_cycle về 0 + đẩy cycle_start_date sang tháng
                // kế tiếp (xem CorporateAccount::closeCycle()). Bắt buộc xác nhận vì KHÔNG hoàn tác
                // được (số liệu kỳ cũ chỉ còn lại trong PDF hoá đơn đã xuất trước đó) — admin nên
                // xuất hoá đơn TRƯỚC KHI chốt.
                Action::make('close_cycle')
                    ->label('Chốt kỳ')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Reset số giờ đã dùng về 0 và chuyển sang kỳ mới (tháng kế tiếp)? Hãy chắc chắn đã xuất hoá đơn cho kỳ hiện tại trước — hành động này KHÔNG hoàn tác được.')
                    ->action(function (CorporateAccount $record) {
                        $record->closeCycle();

                        Notification::make()
                            ->title('Đã chốt kỳ — hạn mức đã reset về 0')
                            ->success()
                            ->send();
                    }),

                DeleteAction::make()->label('Xoá'),
            ])
            ->bulkActions([]);
    }

    public static function getRelationManagers(): array
    {
        return [
            EmployeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCorporateAccounts::route('/'),
            'create' => Pages\CreateCorporateAccount::route('/create'),
            'edit'   => Pages\EditCorporateAccount::route('/{record}/edit'),
            'view'   => Pages\ViewCorporateAccount::route('/{record}'),
        ];
    }
}
