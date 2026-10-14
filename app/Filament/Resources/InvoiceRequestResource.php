<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceRequestResource\Pages;
use App\Models\InvoiceRequest;
use App\Services\InvoiceRequestService;
use Filament\Forms\Components\FileUpload;
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

// Yêu cầu xuất hoá đơn VAT của khách. Đối tác xuất hoá đơn ở phần mềm hoá đơn riêng rồi nhập số + tải file ở đây; hệ thống không tự phát hành hoá đơn điện tử.
class InvoiceRequestResource extends Resource
{
    protected static ?string $model = InvoiceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Yêu cầu hoá đơn VAT';

    protected static ?string $modelLabel = 'Yêu cầu hoá đơn';

    protected static ?string $pluralModelLabel = 'Yêu cầu hoá đơn VAT';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user && ! $user->isSuperAdmin() ? $query->where('partner_id', $user->partner_id) : $query;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order:id,order_code,full_amount'))
            ->columns([
                TextColumn::make('created_at')->label('Ngày yêu cầu')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('order.order_code')->label('Mã đơn')->searchable(),
                TextColumn::make('order.full_amount')->label('Giá trị đơn')->money('VND'),
                TextColumn::make('company_name')->label('Công ty')->searchable()->wrap(),
                TextColumn::make('tax_code')->label('MST')->searchable()->copyable(),
                TextColumn::make('email')->label('Email nhận')->copyable(),
                TextColumn::make('status')->label('Trạng thái')->badge()
                    ->formatStateUsing(fn ($state) => ['pending' => 'Chờ xuất', 'issued' => 'Đã xuất', 'rejected' => 'Từ chối'][$state] ?? $state)
                    ->color(fn ($state) => ['pending' => 'warning', 'issued' => 'success', 'rejected' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('invoice_number')->label('Số hoá đơn')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->label('Trạng thái')->options(['pending' => 'Chờ xuất', 'issued' => 'Đã xuất', 'rejected' => 'Từ chối'])])
            ->actions([
                Action::make('issue')->label('Đã xuất hoá đơn')->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (InvoiceRequest $record) => $record->status === 'pending' && auth()->user()?->can('update_invoice::request'))
                    ->modalDescription(fn (InvoiceRequest $record) => "{$record->company_name} — MST {$record->tax_code} — {$record->address}")
                    ->form([
                        TextInput::make('invoice_number')->label('Số hoá đơn')->required()->maxLength(50),
                        FileUpload::make('invoice_file')->label('File hoá đơn (PDF/XML/ảnh)')->disk('public')->directory('invoices')
                            ->acceptedFileTypes(['application/pdf', 'text/xml', 'application/xml', 'image/jpeg', 'image/png'])->maxSize(10240),
                    ])
                    ->action(function (InvoiceRequest $record, array $data): void {
                        $result = app(InvoiceRequestService::class)->issue($record, $data['invoice_number'], $data['invoice_file'] ?? null, auth()->user());
                        $result['ok'] ? Notification::make()->success()->title('Đã gửi hoá đơn cho khách')->send() : Notification::make()->danger()->title($result['message'])->send();
                    }),
                Action::make('reject')->label('Từ chối')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (InvoiceRequest $record) => $record->status === 'pending' && auth()->user()?->can('update_invoice::request'))
                    ->form([Textarea::make('reason')->label('Lý do')->required()->maxLength(500)])
                    ->action(function (InvoiceRequest $record, array $data): void {
                        $result = app(InvoiceRequestService::class)->reject($record, $data['reason'], auth()->user());
                        $result['ok'] ? Notification::make()->success()->title('Đã từ chối yêu cầu')->send() : Notification::make()->danger()->title($result['message'])->send();
                    }),
                Action::make('download')->label('Tải hoá đơn')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (InvoiceRequest $record) => (bool) $record->invoice_file)->url(fn (InvoiceRequest $record) => $record->invoice_file_url, true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListInvoiceRequests::route('/')];
    }
}
