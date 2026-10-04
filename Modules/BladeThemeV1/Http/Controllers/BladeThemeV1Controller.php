<?php

namespace Modules\BladeThemeV1\Http\Controllers;

use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Category\Entities\Category;
use Modules\Page\Entities\Page;
use Modules\Page\Entities\PageComponent;
use Modules\BladeThemeV1\Traits\HandleColorTrait;
use Modules\BladeThemeV1\Support\BranchBookConfig;
use Modules\Post\Entities\Post;
use Modules\Product\App\Models\Product;
use App\Models\Province;
use Modules\Payment\Entities\Order;
use Modules\Payment\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Log;

class BladeThemeV1Controller extends Controller
{
    use HandleColorTrait;

    public $primaryColor;
    public string $primaryColorRgb;
    public ?string $heavyPrimaryColor;
    public ?string $lightPrimaryColor;

    public function __construct(
        private readonly GeneralSettings $generalSetting
    )
    {
        $this->primaryColor = $this->getFilamentPrimaryColor();
        if ($this->isHexColor($this->primaryColor)) {
            $this->primaryColorRgb = $this->hexToRgb($this->primaryColor);
        } else {
            $this->primaryColorRgb = $this->primaryColor;
        }

        $this->heavyPrimaryColor = $this->lightenColor($this->primaryColor, 100);
        $this->lightPrimaryColor = $this->darkenColor($this->primaryColor, 30);
    }

    public function index(Request $request)
    {
        $pageId = $request->route('page_id');

        $page = Page::findOrFail($pageId);

        $seoData = [
            'seo_title'       => $page->seo_title       ?? '',
            'seo_description' => $page->seo_description ?? '',
            'seo_keywords'    => $page->seo_keywords    ?? '',
            'og_type'         => 'website',
        ];

        $pageComponents = PageComponent::with(['component', 'pageComponentConfigurationValues'])
            ->where('page_id', $pageId)
            ->get();

        $configuration = $pageComponents->map(function ($component) use ($page) {
            $layout = [];
            $componentData = ['name' => $component->component->name ?? ""];

            foreach ($component->pageComponentConfigurationValues as $config) {
                switch ($config->name) {
                    case 'heading':
                    case 'heading_color':
                    case 'heading_sub':
                    case 'heading_sub_color':
                    case 'heading_alignment':
                    case 'heading_background_image':
                    case 'background_color':
                    case 'background_image':
                    case 'background_linear':
                    case 'gradient_start':
                    case 'gradient_end':
                    case 'gradient_direction':
                    case 'overlay_color':
                    case 'overlay_opacity':
                    case 'layout_style':
                        $layout[$config->name] = $config->pivot->value ?? '';
                        break;
                    default:
                        $componentData[$config->name] = $config->pivot->value ?? 4;
                        break;
                }
            }

            if (!empty($layout['background_image'])) {
                $bgImageData = json_decode($layout['background_image'], true);
                if (is_array($bgImageData) && !empty($bgImageData)) {
                    $layout['background_image'] = reset($bgImageData);
                }
            }

            return [
                'layout' => [
                    'heading' => !empty($layout['heading']) ? $layout['heading'] : false,
                    'heading_color' => !empty($layout['heading_color']) ? $layout['heading_color'] : null,
                    'heading_sub' => !empty($layout['heading_sub']) ? $layout['heading_sub'] : false,
                    'heading_sub_color' => !empty($layout['heading_sub_color']) ? $layout['heading_sub_color'] : null,
                    'heading_background_image' => !empty($layout['heading_background_image']) ? $layout['heading_background_image'] : false,
                    'heading_alignment' => !empty($layout['heading_alignment']) ? $layout['heading_alignment'] : null,
                    'background_color' => !empty($layout['background_color']) ? $layout['background_color'] : false,
                    'background_image' => !empty($layout['background_image']) ? $layout['background_image'] : false,
                    'background_linear' => !empty($layout['background_linear']) ? $layout['background_linear'] : false,
                    'gradient_start' => !empty($layout['gradient_start']) ? $layout['gradient_start'] : false,
                    'gradient_end' => !empty($layout['gradient_end']) ? $layout['gradient_end'] : false,
                    'gradient_direction' => !empty($layout['gradient_direction']) ? $layout['gradient_direction'] : false,
                    'overlay_color' => !empty($layout['overlay_color']) ? $layout['overlay_color'] : '#000000',
                    'overlay_opacity' => !empty($layout['overlay_opacity']) ? ($layout['overlay_opacity'] * 0.01) : '0',
                    'style' => !empty($layout['layout_style']) ? $layout['layout_style'] : false,
                ],
                'component' => $componentData
            ];
        });

        return view('bladethemev1::pages.index', [
            'configuration' => $configuration,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
            'seoData' => $seoData,
            'page' => $page,
        ]);
    }

