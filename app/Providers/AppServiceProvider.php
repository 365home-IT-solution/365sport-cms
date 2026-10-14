<?php

namespace App\Providers;

use App\Http\View\Composers\ProductColorComposer;
use App\Models\GoogleOauthConfiguration;
use App\Services\ContractSigning\ContractSigningManager;
use App\Services\ContractSigning\Contracts\DigitalSignatureProvider;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use Illuminate\Support\Facades\Config;
use Filament\Tables\Table;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Guava\FilamentKnowledgeBase\Enums\TableOfContentsPosition;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Guava\FilamentKnowledgeBase\Filament\Panels\KnowledgeBasePanel;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;
use Livewire\Livewire;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        KnowledgeBasePanel::configureUsing(
            fn(KnowledgeBasePanel $panel) => $panel
                ->viteTheme('resources/css/filament/admin/theme.css')
                ->brandName('Tài liệu hướng dẫn')
                ->tableOfContentsPosition(TableOfContentsPosition::Start)
                ->disableBreadcrumbs()
        );

        Blade::directive('livewireIf', function ($expression) {
            return "<?php if(view()->exists($expression)) { echo \Livewire\Livewire::mount($expression)->html(); } ?>";
        });

        // Provider ký số hợp đồng điện tử — chọn theo config/contract_signing.php (mặc định
        // 'local', đổi sang 'vnpt_smartca' qua .env khi có tài khoản đối tác thật, không cần sửa
        // ContractSignController/PartnerForm vì cả 2 nơi chỉ phụ thuộc vào interface này.
        $this->app->bind(DigitalSignatureProvider::class, function ($app) {
            return $app->make(ContractSigningManager::class)->driver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table
                ->emptyStateHeading('Không có dữ liệu')
                ->defaultPaginationPageOption(10)
                ->paginated([10, 25, 50, 100])
                ->extremePaginationLinks()
                ->defaultSort('created_at', 'desc');
        });

        LogViewer::auth(function ($request) {
            $role = auth()?->user()?->roles?->first()->name;
            return $role == config('filament-shield.super_admin.name');
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::FOOTER,
            fn(): View => view('filament.components.panel-footer'),
        );
        // Nút "Chuyển đổi chi nhánh" ở topbar — port từ 365home-cms (App\Livewire\BranchSwitcher).
        // Chỉ hiện khi tài khoản quản lý từ 2 chi nhánh trở lên — 1 chi nhánh thì không có gì để
        // chuyển đổi.
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            function (): string {
                $user = auth()->user();

                if (! $user instanceof \App\Models\User || count($user->rootProductCategoryIds()) <= 1) {
                    return '';
                }

                return \Livewire\Livewire::mount('branch-switcher');
            },
        );
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn(): View => view('filament.components.button-website'),
        );
        // Nhãn đối tác + vai trò + tình trạng gói cạnh menu tài khoản.
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn(): View => view('filament.components.account-badge'),
        );

        try {
            $settings = app(MailSettings::class);
            $settings->loadMailSettingsToConfig();
        } catch (\Exception $e) {
            \Log::error('Failed to load mail settings: ' . $e->getMessage());
        }

        $this->applyZaloSettings();

        // Đăng nhập Google cho khách hàng — client_id/secret nhập ở admin, không dùng .env (xem
        // App\Filament\Pages\GoogleLoginSettings, App\Models\GoogleOauthConfiguration).
        try {
            $googleConfig = GoogleOauthConfiguration::first();
            if ($googleConfig) {
                Config::set('services.google.client_id', $googleConfig->client_id);
                Config::set('services.google.client_secret', $googleConfig->client_secret);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to load Google OAuth config: ' . $e->getMessage());
        }

        ViewFacade::composer('*', ProductColorComposer::class);

        // finfo on Windows does not detect AVIF as image/avif; register a signature-based guesser
        MimeTypes::getDefault()->registerGuesser(new class implements MimeTypeGuesserInterface {
            public function isGuesserSupported(): bool { return true; }
            public function guessMimeType(string $path): ?string {
                $handle = @fopen($path, 'rb');
                if (!$handle) return null;
                $header = fread($handle, 12);
                fclose($handle);
                if (strlen($header) >= 12 && substr($header, 4, 4) === 'ftyp' && in_array(substr($header, 8, 4), ['avif', 'avis'])) {
                    return 'image/avif';
                }
                return null;
            }
        });

        // Đảm bảo Breezy profile components luôn được đăng ký
        // (BreezyCore::boot() chỉ chạy khi panel boot — không đủ cho Livewire update requests)
        Livewire::component('personal_info', PersonalInfo::class);
        Livewire::component('update_password', UpdatePassword::class);

        // Secure the Livewire update route with rate limiting + origin validation
        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web', 'livewire.secure']);
        });
    }

    /**
     * Zalo OA/ZNS nhập ở admin (Cài đặt chung > tab "Zalo") ghi đè .env: ZaloOtpService đọc
     * config('zalo.*'), ZaloZnsService đọc config('services.zalo.*'). Giá trị trống → giữ .env.
     */
    private function applyZaloSettings(): void
    {
        try {
            $s = app(GeneralSettings::class);

            $map = [
                'zalo.app_id'                               => $s->zalo_app_id,
                'services.zalo.app_id'                      => $s->zalo_app_id,
                'zalo.app_secret'                           => $s->zalo_app_secret,
                'services.zalo.app_secret'                  => $s->zalo_app_secret,
                'zalo.access_token'                         => $s->zalo_access_token,
                'zalo.refresh_token'                        => $s->zalo_refresh_token,
                'services.zalo.refresh_token'               => $s->zalo_refresh_token,
                'zalo.otp_template_id'                      => $s->zalo_otp_template_id,
                'services.zalo.templates.booking_success'   => $s->zalo_template_booking_success,
                'services.zalo.templates.booking_reminder'  => $s->zalo_template_booking_reminder,
                'services.zalo.templates.booking_cancelled' => $s->zalo_template_booking_cancelled,
            ];

            foreach ($map as $key => $value) {
                if (filled($value)) {
                    Config::set($key, $value);
                }
            }
        } catch (\Throwable $e) {
            // Chưa migrate settings / DB chưa sẵn sàng → dùng .env.
            \Log::warning('Failed to load Zalo settings: ' . $e->getMessage());
        }
    }
}
