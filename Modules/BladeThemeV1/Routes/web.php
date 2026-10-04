<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Collection;
use Modules\BladeThemeV1\Http\Controllers\BladeThemeV1Controller;
use Modules\BladeThemeV1\Http\Controllers\SitemapController;
use Modules\BladeThemeV1\Support\ThemeCache;

if (!function_exists('formatMenu')) {
function formatMenu(Collection $items): array
{
    return $items->map(function ($item) {
        $page = [
            'name' => $item['title'],
            'url' => $item['url'],
            'page_id' => $item['page_id'],
        ];

        if (isset($item['params'])) {
            $page['params'] = $item['params'];
        }

        if (isset($item['children']) && $item['children'] instanceof Collection && $item['children']->isNotEmpty()) {
            $page['children'] = formatMenu($item['children']);
        }

        return $page;
    })->toArray();
}
} // end if !function_exists('formatMenu')

if (!function_exists('createRoutes')) {
function createRoutes(array $pages, string $prefix = ''): void
{
    foreach ($pages as $page) {
        if (isset($page['url'])) {
            $url = '/' . trim($prefix . '/' . trim($page['url'], '/'), '/');

            $route = Route::get($url, [BladeThemeV1Controller::class, 'index'])
                ->name('page.' . str_replace(['{', '}'], '', trim($url, '/')))
                ->defaults('page_id', $page['page_id']);

            if (isset($page['params']) && is_array($page['params'])) {
                $route->where($page['params']);
            }
        }

        if (!empty($page['children'])) {
            createRoutes($page['children'], $url ?? $prefix);
        }
    }
}
} // end if !function_exists('createRoutes')

// Landing page RIÊNG theo subdomain đối tác (vd mamble.365sport-cms.test) — 1 giao diện DUY NHẤT
// dùng chung cho mọi đối tác, chỉ đổi DỮ LIỆU theo {partnerSlug} (xem
// BladeThemeV1Controller::partnerLandingPage()). Domain tính động từ APP_URL (không hardcode
// ".test") để hoạt động đúng khi lên domain thật — production cần DNS wildcard (*.example.com) +
// SSL wildcard trỏ về ĐÚNG server này (xem ghi chú triển khai đã trao đổi với người dùng). Route
// domain() chỉ khớp khi URL CÓ subdomain — request vào domain gốc (không có {partnerSlug}) tự
// động rơi xuống các route bên dưới như bình thường, không xung đột.
$publicSiteHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost();

// Loại trừ "www" khỏi {partnerSlug} — nginx vhost hiện tại coi www.365sport-cms.test là 1 cách gọi
// hợp lệ khác của domain CHÍNH (xem server_name), không phải subdomain đối tác. Không loại trừ thì
// www sẽ bị route này "cướp" mất (khớp {partnerSlug}='www', 404 vì không có đối tác nào slug
// 'www'), thay vì rơi xuống route trang chủ bình thường bên dưới.
Route::domain('{partnerSlug}.' . $publicSiteHost)
    ->where(['partnerSlug' => '(?!www$)[a-z0-9-]+'])
    ->group(function () {
        Route::get('/', [BladeThemeV1Controller::class, 'partnerLandingPage'])->name('partner.landing');
    });

$menus = ThemeCache::menuForRoutes();

if ($menus->isNotEmpty()) {
    foreach ($menus as $menu) {
        if ($menu->menuItems->isNotEmpty()) {
            $pages = formatMenu($menu->menuItems);
            createRoutes($pages);
        }
    }
} else {
    // Chưa có menu CMS nào (cài mới) → không có page_id để index() dựng trang (findOrFail → 404),
    // dùng home() — trang chủ đặt sân dựng sẵn bằng Livewire, không phụ thuộc Page builder.
    Route::get('/', [BladeThemeV1Controller::class, 'home'])->name('home');
}

// SEO
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt',  [SitemapController::class, 'robots'])->name('robots');

