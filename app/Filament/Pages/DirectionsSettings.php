<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\DirectionsConfiguration;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

// Cấu hình dẫn đường cho bản đồ /ban-do — singleton (1 bản ghi duy nhất). Tắt = dùng OSRM công
// cộng như trước đây (tự lọc điểm ngoài lãnh thổ VN, có thể không vẽ được với quãng đường quá xa).
// Bật = dùng OpenRouteService (tránh Lào/Campuchia ngay từ API, chính xác hơn) — cần API key riêng,
// đăng ký miễn phí tại openrouteservice.org (gói free 2.000 lượt/ngày).
class DirectionsSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Cấu hình web';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Dẫn đường bản đồ';

    protected static ?string $title = 'Dẫn đường bản đồ (/ban-do)';

    protected static string $view = 'filament.pages.directions-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_DirectionsSettings') ?? false;
    }

    public function mount(): void
    {
        $config = DirectionsConfiguration::first();

        $this->form->fill([
            'enabled'     => $config?->enabled ?? false,
            'ors_api_key' => $config?->ors_api_key,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('OpenRouteService')
                    ->description('Lấy API key miễn phí tại openrouteservice.org > Dashboard > Request a token (gói Free, 2.000 lượt/ngày). Khi bật, dẫn đường sẽ tránh định tuyến qua Lào/Campuchia. Khi tắt, dẫn đường quay lại dùng OSRM công cộng như trước đây.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Bật dẫn đường qua OpenRouteService')
                            ->helperText('Tắt: dùng OSRM công cộng (miễn phí, có thể vẽ sai đường qua nước khác với quãng đường xa). Bật: dùng OpenRouteService (cần API key hợp lệ bên dưới).')
                            ->columnSpanFull(),
                        TextInput::make('ors_api_key')
                            ->label('OpenRouteService API Key')
                            ->password()
                            ->revealable()
                            ->maxLength(500),
                    ])
                    ->columns(1),
                Section::make('Quota còn lại')
                    ->description('Đọc từ header X-Ratelimit-* của lần gọi ORS gần nhất — chính xác theo thời gian thực, không phụ thuộc dashboard web của ORS (có thể hiển thị trễ).')
                    ->schema([
                        Placeholder::make('quota_info')
                            ->label('')
                            ->content(fn () => $this->quotaSummary()),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    // Tóm tắt quota lần gọi ORS gần nhất — DirectionsController::saveQuotaFromResponse() ghi lại
    // mỗi lần thật sự gọi API (header X-Ratelimit-*), không phải số liệu tự tính/ước lượng.
    private function quotaSummary(): string
    {
        $config = DirectionsConfiguration::first();

        if (! $config || is_null($config->quota_remaining)) {
            return 'Chưa có dữ liệu — sẽ tự cập nhật sau lần dẫn đường đầu tiên qua OpenRouteService.';
        }

        $checkedAt = $config->quota_checked_at?->diffForHumans() ?? 'không rõ';
        $resetAt   = $config->quota_reset_at?->format('H:i d/m/Y');

        return "Còn {$config->quota_remaining}/{$config->quota_limit} lượt hôm nay"
            . ($resetAt ? " (làm mới lúc {$resetAt})" : '')
            . " — cập nhật {$checkedAt}.";
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $config = DirectionsConfiguration::first() ?? new DirectionsConfiguration();
        $config->enabled     = (bool) ($data['enabled'] ?? false);
        $config->ors_api_key = $data['ors_api_key'] ?? null;
        $config->save();

        Notification::make()
            ->success()
            ->title('Đã lưu cấu hình dẫn đường')
            ->send();
    }
}