    public function postDetail($slug)
    {
        $post = Post::with(['user'])->where('slug', $slug)->first();

        if (!$post) {
            abort(404);
        }

        $seoOgImage = null;
        if ($post->hasMedia('Ảnh chính')) {
            $seoOgImage = $post->getFirstMedia('Ảnh chính')->getUrl();
        }

        $seoData = [
            'seo_title'              => $post->seo_title ?? $post->title ?? '',
            'seo_description'        => $post->seo_description ?? '',
            'seo_keywords'           => $post->seo_keywords ?? '',
            'og_image'               => $seoOgImage,
            'og_type'                => 'article',
            'article_published_time' => $post->published_at?->toIso8601String() ?? $post->created_at?->toIso8601String(),
            'article_modified_time'  => $post->updated_at?->toIso8601String(),
            'author_name'            => $post->user?->fullname ?? $post->user?->name ?? '',
            'site_name'              => config('app.name'),
        ];

        return view('bladethemev1::pages.post.detail', [
            'seoData' => $seoData,
            'slug' => $slug,
            'name' => $post->title,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor
        ]);
    }

    public function bookingDetail(Request $request, $code)
    {
        // Tìm đơn hàng theo order_code, load kèm items và product
        $order = Order::with(['items.product'])
            ->where('order_code', $code)
            ->firstOrFail();

        // ── Xử lý return từ PayOS sau khi thanh toán còn lại ──
        // ?remaining_paid=1  → returnUrl cũ (trước fix)
        // ?orderCode=X       → returnUrl mới
        $hintCode = $request->query('orderCode')
            ?: ($request->query('remaining_paid') ? $order->remaining_payos_code : null);

        if ($hintCode && $order->status === 'deposit' && !empty($order->remaining_payos_code)) {
            try {
                $paymentController = app(PaymentController::class);
                $paymentController->checkPaymentStatus($order, (string) $hintCode);
                $order->refresh();
                Log::info('bookingDetail: checkPaymentStatus called', [
                    'order_id' => $order->id, 'new_status' => $order->status,
                ]);
            } catch (\Exception $e) {
                Log::error('bookingDetail: checkPaymentStatus error', ['error' => $e->getMessage()]);
            }
        }

        // Nếu vừa thanh toán đủ → redirect sang success page để hiện thông báo đầy đủ
        if ($order->status === 'paid' && ($request->query('remaining_paid') || $request->query('orderCode'))) {
            return redirect()->route('payment.success', ['orderCode' => $order->order_code]);
        }

        $item = $order->items->first();
        $product = $item ? $item->product : null;

        // Đơn mua gói nhiều buổi (order_type=package_purchase) — không có khung giờ thật,
        // view tự chuyển sang hiện "Gói đã kích hoạt" thay vì thông tin nhận/trả sân thông thường.
        $isPackagePurchase = $order->order_type === 'package_purchase';
        $customerPackage   = $isPackagePurchase
            ? \App\Models\CustomerPackage::where('order_id', $order->id)->first()
            : null;

        // Đơn đặt sân được trả bằng buổi trong gói vẫn là order_type mặc định. Dấu hiệu chính xác
        // của luồng này là các PackageRedemption thuộc đơn; không suy luận từ amount/status vì đơn
        // dùng gói vẫn lưu giá niêm yết để báo cáo nội bộ.
        $packageRedemptions = $isPackagePurchase
            ? collect()
            : \App\Models\PackageRedemption::with('customerPackage')
                ->where('order_id', $order->id)
                ->whereNull('refunded_at')
                ->get();
        $isPackageBooking = $packageRedemptions->isNotEmpty();
        $redeemedPackage  = $packageRedemptions->first()?->customerPackage;

        return view('bladethemev1::pages.booking-detail', compact(
            'order', 'item', 'product', 'isPackagePurchase', 'customerPackage',
            'isPackageBooking', 'packageRedemptions', 'redeemedPackage'
        ) + [
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    // Khách tự huỷ đơn/xin hoàn tiền online — xem App\Services\OrderCancellationService (điều
    // kiện huỷ + tính % hoàn tự động nằm trong đó, view chỉ hiện kết quả).
    public function cancelBooking(Request $request, string $code)
    {
        $order = Order::with('items')->where('order_code', $code)->firstOrFail();

        $result = app(\App\Services\OrderCancellationService::class)->cancelByCustomer($order);

        if (! $result['ok']) {
            return back()->with('cancel_error', $result['error']);
        }

        return redirect()
            ->route('booking.detail', ['code' => $order->order_code])
            ->with('cancel_success', $result['refund_amount']);
    }

    // Khách upload ảnh biên lai chuyển khoản thủ công (payment_method='bank_transfer') — xem
    // pages/booking-detail.blade.php. Đơn KHÔNG tự chuyển 'paid' ở đây — chỉ đánh dấu
    // bank_transfer_status='pending' để admin đối tác vào duyệt thủ công (xem OrderTable's action
    // "Xác nhận chuyển khoản"), đúng luồng đã thống nhất: "khách gửi ảnh, admin xác nhận".
    public function uploadPaymentProof(Request $request, string $code)
    {
        $order = Order::where('order_code', $code)->firstOrFail();

        if ($order->payment_method !== 'bank_transfer' || $order->isCancelled() || in_array($order->status, ['paid', 'refunded'], true)) {
            abort(404);
        }

        $request->validate([
            'payment_proof' => ['required', 'image', 'max:8192'],
        ]);

        $path = $request->file('payment_proof')->store('payment-proofs', 'public');

        $order->update([
            'payment_proof' => $path,
            'bank_transfer_status' => 'pending',
            // Gia hạn thêm thời gian giữ chỗ ngay khi khách vừa upload — tránh đơn hết hạn ngay
            // lúc admin đang xem xét duyệt (vd admin bận, xử lý sau vài chục phút).
            'expired_at' => now()->addHours(2),
        ]);

        return redirect()
            ->route('booking.detail', ['code' => $order->order_code])
            ->with('bank_transfer_uploaded', true);
    }

    public function favoritesPage(Request $request)
    {
        $seoData = [
            'seo_title' => 'Yêu thích',
            'seo_description' => 'Danh sách sân đã lưu',
            'seo_keywords' => 'yêu thích, sân đã lưu, ' . config('app.name'),
            'og_type' => 'website',
        ];

        return view('bladethemev1::pages.favorites', [
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    public function postsPage()
    {
        $seoData = [
            'seo_title' => 'Tin tức',
            'seo_description' => 'Tin tức và bài viết mới nhất',
            'seo_keywords' => 'tin tức, bài viết, ' . config('app.name'),
            'og_type' => 'website',
        ];

        return view('bladethemev1::pages.posts', [
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    // textOnPrimary: màu chữ tương phản đặt lên nền $primaryColor (nút CTA trong form đăng
    // nhập/đăng ký) — cùng công thức luminance đang dùng ở Modules/BladeThemeV1/Livewire/AuthModal.php
    // (giữ nguyên modal đó cho các nơi khác vẫn đang mở popup, xem components/auth/form.blade.php).
    private function textOnPrimaryColor(): string
    {
        $hex = ltrim($this->primaryColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? '#1a1e25' : '#ffffff';
    }

    public function loginPage()
    {
        $seoData = [
            'seo_title' => 'Đăng nhập',
            'seo_description' => 'Đăng nhập tài khoản ' . config('app.name') . ' để đặt sân nhanh hơn và nhận ưu đãi dành riêng cho thành viên.',
            'seo_keywords' => 'đăng nhập, ' . config('app.name'),
            'og_type' => 'website',
        ];

        return view('bladethemev1::pages.login', [
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
            'textOnPrimary' => $this->textOnPrimaryColor(),
        ]);
    }

    public function registerPage()
    {
        $seoData = [
            'seo_title' => 'Đăng ký',
            'seo_description' => 'Tạo tài khoản để đặt sân nhanh hơn và nhận ưu đãi dành riêng cho thành viên.',
            'seo_keywords' => 'đăng ký, tạo tài khoản',
            'og_type' => 'website',
        ];

        return view('bladethemev1::pages.register', [
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
            'textOnPrimary' => $this->textOnPrimaryColor(),
        ]);
    }

    public function mapPage()
    {
        $seoData = [
            'seo_title' => 'Bản đồ sân thể thao',
            'seo_description' => 'Tìm sân thể thao gần bạn trên bản đồ.',
            'og_type' => 'website',
        ];

        return view('bladethemev1::pages.map', [
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    public function explorePage()
    {
        $seoData = [
            'seo_title' => 'Khám phá',
            'seo_description' => 'Tin tức, ưu đãi và sự kiện mới nhất.',
            'og_type' => 'website',
        ];

        return view('bladethemev1::pages.explore', [
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    public function partnerOnboardingPage(Request $request)
    {
        return view('bladethemev1::pages.partner-onboarding', [
            'seoData' => [
                'seo_title' => 'Đăng ký đối tác ' . config('app.name'),
                'seo_description' => 'Đăng ký làm đối tác ' . config('app.name') . ': chọn gói, dùng thử và nhận tài khoản quản lý sân qua email.',
                'og_type' => 'website',
            ],
            'planSlug' => (string) $request->query('goi', ''),
            'code' => (string) $request->query('ma', ''),
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    public function partnerPage()
    {
        $seoData = [
            'seo_title' => 'Làm đối tác cùng ' . config('app.name'),
            'seo_description' => 'Đăng ký trở thành đối tác ' . config('app.name') . ' — đưa sân thể thao của bạn lên nền tảng đặt lịch trực tuyến, tiếp cận thêm khách hàng, quản lý lịch đặt và doanh thu dễ dàng.',
            'og_type' => 'website',
        ];

        // Form "Đăng ký làm đối tác" (Modules\Form) — tạo sẵn qua tinker, dùng chung component
        // render/submit có sẵn @livewire('bladethemev1::form-contact', ...) đang phục vụ trang
        // Liên hệ (Contact.php) — không viết lại logic validate/lưu submission/gửi mail. Tra theo
        // tên thay vì hardcode ID để không vỡ nếu re-seed dữ liệu.
        $partnerForm = \Modules\Form\Entities\Form::with('formFields')
            ->where('name', 'Đăng ký làm đối tác')
            ->first();

        // Bảng giá lấy THẲNG từ dữ liệu admin đã cấu hình (App\Models\PartnerPlan, trang "Gói đối
        // tác"/"Tính năng theo gói") — trước đây là mảng PHP viết cứng ngay trong view, không đồng
        // bộ với gói thật trong DB (bug đã gặp: sửa gói ở admin không phản ánh ra trang quảng cáo).
        $partnerPlans = \App\Models\PartnerPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('bladethemev1::pages.partner', [
            'seoData' => $seoData,
            'partnerForm' => $partnerForm,
            'partnerPlans' => $partnerPlans,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    // Trang landing page RIÊNG cho 1 đối tác, phục vụ theo subdomain (vd mamble.365sport-cms.test)
    // — xem route domain-group ở Routes/web.php. DÙNG CHUNG 1 giao diện duy nhất
    // (pages/partner-landing.blade.php) cho MỌI đối tác, chỉ khác nhau ở DỮ LIỆU truyền vào (tên,
    // logo, danh sách chi nhánh của ĐÚNG đối tác đó) — không nhân bản toàn bộ trang chủ/luồng đặt
    // sân sang subdomain (phạm vi đã chốt với người dùng: "không phải toàn bộ trang client, chỉ 1
    // landing page chung"). Nút "Đặt lịch" trên trang này trỏ THẲNG về route('branch.booking') ở
    // domain CHÍNH (không lặp lại luồng đặt sân dưới subdomain) — tránh phải xử lý session/auth
    // dùng chung giữa domain gốc và subdomain (2 origin khác nhau, cookie không tự chia sẻ).
    public function partnerLandingPage(string $partnerSlug)
    {
        $partner = \App\Models\Partner::where('slug', $partnerSlug)
            ->where('status', true)
            ->first();

        // 2 điều kiện ĐỘC LẬP đều phải đúng thì trang mới hiển thị: (1) GÓI của đối tác cho phép
        // dùng tính năng này (hasFeature — admin cấu hình ở "Tính năng theo gói"), VÀ (2) chính đối
        // tác/admin đang BẬT công tắc landing_page_enabled riêng (mặc định true, cho phép tạm ẩn
        // trang mà không cần xoá subdomain — vd đang chỉnh sửa nội dung, hoặc gói bị hạ xuống không
        // còn tính năng này nữa). Không có slug khớp / đối tác bị khoá → cùng 404, không lộ thông
        // tin đối tác không được phép hiển thị trang riêng.
        if (! $partner || ! $partner->landing_page_enabled || ! $partner->hasFeature('landing_page')) {
            abort(404);
        }

        // route('branch.booking', ...) mặc định sinh URL theo HOST CỦA REQUEST HIỆN TẠI (Laravel
        // chỉ ép domain cố định cho route có ->domain() tường minh — route này thì không), nên nếu
        // gọi thẳng route() ở đây, link "Đặt lịch" trên trang landing sẽ VẪN Ở LẠI subdomain
        // (vd mamble.365sport-cms.test/branch/...) thay vì sang domain chính như đã định — kéo theo
        // cả trang đặt sân đầy đủ (top-bar/search-bar/menu) hiện SAI màu (dùng
        // GeneralSettings->site_theme của toàn site, không phải landing_primary_color của riêng
        // trang landing — 2 khái niệm màu tách biệt, không đồng bộ được vì luồng đặt sân là 1 trang
        // DÙNG CHUNG cho mọi đối tác/khách hàng, không thể tô theo từng đối tác) (bug đã gặp: "sao
        // không dùng màu chủ đạo cho các trang này, mà lại là màu trang chính"). Fix bằng cách GHÉP
        // THỦ CÔNG base URL của domain chính (APP_URL) với path tương đối của route — ép tuyệt đối
        // sang đúng domain chính bất kể đang đứng ở subdomain nào.
        $mainSiteBaseUrl = rtrim((string) config('app.url'), '/');

        // Cùng cấu trúc mảng $venue với VenueGrid::loadVenues() (trang chủ) để tái dùng NGUYÊN VẸN
        // component <x-bladethemev1::venues.card> — chỉ khác điều kiện lọc: theo ĐÚNG partner_id
        // này thay vì toàn bộ hệ thống.
        // KHÔNG dùng ->limit(1) trong closure eager-load 'products' — Category::get() ở đây trả về
        // NHIỀU chi nhánh cùng lúc, limit() trong 1 quan hệ hasMany/belongsToMany áp cho TOÀN BỘ
        // câu query gộp (WHERE category_id IN (...) LIMIT 1), không phải riêng từng chi nhánh, nên
        // chỉ 1 chi nhánh DUY NHẤT nhận được product (xem giải thích đầy đủ ở MapPage.php và
        // VenueGrid.php — bug đã gặp nhiều lần trong dự án này). Lấy full rồi ->first() trong PHP.
        $venues = Category::where('partner_id', $partner->id)
            ->where('category_type', 'product')
            ->where('status', true)
            ->with('products')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($category) use ($mainSiteBaseUrl) {
                $product = $category->products->first();

                return [
                    'name'       => $category->name,
                    'url'        => $mainSiteBaseUrl . route('branch.booking', ['slug' => $category->slug], false),
                    'image_url'  => $category->image
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($category->image)
                        : ($product?->getFirstMediaUrl('Ảnh bìa') ?: null),
                    'address'    => $product->address ?? null,
                    'lat'        => $product->latitude ?? null,
                    'lng'        => $product->longitude ?? null,
                    'rating'     => $product && $product->rating_score > 0 ? number_format((float) $product->rating_score, 1) : null,
                    'hours'      => $this->formatBranchHours($category->checkin_time, $category->checkout_time),
                    'product_id' => $product->id ?? null,
                ];
            })
            ->values();

        $seoData = [
            'seo_title' => $partner->name . ' — Đặt sân trực tuyến',
            'seo_description' => 'Danh sách chi nhánh và sân thể thao của ' . $partner->name . ' — đặt lịch trực tuyến nhanh chóng.',
            'og_type' => 'website',
        ];

        // Màu chủ đạo RIÊNG của trang landing (admin cấu hình ở tab "Doanh nghiệp" của
        // PartnerResource, ColorPicker 'landing_primary_color') — mặc định xanh lá thương hiệu nếu
        // đối tác chưa tự chọn màu. Dùng lại HandleColorTrait (đã có sẵn ở constructor cho màu
        // TOÀN SITE) để tính thêm 2 sắc độ cho gradient hero/hover, tách biệt hoàn toàn khỏi
        // $this->primaryColor (đó là màu toàn site, không liên quan tới trang landing này).
        $landingColor      = $partner->landing_primary_color ?: '#0B6B3A';
        $landingColorHeavy = $this->lightenColor($landingColor, 60) ?: '#128A4A';
        $landingColorLight = $this->darkenColor($landingColor, 20) ?: '#1AA859';

        // Nội dung linh hoạt kiểu "sườn sẵn giống WP" (Repeater trong PartnerForm) — đối tác chưa tự
        // cấu hình thì dùng bộ mặc định ở đây để trang KHÔNG BAO GIỜ trống trơn ngay khi mới bật
        // tính năng, admin có thể ghi đè từng mục bất kỳ lúc nào qua tab "Trang landing".
        $defaults = $this->defaultLandingSections();

        $pricingPlans = collect($partner->landing_pricing_plans ?: $defaults['pricing_plans'])
            ->map(function ($plan) {
                // Repeater lưu "features" dạng textarea nhiều dòng (string) — bộ mặc định đã sẵn
                // mảng PHP nên chỉ cần explode() khi giá trị thật là string (từ admin nhập).
                $plan['features'] = is_array($plan['features'] ?? null)
                    ? $plan['features']
                    : array_filter(array_map('trim', explode("\n", (string) ($plan['features'] ?? ''))));

                return $plan;
            });

        // 4 mẫu giao diện landing page khác nhau (đối tác/admin tự chọn qua tab "Trang landing",
        // PartnerForm) — dùng CHUNG NGUYÊN VẸN toàn bộ dữ liệu bên dưới, chỉ khác file view/CSS
        // hiển thị. Giá trị lạ/rỗng (dữ liệu cũ trước khi có field này) tự rơi về 'bento' (mẫu gốc),
        // không lỗi trang trắng.
        $templateViews = [
            'bento'     => 'bladethemev1::pages.partner-landing',
            'editorial' => 'bladethemev1::pages.partner-landing-editorial',
            'minimal'   => 'bladethemev1::pages.partner-landing-minimal',
            'split'     => 'bladethemev1::pages.partner-landing-split',
            'neon'      => 'bladethemev1::pages.partner-landing-neon',
            'classic'   => 'bladethemev1::pages.partner-landing-classic',
            'playful'   => 'bladethemev1::pages.partner-landing-playful',
        ];
        $templateView = $templateViews[$partner->landing_template] ?? $templateViews['bento'];

        return view($templateView, [
            'seoData' => $seoData,
            'partner' => $partner,
            'partnerLogoUrl' => $partner->getFirstMediaUrl('landing_logo') ?: null,
            'heroImageUrl' => $partner->getFirstMediaUrl('landing_hero_image') ?: null,
            'heroSecondaryImageUrl' => $partner->getFirstMediaUrl('landing_hero_secondary') ?: null,
            'faqImageUrl' => $partner->getFirstMediaUrl('landing_faq_image') ?: null,
            'heroYoutubeId' => $this->extractYoutubeId($partner->landing_hero_video_url),
            // Điểm đánh giá TRUNG BÌNH THẬT tính từ chính các chi nhánh của đối tác (không phải số
            // giả trang trí) — hiển thị ở thẻ đánh giá trong banner bento (tham khảo mẫu Mona
            // Pickleball). null nếu chưa chi nhánh nào có rating_score > 0.
            'avgRating' => $venues->pluck('rating')->filter()->map(fn ($r) => (float) $r)->avg(),
            'venues' => $venues,
            'tagline' => $partner->landing_tagline ?: 'Đặt sân thể thao trực tuyến nhanh chóng, tiện lợi.',
            'description' => $partner->landing_description,
            'facebookUrl' => $partner->landing_facebook_url,
            'features' => $partner->landing_features ?: $defaults['features'],
            'services' => $this->resolveUploadImages($partner->landing_services ?: $defaults['services']),
            'stats' => $partner->landing_stats ?: $defaults['stats'],
            'testimonials' => $this->resolveUploadImages($partner->landing_testimonials ?: []),
            'pricingPlans' => $pricingPlans,
            'faqs' => $partner->landing_faqs ?: $defaults['faqs'],
            'news' => $this->resolveUploadImages($partner->landing_news ?: []),
            'landingColor' => $landingColor,
            'landingColorHeavy' => $landingColorHeavy,
            'landingColorLight' => $landingColorLight,
            // layouts/master.blade.php luôn cần 4 biến này (dùng cho --color-primary TOÀN SITE ở
            // <head>, không phải màu riêng của trang landing) — mọi controller render qua layout
            // này đều phải truyền, không riêng gì trang landing (xem constructor: đã tính sẵn từ
            // GeneralSettings->site_theme).
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    // Y hệt VenueGrid::formatHours() (trang chủ) — chuỗi "HH:MM - HH:MM" hiển thị trên thẻ chi
    // nhánh ở trang landing, ẩn nếu chi nhánh thiếu 1 trong 2 mốc giờ.
    private function formatBranchHours(?string $checkin, ?string $checkout): ?string
    {
        if (! $checkin || ! $checkout) {
            return null;
        }

        try {
            return substr($checkin, 0, 5) . ' - ' . substr($checkout, 0, 5);
        } catch (\Throwable) {
            return null;
        }
    }

    // Tách ID video từ link YouTube dạng bất kỳ (watch?v=, youtu.be/, embed/, shorts/) để nhúng
    // TRỰC TIẾP bằng <iframe> ngay trên trang landing (yêu cầu người dùng: "hiển thị video youtube
    // trực tiếp luôn" — trước đây chỉ là link mở tab mới, không phải khung phát trực tiếp).
    private function extractYoutubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    // Chuyển 'image' của mỗi phần tử Repeater (landing_services/testimonials/news) từ ĐƯỜNG DẪN
    // TƯƠNG ĐỐI (Forms\Components\FileUpload thường lưu path trên disk 'public', không phải
    // SpatieMediaLibraryFileUpload nên KHÔNG có sẵn accessor URL) sang URL đầy đủ. Bộ nội dung mặc
    // định (defaultLandingSections()) không có ảnh (image => null) nên hàm này AN TOÀN bỏ qua,
    // không lỗi khi thiếu key.
    private function resolveUploadImages(array $items): array
    {
        return collect($items)->map(function ($item) {
            $path = $item['image'] ?? null;

            if ($path && ! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
                $item['image'] = \Illuminate\Support\Facades\Storage::disk('public')->url($path);
            }

            return $item;
        })->all();
    }

    // Nội dung MẶC ĐỊNH cho các khối linh hoạt của trang landing — dùng khi đối tác CHƯA tự cấu
    // hình (landing_features/services/stats/faqs rỗng), để trang không bao giờ trống trơn ngay khi
    // mới bật tính năng (kiểu "sườn sẵn giống WP" có demo content, admin chỉnh sửa dần sau).
    // Testimonials/news/pricing_plans KHÔNG có bộ mặc định (rỗng thì ẩn hẳn mục đó) vì đây là nội
    // dung mang tính "bằng chứng xã hội"/giá bán THẬT của riêng từng đối tác — hiển thị nội dung
    // giả khi chưa cấu hình dễ gây hiểu nhầm hơn là chỉ đơn giản ẩn mục đi.
    private function defaultLandingSections(): array
    {
        return [
            'features' => [
                ['icon' => 'heroicon-o-bolt', 'title' => 'Đặt lịch nhanh chóng', 'description' => 'Xem lịch trống theo thời gian thực, chọn khung giờ và đặt sân chỉ trong vài giây.'],
                ['icon' => 'heroicon-o-shield-check', 'title' => 'Thanh toán an toàn', 'description' => 'Hỗ trợ nhiều phương thức thanh toán, xác nhận đơn tức thì, không lo sai lịch.'],
                ['icon' => 'heroicon-o-map-pin', 'title' => 'Nhiều chi nhánh', 'description' => 'Dễ dàng tìm và chọn chi nhánh gần bạn nhất.'],
                ['icon' => 'heroicon-o-clock', 'title' => 'Linh hoạt thời gian', 'description' => 'Mở cửa từ sáng sớm tới tối muộn, chủ động sắp xếp lịch chơi.'],
            ],
            'services' => [
                ['image' => null, 'title' => 'Thuê sân theo giờ', 'description' => 'Đặt sân linh hoạt theo từng khung giờ phù hợp với lịch của bạn.'],
                ['image' => null, 'title' => 'Gói thuê sân theo tuần/tháng', 'description' => 'Tiết kiệm hơn khi đăng ký thuê sân dài hạn, cố định khung giờ yêu thích.'],
                ['image' => null, 'title' => 'Sân cho đội/nhóm', 'description' => 'Không gian rộng rãi, phù hợp tổ chức buổi tập cho cả đội.'],
                ['image' => null, 'title' => 'Cho thuê thiết bị', 'description' => 'Đầy đủ dụng cụ thi đấu cho thuê ngay tại sân, không cần mang theo.'],
                ['image' => null, 'title' => 'Tổ chức giải đấu', 'description' => 'Hỗ trợ tổ chức giải đấu phong trào, sự kiện thể thao quy mô nhỏ và vừa.'],
            ],
            'stats' => [
                ['number' => '20+', 'label' => 'Năm kinh nghiệm'],
                ['number' => '20+', 'label' => 'Chương trình đào tạo'],
                ['number' => '2K+', 'label' => 'Khách hàng'],
                ['number' => '80+', 'label' => 'Huấn luyện viên'],
            ],
            'faqs' => [
                ['question' => 'Tôi có thể đặt sân trước bao lâu?', 'answer' => 'Bạn có thể đặt sân trực tuyến bất cứ lúc nào, xem lịch trống theo thời gian thực ngay trên trang.'],
                ['question' => 'Có thể huỷ hoặc đổi lịch đặt sân không?', 'answer' => 'Vui lòng liên hệ trực tiếp qua số điện thoại hoặc Facebook để được hỗ trợ huỷ/đổi lịch.'],
                ['question' => 'Có hỗ trợ cho thuê thiết bị không?', 'answer' => 'Một số chi nhánh có hỗ trợ cho thuê thiết bị thi đấu — vui lòng liên hệ trước khi tới sân.'],
            ],
            'pricing_plans' => [],
        ];
    }

    public function productDetail($slug)
    {
        $product = Product::where([
            'slug' => $slug,
            'is_activated' => true,
        ])
            ->where('type', 'simple')
            ->whereHas('categories', function ($query) {
                $query->where('status', 1);
            })
            ->with('tags:id,name')
            ->select(['id', 'name', 'slug', 'short_description', 'description', 'price', 'discount', 'is_in_stock', 'updated_at'])
            ->first();

        if (!$product) {
            abort(404);
        }

        $seoKeywords    = $product->tags->pluck('name')->implode(', ');
        $seoDescription = html_entity_decode(strip_tags($product->short_description ?? ''));
        $seoOgImage     = $product->hasMedia('Ảnh bìa')
                            ? $product->getFirstMedia('Ảnh bìa')->getUrl()
                            : null;
        $offerPrice     = $product->discount > 0
                            ? (float) $product->price - (float) $product->discount
                            : (float) $product->price;

        // Extract YouTube video IDs from description
        preg_match_all(
            '/(?:youtube\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $product->description ?? '',
            $ytMatches
        );
        $videoIds = array_unique($ytMatches[1]);

        $seoData = [
            'seo_title'          => $product->name,
            'seo_description'    => $seoDescription,
            'seo_keywords'       => $seoKeywords,
            'og_image'           => $seoOgImage,
            'og_type'            => 'product',
            'offer_price'        => $offerPrice,
            'offer_currency'     => 'VND',
            'offer_availability' => $product->is_in_stock ? 'InStock' : 'OutOfStock',
            'offer_url'          => url()->current(),
            'offer_sku'          => $product->slug,
            'video_ids'          => $videoIds,
            'video_name'         => $product->name,
            'video_description'  => $seoDescription,
            'video_upload_date'  => $product->updated_at?->toIso8601String(),
        ];

        return view('bladethemev1::pages.product.detail', [
            'slug' => $slug,
            'name' => $product->name,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
            'seoData' => $seoData,
        ]);
    }

    public function templateDetail($slug)
    {
        $template = Product::where([
            'slug' => $slug,
            'is_activated' => true,
            'type' => 'service'
        ])->whereHas('categories', function ($query) {
            $query->where('status', 1);
        })->with('tags:id,name')->first();

        if (!$template) {
            abort(404);
        }

        $seoKeywords    = $template->tags->pluck('name')->implode(', ');
        $seoDescription = html_entity_decode(strip_tags($template->short_description ?? ''));
        $seoOgImage     = $template->hasMedia('Ảnh bìa')
                            ? $template->getFirstMedia('Ảnh bìa')->getUrl()
                            : null;
        $offerPrice     = $template->discount > 0
                            ? (float) $template->price - (float) $template->discount
                            : (float) $template->price;

        preg_match_all(
            '/(?:youtube\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $template->description ?? '',
            $ytMatches
        );
        $videoIds = array_unique($ytMatches[1]);

        $seoData = [
            'seo_title'          => $template->name,
            'seo_description'    => $seoDescription,
            'seo_keywords'       => $seoKeywords,
            'og_image'           => $seoOgImage,
            'og_type'            => 'product',
            'offer_price'        => $offerPrice,
            'offer_currency'     => 'VND',
            'offer_availability' => $template->is_in_stock ? 'InStock' : 'OutOfStock',
            'offer_url'          => url()->current(),
            'offer_sku'          => $template->slug,
            'video_ids'          => $videoIds,
            'video_name'         => $template->name,
            'video_description'  => $seoDescription,
            'video_upload_date'  => $template->updated_at?->toIso8601String(),
        ];

        return view('bladethemev1::pages.template.detail', [
            'slug' => $slug,
            'name' => $template->name,
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor
        ]);
    }

    public function domainLookupDetail()
    {
        $this->primaryColor = $this->getFilamentPrimaryColor();

        return view('bladethemev1::pages.domain-lookup.index', [
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor
        ]);
    }

    public function home()
    {
        $seoData = [
            'seo_title'       => config('app.name', '365Sport'),
            'seo_description' => 'Đặt sân thể thao theo giờ chất lượng tại 365Sport',
            'seo_keywords'    => config('app.name') . ', đặt sân thể thao, đặt sân theo giờ, sân pickleball, sân cầu lông, sân bóng đá mini, sân tennis',
            'og_type'         => 'website',
        ];

        return view('bladethemev1::pages.home', [
            'primaryColor'     => $this->primaryColor,
            'primaryColorRgb'  => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
            'seoData'          => $seoData,
        ]);
    }

    public function searchProduct(Request $request, string $location = '')
    {
        // Ưu tiên location trên path (kiểu /s/ho-chi-minh), fallback query string
        // ?location=... để không phá các link cũ đã chia sẻ/bookmark trước khi đổi cấu trúc URL.
        $location = $location ?: $request->query('location', '');

        // Resolve province for map
        $province = null;
        $mapLat   = 16.0;
        $mapLng   = 106.0;
        $mapZoom  = 6;

        if ($location) {
            $province = Province::where('slug', $location)->first();
            if ($province && $province->lat && $province->lng) {
                $mapLat  = (float) $province->lat;
                $mapLng  = (float) $province->lng;
                $mapZoom = 13;
            }
        }

        $seoData = [
            'seo_title'       => 'Tìm kiếm sân' . ($province ? ' tại ' . $province->name : '') . ' | 365Sport',
            'seo_description' => 'Tìm kiếm sân thể thao theo giờ chất lượng tại 365Sport.',
            'seo_keywords'    => 'tìm kiếm sân, đặt sân, sân theo giờ, ' . config('app.name'),
            'og_type'         => 'website',
        ];

        return view('bladethemev1::pages.product.search', [
            'seoData'          => $seoData,
            'primaryColor'     => $this->primaryColor,
            'primaryColorRgb'  => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
            'province'         => $province,
            'location'         => $location,
            'mapLat'           => $mapLat,
            'mapLng'           => $mapLng,
            'mapZoom'          => $mapZoom,
            // Xem ghi chú đầy đủ tại config('services.carto') + .env.example (CARTO_BASEMAPS_API_KEY).
            'cartoBasemapsKey' => config('services.carto.basemaps_key'),
        ]);
    }

    public function bookingBoard(string $slug)
    {
        $result = BranchBookConfig::build($slug);

        abort_unless($result, 404);

        $branch = $result['branch'];
        $bookConfig = $result['bookConfig'];

        $seoData = [
            'seo_title' => 'Đặt sân ' . $branch->name . ' | 365Sport',
            'seo_description' => 'Bảng đặt sân theo khung giờ tại chi nhánh ' . $branch->name . '.',
            'seo_keywords' => 'đặt sân, khung giờ, ' . $branch->name,
            'og_type' => 'website',
        ];

        return view('bladethemev1::pages.booking-board', [
            'seoData' => $seoData,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
            'branch' => $branch,
            'bookConfig' => $bookConfig,
        ]);
    }

    public function paymentPage()
    {
        return view('bladethemev1::pages.payment.payment', [
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor
        ]);
    }

    public function accountPage()
    {
        return view('bladethemev1::pages.account.index', [
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    // Quỹ nhóm góp tiền đặt sân — xem Modules/BladeThemeV1/Livewire/BookingFund/*. Layout gốc
    // (layouts/master.blade.php) BẮT BUỘC có $primaryColor/$primaryColorRgb — mọi page controller
    // khác trong file này đều truyền 2 biến này, thiếu sẽ vỡ trang (bug đã gặp: 500 "Undefined
    // variable $primaryColor" ngay lần test đầu).
    public function bookingFundCreatePage()
    {
        return view('bladethemev1::pages.booking-fund.create', [
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

    public function bookingFundDetailPage(string $code)
    {
        return view('bladethemev1::pages.booking-fund.detail', [
            'code' => $code,
            'primaryColor' => $this->primaryColor,
            'primaryColorRgb' => $this->primaryColorRgb,
            'heavyPrimaryColor' => $this->heavyPrimaryColor,
            'lightPrimaryColor' => $this->lightPrimaryColor,
        ]);
    }

}
