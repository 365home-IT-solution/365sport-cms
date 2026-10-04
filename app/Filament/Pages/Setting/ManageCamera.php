<?php

declare(strict_types=1);

namespace App\Filament\Pages\Setting;

use App\Models\CameraSetting;
use App\Models\Partner;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

// Cấu hình server go2rtc/Frigate theo TỪNG ĐỐI TÁC — go2rtc/Frigate là phần mềm NGOÀI dự án, đối
// tác tự dựng/vận hành (server/NVR riêng kết nối camera IP thật), trang này chỉ lưu thông tin để
// hệ thống kết nối tới đó. User thường tự bind partner_id của mình; super_admin phải chọn Partner
// trước rồi mới hiện form.
class ManageCamera extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-video-camera';
    protected static ?string $navigationGroup = 'Cấu hình web';
    protected static ?int    $navigationSort  = 12;
    protected static ?string $navigationLabel = 'Cấu hình Camera';
    protected static ?string $title           = 'Cấu hình Camera (go2rtc/Frigate)';
    protected static string $view = 'filament.pages.setting.manage-camera';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (auth()->user()?->isSuperAdmin() ?? false) || (auth()->user()?->can('page_ManageCamera') ?? false);
    }

    public function mount(): void
    {
        $partnerId = auth()->user()?->isSuperAdmin() ? null : auth()->user()?->partner_id;
        $setting = $partnerId ? CameraSetting::forPartner($partnerId) : null;

        $this->form->fill([
            'partner_id' => $partnerId,
            'base_url'   => $setting?->base_url,
            'username'   => $setting?->username,
            // api_key/password không hiện lại giá trị thật (đã mã hoá) — để trống nghĩa là GIỮ
            // NGUYÊN giá trị cũ khi lưu, chỉ ghi đè khi admin thật sự gõ giá trị mới.
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('partner_id')
                    ->label('Đối tác')
                    ->options(fn () => Partner::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false)
                    ->required(fn () => auth()->user()?->isSuperAdmin() ?? false)
                    ->afterStateUpdated(function (Get $get, callable $set) {
                        $setting = CameraSetting::forPartner($get('partner_id'));
                        $set('base_url', $setting->base_url);
                        $set('username', $setting->username);
                    }),

                Section::make('Kết nối go2rtc / Frigate')
                    ->visible(fn (Get $get) => filled($get('partner_id')))
                    ->schema([
                        TextInput::make('base_url')
                            ->label('Base URL')
                            ->placeholder('http://192.168.1.10:1984 (go2rtc) hoặc http://192.168.1.10:5000 (Frigate)')
                            ->url()
                            ->required(),

                        TextInput::make('api_key')
                            ->label('API Key (go2rtc, nếu có bật auth)')
                            ->password()
                            ->revealable()
                            ->helperText('Để trống = giữ nguyên giá trị đã lưu trước đó.'),

                        TextInput::make('username')
                            ->label('Tài khoản đăng nhập Frigate'),

                        TextInput::make('password')
                            ->label('Mật khẩu Frigate')
                            ->password()
                            ->revealable()
                            ->helperText('Để trống = giữ nguyên giá trị đã lưu trước đó.'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $partnerId = $data['partner_id'] ?? auth()->user()?->partner_id;

        if (! $partnerId) {
            Notification::make()->danger()->title('Vui lòng chọn đối tác trước khi lưu.')->send();

            return;
        }

        $setting = CameraSetting::forPartner($partnerId);
        $setting->partner_id = $partnerId;
        $setting->base_url = $data['base_url'] ?? null;
        $setting->username = $data['username'] ?? $setting->username;

        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        if (filled($data['password'] ?? null)) {
            $setting->password = $data['password'];
        }

        $setting->save();

        Notification::make()->success()->title('Đã lưu cấu hình camera.')->send();
    }
}
