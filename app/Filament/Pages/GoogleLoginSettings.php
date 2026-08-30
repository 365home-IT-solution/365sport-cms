<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\GoogleOauthConfiguration;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

// Cấu hình đăng nhập Google cho KHÁCH HÀNG (storefront) — chỉ 1 bản ghi duy nhất (singleton),
// KHÔNG liên quan tới đăng nhập admin (Filament vẫn chỉ dùng email/mật khẩu). client_id/
// client_secret nhập ở đây, nạp vào config lúc boot() (xem App\Providers\AppServiceProvider),
// KHÔNG lưu vào .env — mirror đúng cách PaymentConfiguration lưu tài khoản PayOS mặc định.
class GoogleLoginSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Cấu hình web';

    protected static ?string $navigationLabel = 'Đăng nhập Google';

    protected static ?string $title = 'Đăng nhập Google (khách hàng)';

    protected static string $view = 'filament.pages.google-login-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_GoogleLoginSettings') ?? false;
    }

    public function mount(): void
    {
        $config = GoogleOauthConfiguration::first();

        $this->form->fill([
            'enabled'       => $config?->enabled ?? false,
            'client_id'     => $config?->client_id,
            'client_secret' => $config?->client_secret,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Google OAuth Client')
                    ->description('Lấy Client ID/Secret tại Google Cloud Console > APIs & Services > Credentials > OAuth 2.0 Client IDs. Authorized redirect URI cần khai báo: ' . url('/auth/google/callback'))
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Bật đăng nhập Google cho khách hàng')
                            ->helperText('Tắt: ẩn nút "Đăng nhập với Google" khỏi trang đăng nhập khách hàng ngay lập tức, không cần xoá Client ID/Secret đã nhập.')
                            ->columnSpanFull(),
                        TextInput::make('client_id')
                            ->label('Client ID')
                            ->maxLength(255),
                        TextInput::make('client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $config = GoogleOauthConfiguration::first() ?? new GoogleOauthConfiguration();
        $config->enabled       = (bool) ($data['enabled'] ?? false);
        $config->client_id     = $data['client_id'] ?? null;
        $config->client_secret = $data['client_secret'] ?? null;
        $config->save();

        Notification::make()
            ->success()
            ->title('Đã lưu cấu hình đăng nhập Google')
            ->send();
    }
}
