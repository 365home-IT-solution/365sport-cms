@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Trang landing page RIÊNG cho 1 đối tác theo subdomain — biến thể thiết kế thứ 2 ("Split"),
         song song với partner-landing.blade.php (biến thể "Bento" tối màu). Đối tác nào dùng mẫu
         nào do admin chọn ở PartnerForm (landing_template), controller vẫn CHỈ 1 hàm
         partnerLandingPage() truyền đúng CÙNG MỘT bộ biến cho cả 2 view.

         Ngôn ngữ thị giác: ấm áp, gần gũi kiểu câu lạc bộ thể thao cộng đồng — nền kem/trắng ấm
         (không trắng lạnh, không nền đen như bản Bento), ảnh bo góc lớn (1.5–2rem), bố cục "split"
         xen kẽ ảnh-trái/chữ-phải rồi chữ-trái/ảnh-phải xuyên suốt trang, nút bo tròn pill mềm mại.
         Mọi khối tuỳ chọn vẫn tự ẩn khi rỗng — CÙNG một hợp đồng biến với bản Bento, chỉ khác lớp vỏ
         HTML/CSS (scope riêng .partner-landing-split-scope, không đụng file kia). --}}
    <div class="partner-landing-split-scope" style="
        --lp-split-color: {{ $landingColor }};
        --lp-split-color-heavy: {{ $landingColorHeavy }};
        --lp-split-color-light: {{ $landingColorLight }};
    ">
        {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
        <header class="lps-header">
            <div class="lps-header-inner">
                <a href="#" class="lps-brand">
                    @if ($partnerLogoUrl)
                        <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lps-brand-logo">
                    @else
                        <span class="lps-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                    @endif
                    <span class="lps-brand-name">{{ $partner->name }}</span>
                </a>

                <nav class="lps-header-nav">
                    @if ($description)
                        <a href="#lp-about" class="lps-header-link">Giới thiệu</a>
                    @endif
                    <a href="#lp-services" class="lps-header-link">Dịch vụ</a>
                    @if ($pricingPlans->isNotEmpty())
                        <a href="#lp-pricing" class="lps-header-link">Bảng giá</a>
                    @endif
                    <a href="#lp-venues" class="lps-header-link">Chi nhánh</a>
                    <a href="#lp-contact" class="lps-header-link">Liên hệ</a>
                    @if ($partner->phone)
                        <a href="tel:{{ $partner->phone }}" class="lps-header-contact">
                            <x-heroicon-o-phone class="h-4 w-4" />
                            <span>{{ $partner->phone }}</span>
                        </a>
                    @endif
                    <a href="#lp-venues" class="lps-header-cta">Đặt sân ngay</a>
                </nav>
            </div>
        </header>

        {{-- ── HERO — split: chữ + CTA bên trái, cụm ảnh (heroImageUrl chính + heroSecondaryImageUrl
             lồng góc) bên phải. Nếu có cấu hình video, bấm vào ảnh chính sẽ nhúng iframe YouTube
             (lite-embed, giống hệt cơ chế bên bản Bento) thay vì mở tab mới. --}}
        <section class="lps-hero">
            <div class="lps-hero-inner">
                <div class="lps-hero-content">
                    <span class="lps-eyebrow">Chào mừng bạn đến với {{ $partner->name }}</span>
                    <h1 class="lps-hero-title">{{ $tagline }}</h1>
                    <p class="lps-hero-desc">{{ $description ?: ('Đặt sân trực tuyến tại ' . $partner->name . ' — chọn chi nhánh, xem lịch trống theo thời gian thực và đặt lịch chỉ trong vài bước.') }}</p>

                    <div class="lps-hero-actions">
                        <a href="#lp-venues" class="lps-btn-primary">
                            <span>Đặt sân ngay</span>
                            <x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lps-btn-ghost">
                                <x-heroicon-o-phone class="h-4 w-4" />
                                <span>{{ $partner->phone }}</span>
                            </a>
                        @endif
                    </div>

                    <div class="lps-hero-rating">
                        @if ($avgRating)
                            <div class="lps-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 {{ $i <= round($avgRating) ? '' : 'is-dim' }}" />
                                @endfor
                            </div>
                            <span>{{ number_format($avgRating, 1) }} / 5 — đánh giá từ khách hàng</span>
                        @else
                            <div class="lps-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 is-dim" />
                                @endfor
                            </div>
                            <span>Chưa có đánh giá</span>
                        @endif
                    </div>
                </div>

                <div class="lps-hero-media" @if ($heroYoutubeId) x-data="{ playing: false }" @endif>
                    <div class="lps-hero-media-main">
                        @if ($heroImageUrl)
                            <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="lazy" @if ($heroYoutubeId) x-show="!playing" @endif>
                        @elseif ($heroYoutubeId)
                            <img src="https://i3.ytimg.com/vi/{{ $heroYoutubeId }}/hqdefault.jpg" alt="{{ $partner->name }}" x-show="!playing">
                        @else
                            <div class="lps-media-placeholder"><x-heroicon-o-photo class="h-10 w-10" /></div>
                        @endif

                        @if ($heroYoutubeId)
                            <div class="lps-hero-scrim" x-show="!playing"></div>
                            <button type="button" class="lps-play-btn" x-show="!playing" @click="playing = true" aria-label="Xem video giới thiệu">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M8 5v14l11-7z" /></svg>
                            </button>
                            <template x-if="playing">
                                <iframe
                                    class="lps-hero-video-frame"
                                    src="https://www.youtube-nocookie.com/embed/{{ $heroYoutubeId }}?autoplay=1&rel=0"
                                    title="Video giới thiệu {{ $partner->name }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </template>
                        @endif
                    </div>

                    {{-- Ảnh phụ lồng góc dưới-trái, viền trắng dày kiểu "polaroid" ấm áp — dùng
                         heroSecondaryImageUrl để KHÔNG bỏ phí ảnh phụ đã cấu hình. --}}
                    <div class="lps-hero-media-sub">
                        @if ($heroSecondaryImageUrl)
                            <img src="{{ $heroSecondaryImageUrl }}" alt="{{ $partner->name }}" loading="lazy">
                        @else
                            <div class="lps-media-placeholder"><x-heroicon-o-photo class="h-6 w-6" /></div>
                        @endif
                    </div>

                    {{-- Thẻ mini nổi — 2 số liệu đầu của landing_stats (dữ liệu thật, không trang trí
                         giả), rơi vào trường hợp rỗng thì hiện số chi nhánh thật. --}}
                    <div class="lps-hero-stat-chip">
                        @forelse (array_slice($stats, 0, 2) as $stat)
                            <div class="lps-hero-stat-item">
                                <strong>{{ $stat['number'] }}</strong>
                                <span>{{ $stat['label'] }}</span>
                            </div>
                        @empty
                            <div class="lps-hero-stat-item">
                                <strong>{{ $venues->count() }}</strong>
                                <span>Chi nhánh</span>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- ── ĐIỂM NỔI BẬT ───────────────────────────────────────────────────── --}}
        <section class="lps-features">
            <div class="lps-features-inner lps-reveal">
                <div class="lps-section-head">
                    <span class="lps-eyebrow">Vì sao chọn chúng tôi</span>
                    <h2 class="lps-section-title">Điều thú vị về {{ $partner->name }}</h2>
                </div>
                <div class="lps-features-grid">
                    @foreach ($features as $feature)
                        <div class="lps-feature">
                            <span class="lps-feature-icon">
                                <x-dynamic-component :component="$feature['icon'] ?? 'heroicon-o-star'" class="h-6 w-6" />
                            </span>
                            <h3>{{ $feature['title'] }}</h3>
                            @if (! empty($feature['description']))
                                <p>{{ $feature['description'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── GIỚI THIỆU (chỉ hiện khi admin có nhập landing_description) ──────── --}}
        @if ($description)
            <section id="lp-about" class="lps-about">
                <div class="lps-about-inner lps-reveal">
                    <span class="lps-eyebrow">Câu chuyện của chúng tôi</span>
                    <h2 class="lps-section-title" style="text-align:left;">Giới thiệu về {{ $partner->name }}</h2>
                    <p class="lps-about-text">{{ $description }}</p>
                </div>
            </section>
        @endif

        {{-- ── DỊCH VỤ — mỗi dịch vụ 1 hàng split, xen kẽ ảnh trái/phải liên tục ─────── --}}
        <section id="lp-services" class="lps-services">
            <div class="lps-services-inner">
                <div class="lps-section-head lps-reveal">
                    <span class="lps-eyebrow">Dịch vụ</span>
                    <h2 class="lps-section-title">Cung cấp dịch vụ thuê sân tốt nhất</h2>
                </div>

                <div class="lps-services-list">
                    @foreach ($services as $i => $service)
                        <div class="lps-split {{ $i % 2 === 1 ? 'is-reverse' : '' }} lps-reveal">
                            <div class="lps-split-media">
                                @if (! empty($service['image']))
                                    <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy">
                                @else
                                    <div class="lps-media-placeholder"><x-heroicon-o-photo class="h-10 w-10" /></div>
                                @endif
                            </div>
                            <div class="lps-split-content">
                                <span class="lps-split-index">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <h3>{{ $service['title'] }}</h3>
                                @if (! empty($service['description']))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                                <a href="#lp-venues" class="lps-link-cta">
                                    <span>Đặt sân ngay</span>
                                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── BẢNG GIÁ (chỉ hiện khi admin có cấu hình landing_pricing_plans) ──── --}}
        @if ($pricingPlans->isNotEmpty())
            <section id="lp-pricing" class="lps-pricing">
                <div class="lps-pricing-inner lps-reveal">
                    <div class="lps-section-head">
                        <span class="lps-eyebrow">Bảng giá</span>
                        <h2 class="lps-section-title">Chọn gói phù hợp với bạn</h2>
                    </div>
                    <div class="lps-pricing-grid">
                        @foreach ($pricingPlans as $plan)
                            <div class="lps-pricing-card {{ ! empty($plan['highlighted']) ? 'is-highlighted' : '' }}">
                                @if (! empty($plan['highlighted']))
                                    <span class="lps-pricing-badge">Phổ biến nhất</span>
                                @endif
                                <h3>{{ $plan['name'] }}</h3>
                                <div class="lps-pricing-amount">
                                    {{ $plan['price'] }}
                                    @if (! empty($plan['unit']))
                                        <span>{{ $plan['unit'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($plan['features']))
                                    <ul class="lps-pricing-features">
                                        @foreach ($plan['features'] as $line)
                                            <li><x-heroicon-s-check-circle class="h-4 w-4" />{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#lp-venues" class="{{ ! empty($plan['highlighted']) ? 'lps-btn-primary' : 'lps-btn-outline' }}">Đăng ký ngay</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── THÀNH TỰU — dải số liệu ấm (nền tint màu chủ đạo, KHÔNG nền đen như bản Bento) ──── --}}
        <section class="lps-stats">
            <div class="lps-stats-inner lps-reveal">
                <div class="lps-stats-text">
                    <span class="lps-eyebrow">Thành tựu</span>
                    <h2>Đồng hành cùng cộng đồng yêu thể thao</h2>
                    <p>{{ $partner->name }} không ngừng nâng cao chất lượng dịch vụ để mang lại trải nghiệm tốt nhất cho từng khách hàng.</p>
                </div>
                <div class="lps-stats-grid">
                    @foreach ($stats as $stat)
                        <div class="lps-stat">
                            <span class="lps-stat-num">{{ $stat['number'] }}</span>
                            <span class="lps-stat-label">{{ $stat['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── ĐÁNH GIÁ (chỉ hiện khi admin có cấu hình) — split: ảnh trái, trích dẫn phải ──────── --}}
        @if (! empty($testimonials))
            <section class="lps-testimonials" x-data="{ active: 0, total: {{ count($testimonials) }} }">
                <div class="lps-testimonials-inner lps-reveal">
                    <div class="lps-section-head">
                        <span class="lps-eyebrow">Khách hàng nói gì</span>
                        <h2 class="lps-section-title">Đánh giá từ khách hàng</h2>
                    </div>

                    <div class="lps-split lps-testimonial-split">
                        <div class="lps-split-media lps-testimonial-media">
                            @foreach ($testimonials as $i => $t)
                                <div x-show="active === {{ $i }}" x-cloak>
                                    @if (! empty($t['image']))
                                        <img src="{{ $t['image'] }}" alt="{{ $t['name'] }}">
                                    @else
                                        <div class="lps-testimonial-avatar">{{ mb_substr($t['name'], 0, 1) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <div class="lps-split-content">
                            @foreach ($testimonials as $i => $t)
                                <div class="lps-testimonial-quote" x-show="active === {{ $i }}" x-cloak>
                                    <svg viewBox="0 0 24 24" fill="currentColor" class="lps-quote-mark h-8 w-8"><path d="M7.17 6C4.87 6 3 7.87 3 10.17c0 2.3 1.87 4.17 4.17 4.17.4 0 .78-.06 1.15-.16C7.63 16.6 5.9 18 3.5 18v2c4.14 0 7.5-3.36 7.5-7.5V10.17C11 7.87 9.13 6 6.83 6h.34zm10 0c-2.3 0-4.17 1.87-4.17 4.17 0 2.3 1.87 4.17 4.17 4.17.4 0 .78-.06 1.15-.16C17.63 16.6 15.9 18 13.5 18v2c4.14 0 7.5-3.36 7.5-7.5V10.17C21 7.87 19.13 6 16.83 6h.34z"/></svg>
                                    <p>{{ $t['quote'] }}</p>
                                    <div class="lps-testimonial-author">
                                        <strong>{{ $t['name'] }}</strong>
                                        @if (! empty($t['role']))
                                            <span>{{ $t['role'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            @if (count($testimonials) > 1)
                                <div class="lps-testimonial-dots">
                                    @foreach ($testimonials as $i => $t)
                                        <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'is-active' : ''" aria-label="Đánh giá {{ $i + 1 }}"></button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ── DANH SÁCH CHI NHÁNH ───────────────────────────────────────────────── --}}
        <section id="lp-venues" class="lps-venues">
            <div class="lps-venues-inner lps-reveal">
                @if ($venues->isNotEmpty())
                    <div class="lps-section-head">
                        <span class="lps-eyebrow">Chi nhánh</span>
                        <h2 class="lps-section-title">Chi nhánh của {{ $partner->name }}</h2>
                        <p class="lps-section-sub">Chọn 1 chi nhánh để xem lịch trống và đặt sân ngay.</p>
                    </div>

                    <div class="lps-venues-grid">
                        @foreach ($venues as $venue)
                            <x-bladethemev1::venues.card :venue="$venue" />
                        @endforeach
                    </div>
                @else
                    <div class="lps-empty">
                        <x-heroicon-o-map-pin class="h-8 w-8" />
                        <p>{{ $partner->name }} hiện chưa có chi nhánh nào hiển thị.</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- ── TIN TỨC / ƯU ĐÃI (chỉ hiện khi admin có cấu hình) ──────── --}}
        @if (! empty($news))
            <section class="lps-news">
                <div class="lps-news-inner lps-reveal">
                    <div class="lps-section-head">
                        <span class="lps-eyebrow">Cập nhật</span>
                        <h2 class="lps-section-title">Tin tức &amp; ưu đãi mới nhất</h2>
                    </div>
                    <div class="lps-news-grid">
                        @foreach ($news as $item)
                            <a href="{{ $item['url'] ?: '#' }}" @if ($item['url']) target="_blank" rel="noopener" @endif class="lps-news-card">
                                <div class="lps-news-media">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                                    @else
                                        <x-heroicon-o-photo class="h-8 w-8" />
                                    @endif
                                </div>
                                <div class="lps-news-body">
                                    <h3>{{ $item['title'] }}</h3>
                                    @if (! empty($item['excerpt']))
                                        <p>{{ $item['excerpt'] }}</p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── FAQ — split: ảnh trái + accordion phải ────── --}}
        <section class="lps-faq">
            <div class="lps-split lps-faq-split lps-reveal">
                <div class="lps-split-media">
                    @if ($faqImageUrl)
                        <img src="{{ $faqImageUrl }}" alt="Câu hỏi thường gặp" loading="lazy">
                    @else
                        <div class="lps-media-placeholder"><x-heroicon-o-chat-bubble-left-right class="h-10 w-10" /></div>
                    @endif
                </div>
                <div class="lps-split-content">
                    <span class="lps-eyebrow">Hỗ trợ</span>
                    <h2 class="lps-section-title" style="text-align:left;">Các câu hỏi thường gặp</h2>
                    <div class="lps-faq-list">
                        @foreach ($faqs as $i => $faq)
                            <div class="lps-faq-item" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                                <button type="button" class="lps-faq-question" @click="open = !open">
                                    <span>{{ $faq['question'] }}</span>
                                    <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 transition-transform" x-bind:style="open ? 'transform:rotate(180deg)' : ''" />
                                </button>
                                <div class="lps-faq-answer" x-show="open" x-collapse x-cloak>{{ $faq['answer'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ── CTA CUỐI TRANG ─────────────────────────────────────────────────── --}}
        <section class="lps-cta">
            <div class="lps-cta-inner lps-reveal">
                <h2>Sẵn sàng đặt sân ngay hôm nay?</h2>
                <p>Chọn chi nhánh phù hợp và đặt lịch chỉ trong vài phút.</p>
                <a href="#lp-venues" class="lps-btn-primary lps-btn-on-cta">
                    <span>Đặt sân ngay</span>
                    <x-heroicon-o-arrow-right class="h-4 w-4" />
                </a>
            </div>
        </section>

        {{-- ── FOOTER ─────────────────────────────────────────────────────────── --}}
        <footer id="lp-contact" class="lps-footer">
            <div class="lps-footer-inner">
                <div class="lps-footer-grid">
                    <div class="lps-footer-col lps-footer-col-brand">
                        <div class="lps-footer-brand">
                            @if ($partnerLogoUrl)
                                <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lps-brand-logo">
                            @else
                                <span class="lps-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                            @endif
                            <span class="lps-brand-name">{{ $partner->name }}</span>
                        </div>
                        <p>{{ $description ? \Illuminate\Support\Str::limit($description, 140) : ('Đặt sân thể thao trực tuyến tại ' . $partner->name . ' — nhanh chóng, tiện lợi, xác nhận tức thì.') }}</p>
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" class="lps-footer-social" aria-label="Facebook">
                                <x-heroicon-o-globe-alt class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    <div class="lps-footer-col">
                        <h4>Liên kết nhanh</h4>
                        @if ($description)
                            <a href="#lp-about">Giới thiệu</a>
                        @endif
                        <a href="#lp-services">Dịch vụ</a>
                        <a href="#lp-venues">Chi nhánh &amp; đặt sân</a>
                        @if ($pricingPlans->isNotEmpty())
                            <a href="#lp-pricing">Bảng giá</a>
                        @endif
                    </div>

                    <div class="lps-footer-col">
                        <h4>Liên hệ</h4>
                        @if ($partner->address)
                            <span class="lps-footer-item"><x-heroicon-o-map-pin class="h-4 w-4" />{{ $partner->address }}</span>
                        @endif
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lps-footer-item"><x-heroicon-o-phone class="h-4 w-4" />{{ $partner->phone }}</a>
                        @endif
                        @if ($partner->email)
                            <a href="mailto:{{ $partner->email }}" class="lps-footer-item"><x-heroicon-o-envelope class="h-4 w-4" />{{ $partner->email }}</a>
                        @endif
                    </div>
                </div>

                <div class="lps-footer-bottom">
                    © {{ now()->year }} {{ $partner->name }} — Cung cấp bởi {{ config('app.name') }}
                </div>
            </div>
        </footer>
    </div>

    @once
        <script>
            // Bóng đổ header khi cuộn xuống + hiệu ứng "fade lên khi cuộn tới" cho .lps-reveal —
            // thuần vanilla JS/IntersectionObserver, cùng mẫu hình với bản Bento nhưng tên lớp
            // riêng (lps-*) để 2 template độc lập tuyệt đối nếu có lỗi tải nhầm cả 2 style.
            document.addEventListener('DOMContentLoaded', function () {
                var header = document.querySelector('.lps-header');
                if (header) {
                    window.addEventListener('scroll', function () {
                        header.classList.toggle('is-scrolled', window.scrollY > 8);
                    }, { passive: true });
                }

                var revealTargets = document.querySelectorAll('.lps-reveal');
                if (revealTargets.length && 'IntersectionObserver' in window) {
                    var observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('is-visible');
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
                    revealTargets.forEach(function (el) { observer.observe(el); });
                } else {
                    revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
                }
            });
        </script>

        <style>
            .partner-landing-split-scope {
                --color-primary: var(--lp-split-color);
                --lp-split-cream: #fbf6ee;
                --lp-split-cream-deep: #f4ead9;
                --lp-split-ink: #2c2620;
                --lp-split-ink-soft: #6b6259;
                font-family: inherit;
                background: var(--lp-split-cream);
                color: var(--lp-split-ink);
                -webkit-font-smoothing: antialiased;
            }
            .partner-landing-split-scope * { scroll-behavior: smooth; }
            .partner-landing-split-scope a, .partner-landing-split-scope button { transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, opacity .18s ease, border-color .18s ease; }
            .partner-landing-split-scope img { max-width: 100%; display: block; }

            .lps-eyebrow { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .9rem; border-radius: 9999px; background: color-mix(in srgb, var(--lp-split-color) 14%, #fff); color: color-mix(in srgb, var(--lp-split-color) 65%, var(--lp-split-ink)); font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .85rem; }
            .lps-eyebrow::before { content: ''; width: 6px; height: 6px; border-radius: 9999px; background: var(--lp-split-color); }

            .lps-reveal { opacity: 0; transform: translateY(24px); transition: opacity .7s ease, transform .7s ease; }
            .lps-reveal.is-visible { opacity: 1; transform: translateY(0); }
            .no-js .lps-reveal { opacity: 1; transform: none; }

            .lps-section-head { max-width: 42rem; margin: 0 auto 2.5rem; text-align: center; }
            .lps-section-title { font-size: 1.5rem; font-weight: 800; color: var(--lp-split-ink); text-align: center; letter-spacing: -.01em; }
            .lps-section-sub { margin-top: .5rem; font-size: .88rem; color: var(--lp-split-ink-soft); text-align: center; }

            .lps-media-placeholder { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; min-height: 12rem; background: color-mix(in srgb, var(--lp-split-color) 10%, #fff); color: color-mix(in srgb, var(--lp-split-color) 45%, var(--lp-split-ink-soft)); border-radius: inherit; }

            /* Buttons — pill mềm mại, thân thiện */
            .lps-btn-primary { display: inline-flex; align-items: center; gap: .6rem; padding: .85rem 1.75rem; border-radius: 9999px; background: var(--lp-split-color); color: #fff; font-weight: 800; font-size: .88rem; text-decoration: none; box-shadow: 0 10px 24px color-mix(in srgb, var(--lp-split-color) 35%, transparent); border: none; cursor: pointer; }
            .lps-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 30px color-mix(in srgb, var(--lp-split-color) 45%, transparent); }
            .lps-btn-ghost { display: inline-flex; align-items: center; gap: .5rem; padding: .82rem 1.5rem; border-radius: 9999px; background: #fff; color: var(--lp-split-ink); font-weight: 700; font-size: .85rem; text-decoration: none; border: 1.5px solid color-mix(in srgb, var(--lp-split-color) 25%, #e7ddcd); }
            .lps-btn-ghost:hover { border-color: var(--lp-split-color); color: color-mix(in srgb, var(--lp-split-color) 70%, var(--lp-split-ink)); }
            .lps-btn-outline { display: flex; align-items: center; justify-content: center; margin-top: 1.5rem; padding: .75rem 1.5rem; border-radius: 9999px; border: 1.5px solid var(--lp-split-ink); color: var(--lp-split-ink); font-weight: 700; font-size: .84rem; text-decoration: none; }
            .lps-btn-outline:hover { background: var(--lp-split-ink); color: #fff; }
            .lps-link-cta { display: inline-flex; align-items: center; gap: .5rem; margin-top: 1.25rem; font-weight: 800; font-size: .84rem; color: color-mix(in srgb, var(--lp-split-color) 70%, var(--lp-split-ink)); text-decoration: none; }
            .lps-link-cta:hover { gap: .75rem; }

            /* Header */
            .lps-header { position: sticky; top: 0; z-index: 40; background: color-mix(in srgb, var(--lp-split-cream) 92%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid color-mix(in srgb, var(--lp-split-color) 12%, #ecdfc9); transition: box-shadow .2s ease; }
            .lps-header.is-scrolled { box-shadow: 0 4px 20px rgba(60,45,20,.06); }
            .lps-header-inner { max-width: 110rem; margin: 0 auto; padding: .9rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .lps-brand { display: inline-flex; align-items: center; gap: .6rem; text-decoration: none; }
            .lps-brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: .9rem; background: var(--lp-split-color); color: #fff; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; }
            .lps-brand-logo { width: 38px; height: 38px; border-radius: .9rem; object-fit: cover; flex-shrink: 0; }
            .lps-brand-name { font-weight: 800; font-size: 1.02rem; color: var(--lp-split-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; letter-spacing: -.01em; }
            .lps-header-nav { display: flex; align-items: center; gap: 1.5rem; }
            .lps-header-link { display: none; position: relative; font-size: .82rem; font-weight: 700; color: var(--lp-split-ink-soft); text-decoration: none; padding: .25rem 0; }
            .lps-header-link::after { content: ''; position: absolute; left: 0; right: 100%; bottom: 0; height: 2px; background: var(--lp-split-color); transition: right .2s ease; }
            .lps-header-link:hover { color: var(--lp-split-ink); }
            .lps-header-link:hover::after { right: 0; }
            .lps-header-contact { display: none; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 700; color: var(--lp-split-ink); text-decoration: none; }
            .lps-header-cta { display: inline-flex; align-items: center; padding: .55rem 1.3rem; border-radius: 9999px; background: var(--lp-split-color); color: #fff; font-size: .82rem; font-weight: 800; text-decoration: none; white-space: nowrap; box-shadow: 0 6px 16px color-mix(in srgb, var(--lp-split-color) 30%, transparent); }
            .lps-header-cta:hover { transform: translateY(-1px); }
            @media (min-width: 640px) { .lps-header-contact { display: inline-flex; } }
            @media (min-width: 900px) { .lps-header-link { display: inline-flex; } }

            /* Hero — split ấm áp: chữ trái, cụm ảnh lồng nhau phải */
            .lps-hero { padding: 2.5rem 1rem 3rem; }
            .lps-hero-inner { max-width: 110rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 2.5rem; align-items: center; }
            .lps-hero-title { margin: 0; font-size: 2rem; font-weight: 800; line-height: 1.2; letter-spacing: -.02em; color: var(--lp-split-ink); }
            .lps-hero-desc { margin: 1.1rem 0 0; font-size: .95rem; line-height: 1.75; color: var(--lp-split-ink-soft); max-width: 34rem; }
            .lps-hero-actions { margin-top: 1.75rem; display: flex; flex-wrap: wrap; gap: .9rem; }
            .lps-hero-rating { margin-top: 1.75rem; display: flex; align-items: center; gap: .6rem; font-size: .82rem; font-weight: 600; color: var(--lp-split-ink-soft); }
            .lps-hero-stars { display: flex; gap: .15rem; color: var(--lp-split-color); }
            .lps-hero-stars svg.is-dim { color: #e2d8c4; }

            .lps-hero-media { position: relative; }
            .lps-hero-media-main { position: relative; aspect-ratio: 4 / 3.2; border-radius: 2rem; overflow: hidden; background: color-mix(in srgb, var(--lp-split-color) 12%, #fff); box-shadow: 0 24px 50px -18px rgba(60,45,20,.25); }
            .lps-hero-media-main img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lps-hero-media-main iframe.lps-hero-video-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; z-index: 2; }
            .lps-hero-scrim { position: absolute; inset: 0; background: linear-gradient(to top, rgba(20,15,5,.35) 0%, transparent 50%); pointer-events: none; }
            .lps-play-btn { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 62px; height: 62px; border-radius: 9999px; background: #fff; border: none; cursor: pointer; color: var(--lp-split-color); display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 10px 26px rgba(20,15,5,.28); }
            .lps-play-btn:hover { transform: translate(-50%, -50%) scale(1.06); }

            .lps-hero-media-sub { position: absolute; left: -1rem; bottom: -1.5rem; width: 38%; aspect-ratio: 1 / 1; border-radius: 1.5rem; overflow: hidden; border: 6px solid var(--lp-split-cream); box-shadow: 0 16px 32px rgba(60,45,20,.2); background: color-mix(in srgb, var(--lp-split-color) 10%, #fff); }
            .lps-hero-media-sub img { width: 100%; height: 100%; object-fit: cover; }

            .lps-hero-stat-chip { position: absolute; right: -.5rem; top: -1.25rem; display: flex; gap: 1.25rem; padding: 1rem 1.4rem; border-radius: 1.25rem; background: #fff; box-shadow: 0 14px 30px rgba(60,45,20,.14); }
            .lps-hero-stat-item { display: flex; flex-direction: column; }
            .lps-hero-stat-item strong { font-size: 1.25rem; font-weight: 800; color: var(--lp-split-color); }
            .lps-hero-stat-item span { font-size: .68rem; color: var(--lp-split-ink-soft); white-space: nowrap; }

            @media (min-width: 900px) {
                .lps-hero-inner { grid-template-columns: 1fr 1fr; gap: 3.5rem; }
                .lps-hero-title { font-size: 2.6rem; }
            }

            /* Features */
            .lps-features { padding: 4rem 1rem; }
            .lps-features-inner { max-width: 96rem; margin: 0 auto; }
            .lps-features-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lps-feature { flex: 1 1 240px; max-width: 270px; background: #fff; border: 1px solid color-mix(in srgb, var(--lp-split-color) 8%, #ecdfc9); border-radius: 1.5rem; padding: 1.85rem 1.6rem; text-align: center; transition: transform .25s ease, box-shadow .25s ease; }
            .lps-feature:hover { transform: translateY(-5px); box-shadow: 0 18px 34px rgba(60,45,20,.08); }
            .lps-feature-icon { display: inline-flex; align-items: center; justify-content: center; width: 54px; height: 54px; border-radius: 1.1rem; background: color-mix(in srgb, var(--lp-split-color) 14%, #fff); color: var(--lp-split-color); }
            .lps-feature h3 { margin-top: 1rem; font-size: .95rem; font-weight: 800; color: var(--lp-split-ink); }
            .lps-feature p { margin-top: .5rem; font-size: .82rem; color: var(--lp-split-ink-soft); line-height: 1.6; }

            /* About */
            .lps-about { padding: 1rem 1rem 4rem; }
            .lps-about-inner { max-width: 62rem; margin: 0 auto; background: #fff; border-radius: 2rem; padding: 2.5rem; box-shadow: 0 18px 40px -12px rgba(60,45,20,.1); }
            .lps-about-text { margin-top: 1rem; font-size: .92rem; line-height: 1.8; color: var(--lp-split-ink-soft); white-space: pre-line; }

            /* Split — pattern chung cho Dịch vụ / FAQ / Testimonials */
            .lps-split { display: grid; grid-template-columns: 1fr; gap: 2rem; align-items: center; }
            .lps-split-media { position: relative; aspect-ratio: 4 / 3; border-radius: 1.75rem; overflow: hidden; background: color-mix(in srgb, var(--lp-split-color) 10%, #fff); box-shadow: 0 20px 40px -16px rgba(60,45,20,.18); }
            .lps-split-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lps-split-content { padding: .25rem; }
            .lps-split-index { display: inline-block; font-size: .78rem; font-weight: 800; letter-spacing: .08em; color: var(--lp-split-color); margin-bottom: .5rem; }
            .lps-split-content h3 { font-size: 1.3rem; font-weight: 800; color: var(--lp-split-ink); letter-spacing: -.01em; }
            .lps-split-content p { margin-top: .75rem; font-size: .9rem; line-height: 1.75; color: var(--lp-split-ink-soft); }
            @media (min-width: 900px) {
                .lps-split { grid-template-columns: 1fr 1fr; gap: 3.5rem; }
                .lps-split.is-reverse .lps-split-media { order: 2; }
                .lps-split.is-reverse .lps-split-content { order: 1; }
            }

            /* Services */
            .lps-services { padding: 1rem 1rem 4.5rem; }
            .lps-services-inner { max-width: 76rem; margin: 0 auto; }
            .lps-services-list { display: flex; flex-direction: column; gap: 3.5rem; }

            /* Pricing */
            .lps-pricing { padding: 4.5rem 1rem; background: #fff; }
            .lps-pricing-inner { max-width: 96rem; margin: 0 auto; }
            .lps-pricing-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lps-pricing-card { position: relative; flex: 1 1 300px; max-width: 340px; background: var(--lp-split-cream); border: 1px solid color-mix(in srgb, var(--lp-split-color) 10%, #ecdfc9); border-radius: 1.75rem; padding: 2.25rem 1.85rem; transition: transform .25s ease, box-shadow .25s ease; }
            .lps-pricing-card:not(.is-highlighted):hover { transform: translateY(-4px); box-shadow: 0 18px 34px rgba(60,45,20,.08); }
            .lps-pricing-card.is-highlighted { background: #fff; border-color: var(--lp-split-color); box-shadow: 0 20px 40px color-mix(in srgb, var(--lp-split-color) 20%, transparent); transform: translateY(-6px); }
            .lps-pricing-badge { position: absolute; top: -13px; left: 50%; transform: translateX(-50%); padding: .3rem .9rem; border-radius: 9999px; background: var(--lp-split-color); color: #fff; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
            .lps-pricing-card h3 { font-size: 1rem; font-weight: 800; color: var(--lp-split-ink); }
            .lps-pricing-amount { margin-top: .5rem; font-size: 1.7rem; font-weight: 800; color: var(--lp-split-color); }
            .lps-pricing-amount span { font-size: .78rem; font-weight: 600; color: var(--lp-split-ink-soft); }
            .lps-pricing-features { margin-top: 1.35rem; display: flex; flex-direction: column; gap: .6rem; }
            .lps-pricing-features li { display: flex; align-items: flex-start; gap: .5rem; font-size: .8rem; color: var(--lp-split-ink-soft); }
            .lps-pricing-features li svg { color: var(--lp-split-color); margin-top: .1rem; flex-shrink: 0; }
            .lps-pricing-card .lps-btn-primary, .lps-pricing-card .lps-btn-outline { display: flex; width: 100%; justify-content: center; margin-top: 1.6rem; }

            /* Stats — dải tint ấm, không nền đen */
            .lps-stats { position: relative; overflow: hidden; padding: 4rem 1rem; background: linear-gradient(135deg, color-mix(in srgb, var(--lp-split-color) 12%, var(--lp-split-cream-deep)) 0%, var(--lp-split-cream-deep) 100%); }
            .lps-stats-inner { max-width: 96rem; margin: 0 auto; display: flex; flex-direction: column; gap: 2.25rem; }
            .lps-stats-text h2 { margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--lp-split-ink); }
            .lps-stats-text p { margin-top: .6rem; font-size: .85rem; color: var(--lp-split-ink-soft); max-width: 36rem; }
            .lps-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
            .lps-stat { background: #fff; border-radius: 1.25rem; padding: 1.25rem 1.1rem; text-align: center; }
            .lps-stat-num { display: block; font-size: 1.8rem; font-weight: 800; color: var(--lp-split-color); }
            .lps-stat-label { display: block; margin-top: .3rem; font-size: .76rem; color: var(--lp-split-ink-soft); }
            @media (min-width: 768px) { .lps-stats-inner { flex-direction: row; align-items: center; justify-content: space-between; } .lps-stats-grid { grid-template-columns: repeat(4, 1fr); flex: 1; margin-left: 2rem; } }

            /* Testimonials */
            .lps-testimonials { padding: 4.5rem 1rem; }
            .lps-testimonials-inner { max-width: 76rem; margin: 0 auto; }
            .lps-testimonial-split .lps-split-media { aspect-ratio: 1 / 1; max-width: 26rem; }
            .lps-testimonial-avatar { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--lp-split-color); color: #fff; font-size: 3rem; font-weight: 800; }
            .lps-quote-mark { color: color-mix(in srgb, var(--lp-split-color) 45%, #ecdfc9); }
            .lps-testimonial-quote p { margin-top: 1rem; font-size: 1.1rem; line-height: 1.7; color: var(--lp-split-ink); font-style: italic; }
            .lps-testimonial-author { margin-top: 1.5rem; display: flex; flex-direction: column; }
            .lps-testimonial-author strong { font-size: .92rem; color: var(--lp-split-ink); }
            .lps-testimonial-author span { font-size: .78rem; color: var(--lp-split-ink-soft); }
            .lps-testimonial-dots { margin-top: 1.75rem; display: flex; align-items: center; gap: .5rem; }
            .lps-testimonial-dots button { width: 8px; height: 8px; border-radius: 9999px; background: #e2d8c4; border: none; padding: 0; cursor: pointer; }
            .lps-testimonial-dots button.is-active { background: var(--lp-split-color); width: 22px; }

            /* Venues */
            .lps-venues { padding: 1rem 1rem 4.5rem; }
            .lps-venues-inner { max-width: 110rem; margin: 0 auto; }
            .lps-venues-grid { margin-top: 1.75rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; }
            .lps-venues-grid > * { flex: 1 1 220px; max-width: 280px; }
            .lps-empty { display: flex; flex-direction: column; align-items: center; gap: .75rem; padding: 4rem 0; text-align: center; font-size: .9rem; color: var(--lp-split-ink-soft); }
            .lps-empty svg { color: color-mix(in srgb, var(--lp-split-color) 40%, var(--lp-split-ink-soft)); }

            /* News */
            .lps-news { padding: 4.5rem 1rem; background: #fff; }
            .lps-news-inner { max-width: 96rem; margin: 0 auto; }
            .lps-news-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.35rem; }
            .lps-news-card { flex: 1 1 240px; max-width: 300px; display: block; background: var(--lp-split-cream); border-radius: 1.5rem; overflow: hidden; text-decoration: none; border: 1px solid color-mix(in srgb, var(--lp-split-color) 8%, #ecdfc9); transition: transform .25s ease, box-shadow .25s ease; }
            .lps-news-card:hover { transform: translateY(-5px); box-shadow: 0 18px 34px rgba(60,45,20,.1); }
            .lps-news-media { height: 160px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: color-mix(in srgb, var(--lp-split-color) 10%, #fff); color: color-mix(in srgb, var(--lp-split-color) 40%, var(--lp-split-ink-soft)); }
            .lps-news-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lps-news-card:hover .lps-news-media img { transform: scale(1.06); }
            .lps-news-body { padding: 1.35rem 1.5rem; }
            .lps-news-body h3 { font-size: .9rem; font-weight: 800; color: var(--lp-split-ink); line-height: 1.4; }
            .lps-news-body p { margin-top: .45rem; font-size: .8rem; color: var(--lp-split-ink-soft); line-height: 1.55; }

            /* FAQ */
            .lps-faq { padding: 4.5rem 1rem; }
            .lps-faq-split .lps-split-media { aspect-ratio: 4 / 3.4; }
            .lps-faq-list { margin-top: 1.75rem; display: flex; flex-direction: column; gap: .75rem; }
            .lps-faq-item { background: #fff; border: 1px solid color-mix(in srgb, var(--lp-split-color) 10%, #ecdfc9); border-radius: 1.25rem; overflow: hidden; }
            .lps-faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.4rem; background: none; border: none; text-align: left; font-size: .88rem; font-weight: 700; color: var(--lp-split-ink); cursor: pointer; }
            .lps-faq-question svg { color: var(--lp-split-color); }
            .lps-faq-answer { padding: 0 1.4rem 1.25rem; font-size: .84rem; color: var(--lp-split-ink-soft); line-height: 1.65; }

            /* CTA */
            .lps-cta { padding: 4.5rem 1rem; text-align: center; }
            .lps-cta-inner { max-width: 42rem; margin: 0 auto; background: linear-gradient(135deg, var(--lp-split-color) 0%, var(--lp-split-color-heavy) 100%); border-radius: 2rem; padding: 3.25rem 2rem; box-shadow: 0 24px 50px -18px color-mix(in srgb, var(--lp-split-color) 45%, transparent); }
            .lps-cta h2 { margin: 0; font-size: 1.6rem; font-weight: 800; color: #fff; letter-spacing: -.01em; }
            .lps-cta p { margin-top: .65rem; font-size: .9rem; color: rgba(255,255,255,.9); }
            .lps-btn-on-cta { margin-top: 1.85rem; background: #fff; color: var(--lp-split-color-heavy); box-shadow: 0 10px 24px rgba(0,0,0,.15); }
            .lps-btn-on-cta:hover { box-shadow: 0 14px 30px rgba(0,0,0,.22); }

            /* Footer */
            .lps-footer { background: var(--lp-split-ink); color: #d9d2c7; padding: 3.5rem 1rem 1.5rem; }
            .lps-footer-inner { max-width: 110rem; margin: 0 auto; }
            .lps-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; text-align: center; }
            .lps-footer-col { display: flex; flex-direction: column; align-items: center; gap: .65rem; }
            .lps-footer-col h4 { font-size: .82rem; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .35rem; }
            .lps-footer-col a { font-size: .82rem; color: #d9d2c7; text-decoration: none; }
            .lps-footer-col a:hover { color: var(--lp-split-color); }
            .lps-footer-col-brand { max-width: 24rem; margin: 0 auto; }
            .lps-footer-brand { display: flex; align-items: center; gap: .6rem; }
            .lps-footer-brand .lps-brand-name { color: #fff; }
            .lps-footer-brand .lps-brand-badge { background: var(--lp-split-color); }
            .lps-footer-col-brand p { font-size: .82rem; color: #b9b0a2; line-height: 1.6; }
            .lps-footer-social { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 9999px; background: rgba(255,255,255,.08); color: #d9d2c7; }
            .lps-footer-social:hover { background: var(--lp-split-color); color: #fff; }
            .lps-footer-item { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; color: #d9d2c7; text-decoration: none; }
            .lps-footer-bottom { margin-top: 2.5rem; text-align: center; font-size: .74rem; color: #918a7d; border-top: 1px solid rgba(255,255,255,.1); padding-top: 1.25rem; }
            @media (min-width: 768px) { .lps-footer-grid { grid-template-columns: 1.4fr 1fr 1fr; text-align: left; } .lps-footer-col { align-items: flex-start; } .lps-footer-col-brand { margin: 0; } }

            /* Mobile safety: cụm ảnh hero lồng góc cần margin-top để thẻ mini/ảnh phụ không đè lên
               nội dung phía trên khi màn hình rất hẹp (~360px). */
            @media (max-width: 640px) {
                .lps-hero-media { margin-top: 2rem; }
                .lps-hero-stat-chip { position: static; margin-top: 2.25rem; display: inline-flex; }
                .lps-hero-media-sub { width: 34%; left: -.5rem; bottom: -1rem; }
            }
        </style>
    @endonce
@endsection
