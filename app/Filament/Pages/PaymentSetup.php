<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Partner;
use App\Services\PaymentSetupService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

// Trang "Thiết lập thanh toán" của CHỦ đối tác: chọn cách thu tiền (chuyển khoản VietQR hoặc PayOS riêng) và điền thông tin. Chưa thiết lập thì sân không nhận đặt online
// và không bật bán sân được. Dùng chung PaymentSetupService với API quản trị.
class PaymentSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Quản lý';

    protected static ?string $navigationLabel = 'Thiết lập thanh toán';

    protected static ?string $title = 'Thiết lập thanh toán';

    protected static ?string $slug = 'payment-setup';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.payment-setup';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->partner_id && ! $user->isSuperAdmin() && $user->isPartnerOwner() && $user->can('page_PaymentSetup'));
    }

    public function getPartner(): ?Partner
    {
        return Partner::query()->find(auth()->user()?->partner_id);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = app(PaymentSetupService::class)->current($this->getPartner());
        $this->form->fill($state);
    }

    public function form(Form $form): Form
    {
        $hint = fn (?string $v) => $v ? "Đã lưu ({$v}). Để trống nếu không muốn đổi." : 'Chưa có';

        return $form->schema([
            Placeholder::make('status')->hiddenLabel()->content(function () {
                $ready = $this->getPartner()?->hasUsablePaymentMethod();

                return new HtmlString($ready
                    ? '<div class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">✅ Đã thiết lập — khách đặt sân online sẽ thanh toán theo cách bạn chọn bên dưới.</div>'
                    : '<div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">⚠️ <strong>Chưa thiết lập xong.</strong> Cho tới khi hoàn tất, khách không đặt được sân online và bạn chưa bật bán sân được.</div>');
            }),
            Radio::make('payment_method_preference')->label('Cách nhận tiền từ khách')->options(PaymentSetupService::METHODS)->required()->live()->inline(false)
                ->helperText('Khuyến nghị: chuyển khoản — chỉ cần số tài khoản ngân hàng, không cần đăng ký cổng thanh toán.'),
            Section::make('Tài khoản ngân hàng nhận tiền')
                ->description('Khách quét mã QR VietQR để chuyển khoản đúng số tiền vào tài khoản này.')
                ->visible(fn (\Filament\Forms\Get $get) => in_array($get('payment_method_preference'), ['bank_transfer_sepay', 'bank_transfer_manual'], true))
                ->schema([
                    Select::make('bank_bin')->label('Ngân hàng')->options(config('vietnam_banks'))->searchable(),
                    TextInput::make('bank_branch')->label('Chi nhánh')->maxLength(255),
                    TextInput::make('bank_account_number')->label('Số tài khoản')->maxLength(20),
                    TextInput::make('bank_account_holder')->label('Tên chủ tài khoản (KHÔNG dấu, đúng như trên thẻ)')->maxLength(255),
                ])->columns(2),
            Section::make('Khoá PayOS của bạn')
                ->description('Lấy trong tài khoản PayOS (my.payos.vn → Kênh thanh toán). Khoá được lưu mã hoá.')
                ->visible(fn (\Filament\Forms\Get $get) => $get('payment_method_preference') === 'payos')
                ->schema([
                    TextInput::make('payos_client_id')->label('Client ID')->maxLength(255)->helperText(fn () => $hint(app(PaymentSetupService::class)->current($this->getPartner())['payos_client_id_hint'])),
                    TextInput::make('payos_api_key')->label('API Key')->password()->revealable()->maxLength(255),
                    TextInput::make('payos_checksum_key')->label('Checksum Key')->password()->revealable()->maxLength(255),
                ])->columns(1),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            app(PaymentSetupService::class)->save($this->getPartner(), $this->form->getState());
        } catch (ValidationException $e) {
            Notification::make()->title('Chưa lưu được')->body(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        Notification::make()->title('Đã lưu thiết lập thanh toán')->success()->send();
        $this->mount();
    }
}