Route::get('/bai-viet/{slug}', [BladeThemeV1Controller::class, 'postDetail'])->name('post.detail');
Route::get('/s/{location?}', [BladeThemeV1Controller::class, 'searchProduct'])->name('product.search');
Route::get('/branch/{slug}', [BladeThemeV1Controller::class, 'bookingBoard'])->name('branch.booking');
Route::get('/room/{slug}/', [BladeThemeV1Controller::class, 'productDetail'])->name('product.detail');
//Route::get('/local/home-{slug}/', [BladeThemeV1Controller::class, 'categoryDetail'])->name('category.detail');
Route::get('/mau-giao-dien/{slug}', [BladeThemeV1Controller::class, 'templateDetail'])->name('template.detail');
// Đã tắt: trang giỏ hàng + phí ship GHN thừa từ dự án bán hàng (không có config/ghn.php, không nơi
// nào liên kết tới) — truy cập gây 500. Luồng đặt sân dùng /room/{slug} → PayOS.
// Route::get('/thanh-toan', [BladeThemeV1Controller::class, 'paymentPage'])->name('payment.page');
// Đã tắt: chức năng "kiểm tra tên miền" thừa từ dự án khác, view livewire.domain-lookup-detail không
// tồn tại nên truy cập gây 500. Xoá luôn DomainLookup*.php/HandleDomainTrait nếu không cần nữa.
// Route::get('/kiem-tra-ten-mien', [BladeThemeV1Controller::class, 'domainLookupDetail'])->name('domain-lookup.detail');
Route::get('/api/check-domain', function (\Illuminate\Http\Request $request) {
    $domain = $request->query('domain', '');

    // Chỉ cho phép domain hợp lệ (chữ, số, dấu chấm, gạch ngang) — chặn SSRF
    if (!$domain || !preg_match('/^[a-zA-Z0-9\-\.]{2,253}$/', $domain)) {
        return response()->json(['error' => 'Invalid domain'], 422);
    }

    $response = \Illuminate\Support\Facades\Http::timeout(5)
        ->get('https://tracking-domain.goldenbeeltd.vn/', ['domain' => $domain]);

    return $response->json();
});
Route::get('/thong-tin-dat-phong/{code}', [BladeThemeV1Controller::class, 'bookingDetail'])->name('booking.detail');
// Khách tự huỷ đơn/xin hoàn tiền online (xem OrderCancellationService) — trang tra cứu đơn
// (booking-detail.blade.php) là Blade thuần (không Livewire) nên dùng form POST thường thay vì
// wire:click; account-page.blade.php (Livewire) gọi thẳng AccountPage::cancelOrder() không qua
// route này.
Route::post('/thong-tin-dat-phong/{code}/huy', [BladeThemeV1Controller::class, 'cancelBooking'])->name('booking.cancel');
// Upload ảnh biên lai chuyển khoản thủ công — xem BladeThemeV1Controller::uploadPaymentProof(),
// pages/booking-detail.blade.php (form POST thường, cùng pattern với booking.cancel ở trên).
Route::post('/thong-tin-dat-phong/{code}/xac-nhan-chuyen-khoan', [BladeThemeV1Controller::class, 'uploadPaymentProof'])->name('booking.upload-payment-proof');
Route::get('/tai-khoan', [BladeThemeV1Controller::class, 'accountPage'])->name('account.page');
// Quỹ nhóm góp tiền đặt sân — /quy-nhom/tao-moi TRƯỚC /quy-nhom/{code} (route cụ thể hơn phải khai
// báo trước, nếu không "tao-moi" sẽ bị route {code} nuốt mất do khớp trước).
Route::get('/quy-nhom/tao-moi', [BladeThemeV1Controller::class, 'bookingFundCreatePage'])->name('booking-fund.create');
Route::get('/quy-nhom/{code}', [BladeThemeV1Controller::class, 'bookingFundDetailPage'])->name('booking-fund.detail');
Route::get('/yeu-thich', [BladeThemeV1Controller::class, 'favoritesPage'])->name('favorites.page');
Route::get('/dang-nhap', [BladeThemeV1Controller::class, 'loginPage'])->name('login.page');
Route::get('/dang-ky', [BladeThemeV1Controller::class, 'registerPage'])->name('register.page');
Route::get('/ban-do', [BladeThemeV1Controller::class, 'mapPage'])->name('map.page');
Route::get('/kham-pha', [BladeThemeV1Controller::class, 'explorePage'])->name('explore.page');
Route::get('/tin-tuc', [BladeThemeV1Controller::class, 'postsPage'])->name('posts.page');
Route::get('/lam-doi-tac', [BladeThemeV1Controller::class, 'partnerPage'])->name('partner.page');
// Đăng ký đối tác = mua gói (xem pages/partner-onboarding.blade.php, App\Http\Controllers\Api\Public\PartnerPurchaseController).
Route::get('/dang-ky-hop-tac', [BladeThemeV1Controller::class, 'partnerOnboardingPage'])->name('partner-onboarding.page');
// routes/web.php
Route::get('/theme.css', function () {
    $theme = ThemeCache::generalSettings()->site_theme;

    $rgb = fn($hex) => implode(', ', sscanf($hex, "#%02x%02x%02x"));

    return response()->make("
        :root {
            --color-primary: {$theme['primary']};
            --color-primary-rgb: " . $rgb($theme['primary']) . ";
            --color-text-secondary: {$theme['Secondary']};
            --color-secondary: {$theme['secondary']};
            --color-gray: {$theme['gray']};
            --color-success: {$theme['success']};
            --color-danger: {$theme['danger']};
            --color-info: {$theme['info']};
            --color-warning: {$theme['warning']};
            --color-background: {$theme['background']};
            --color-bgDark: {$theme['bg_dark']};
            --color-textDark: {$theme['text_dark']};
            --color-red9C: {$theme['red_9c']};
            --color-borderGray: {$theme['border_gray']};
            --color-tickGreen: {$theme['tick_green']};
            --color-tickYellow: {$theme['tick_yellow']};
            --color-tickGray: {$theme['tick_gray']};
        }
    ", 200, [
        'Content-Type' => 'text/css',
        'Cache-Control' => 'public, max-age=3600'
    ]);
})->name('theme.css');
