@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Trang landing page "Classic" — 1 trong 4 mẫu giao diện landing page dành cho đối tác theo
         subdomain (song song với partner-landing.blade.php "bento", partner-landing-editorial.blade.php
         "brutalist" và partner-landing-minimal.blade.php "minimal") — chọn theo cấu hình
         landing_template của đối tác (BladeThemeV1Controller::partnerLandingPage()).

         Định hướng thị giác: "câu lạc bộ thể thao cao cấp / khách sạn 5 sao" — KHÔNG phải phong
         cách startup công nghệ trẻ trung của bản "bento". Nền ngà (ivory)/than chì đậm (charcoal),
         tiêu đề dùng font chữ serif trình diễn ("Cormorant Garamond") ghép với sans-serif cho phần
         nội dung (kế thừa 'Inter' đã nạp sẵn ở layout gốc — xem layouts/master.blade.php dòng 56),
         màu nhấn của đối tác ($landingColor) CHỈ dùng tinh tế (đường kẻ mảnh kiểu chỉ vàng, icon nhỏ)
         chứ không phải khối màu lớn — đối xứng, khoảng trắng rộng rãi, tiêu đề canh giữa kèm đường
         phân cách trang trí nhỏ bên dưới, các đường kẻ ngang mảnh ("ornamental rule") ngăn cách giữa
         các section để tạo cảm giác trang trọng/đáng tin cậy đã được thiết lập lâu năm. --}}
    <div class="partner-landing-classic-scope" style="
        --lpc-color: {{ $landingColor }};
        --lpc-color-heavy: {{ $landingColorHeavy }};
        --lpc-color-light: {{ $landingColorLight }};
    ">
        {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
        <header class="lpc-header">
            <div class="lpc-header-inner">
                <a href="#" class="lpc-brand">
                    @if ($partnerLogoUrl)
                        <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpc-brand-logo">
                    @else
                        <span class="lpc-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                    @endif
                    <span class="lpc-brand-name">{{ $partner->name }}</span>
                </a>

                <nav class="lpc-header-nav">
                    @if ($description)
                        <a href="#lp-about" class="lpc-header-link">Giới thiệu</a>
                    @endif
                    <a href="#lp-services" class="lpc-header-link">Dịch vụ</a>
                    @if ($pricingPlans->isNotEmpty())
                        <a href="#lp-pricing" class="lpc-header-link">Bảng giá</a>
                    @endif
                    <a href="#lp-venues" class="lpc-header-link">Chi nhánh</a>
                    <a href="#lp-contact" class="lpc-header-link">Liên hệ</a>
                    @if ($partner->phone)
                        <a href="tel:{{ $partner->phone }}" class="lpc-header-contact">
                            <x-heroicon-o-phone class="h-4 w-4" />
                            <span>{{ $partner->phone }}</span>
                        </a>
                    @endif
                    <a href="#lp-venues" class="lpc-header-cta">Đặt sân ngay</a>
                </nav>
            </div>
        </header>

        {{-- ── HERO ────────────────────────────────────────────────────────────
             Bố cục cân xứng, canh giữa, kiểu "trang bìa tạp chí câu lạc bộ": tiêu đề serif lớn +
             đường phân cách trang trí nhỏ + CTA, phía dưới là DẢI 2 ảnh (heroImageUrl +
             heroSecondaryImageUrl) đặt cạnh nhau lệch tầng — dùng ĐỦ cả 2 ảnh được cấp thay vì bỏ
             bớt 1 ảnh. Nếu có cấu hình video giới thiệu thì overlay nút Play xuất hiện trên ảnh
             chính (đúng pattern lite-embed của bản gốc: chỉ tạo iframe SAU khi bấm). --}}
        <section class="lpc-hero">
            <div class="lpc-hero-inner">
                @if ($avgRating)
                    <div class="lpc-hero-rating">
                        <span class="lpc-stars">
                            @for ($i = 1; $i <= 5; $i++)
                                <x-heroicon-s-star class="h-3.5 w-3.5 {{ $i <= round($avgRating) ? '' : 'is-dim' }}" />
                            @endfor
                        </span>
                        <span>{{ number_format($avgRating, 1) }} / 5 — Đánh giá từ khách hàng</span>
                    </div>
                @else
                    <div class="lpc-hero-rating lpc-hero-rating-empty">Chưa có đánh giá</div>
                @endif

                <h1 class="lpc-hero-title">{{ $tagline }}</h1>
                <div class="lpc-divider" aria-hidden="true"><span></span><svg viewBox="0 0 24 24" fill="currentColor" class="lpc-divider-icon"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z" /></svg><span></span></div>
                <p class="lpc-hero-desc">{{ $description ?: ('Trải nghiệm đặt sân trực tuyến đẳng cấp tại ' . $partner->name . ' — chọn chi nhánh, xem lịch trống theo thời gian thực và xác nhận chỉ trong vài bước.') }}</p>

                <div class="lpc-hero-actions">
                    <a href="#lp-venues" class="lpc-btn-primary">Đặt sân ngay</a>
                    @if ($description)
                        <a href="#lp-about" class="lpc-btn-outline">Tìm hiểu thêm</a>
                    @endif
                </div>
            </div>

            <div class="lpc-hero-gallery" @if ($heroYoutubeId) x-data="{ playing: false }" @endif>
                <div class="lpc-hero-media lpc-hero-media-main">
                    @if ($heroImageUrl)
                        <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="lazy" @if ($heroYoutubeId) x-show="!playing" @endif>
                    @elseif ($heroYoutubeId)
                        <img src="https://i3.ytimg.com/vi/{{ $heroYoutubeId }}/hqdefault.jpg" alt="{{ $partner->name }}" x-show="!playing">
                    @else
                        <div class="lpc-hero-media-placeholder"><x-heroicon-o-photo class="h-10 w-10" /></div>
                    @endif

                    @if ($heroYoutubeId)
                        <div class="lpc-hero-scrim" x-show="!playing"></div>
                        <button type="button" class="lpc-hero-play-btn" x-show="!playing" @click="playing = true" aria-label="Xem video giới thiệu">
                            <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M8 5v14l11-7z" /></svg>
                        </button>
                        <template x-if="playing">
                            <iframe
                                class="lpc-hero-video-frame"
                                src="https://www.youtube-nocookie.com/embed/{{ $heroYoutubeId }}?autoplay=1&rel=0"
                                title="Video giới thiệu {{ $partner->name }}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </template>
                    @endif
                </div>

                <div class="lpc-hero-media lpc-hero-media-secondary">
                    @if ($heroSecondaryImageUrl)
                        <img src="{{ $heroSecondaryImageUrl }}" alt="{{ $partner->name }}" loading="lazy">
                    @else
                        <div class="lpc-hero-media-placeholder"><x-heroicon-o-photo class="h-8 w-8" /></div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ── ĐIỂM NỔI BẬT ───────────────────────────────────────────────────── --}}
        <section class="lpc-features">
            <div class="lpc-features-inner lpc-reveal">
                <div class="lpc-section-head">
                    <span class="lpc-eyebrow">Vì sao chọn chúng tôi</span>
                    <h2 class="lpc-section-title">Điều làm nên khác biệt tại {{ $partner->name }}</h2>
                    <div class="lpc-rule"></div>
                </div>
                <div class="lpc-features-grid">
                    @foreach ($features as $feature)
                        <div class="lpc-feature">
                            <span class="lpc-feature-icon">
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
            <section id="lp-about" class="lpc-about">
                <div class="lpc-about-inner lpc-reveal">
                    <span class="lpc-eyebrow">Câu chuyện của chúng tôi</span>
                    <h2 class="lpc-section-title">Giới thiệu về {{ $partner->name }}</h2>
                    <div class="lpc-rule"></div>
                    <p class="lpc-about-text">{{ $description }}</p>
                </div>
            </section>
        @endif

        {{-- ── DỊCH VỤ ────────────────────────────────────────────────────────── --}}
        <section id="lp-services" class="lpc-services">
            <div class="lpc-services-inner lpc-reveal">
                <div class="lpc-section-head">
                    <span class="lpc-eyebrow">Dịch vụ</span>
                    <h2 class="lpc-section-title">Những trải nghiệm chúng tôi mang lại</h2>
                    <div class="lpc-rule"></div>
                </div>
                <div class="lpc-services-grid">
                    @foreach ($services as $service)
                        <div class="lpc-service-card">
                            <div class="lpc-service-media">
                                @if (! empty($service['image']))
                                    <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy">
                                @else
                                    <x-heroicon-o-photo class="h-8 w-8" />
                                @endif
                            </div>
                            <div class="lpc-service-body">
                                <h3>{{ $service['title'] }}</h3>
                                @if (! empty($service['description']))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="lpc-section-cta">
                    <a href="#lp-venues" class="lpc-btn-outline">Đặt sân ngay</a>
                </div>
            </div>
        </section>

        {{-- ── BẢNG GIÁ (chỉ hiện khi admin có cấu hình landing_pricing_plans) ──── --}}
        @if ($pricingPlans->isNotEmpty())
            <section id="lp-pricing" class="lpc-pricing">
                <div class="lpc-pricing-inner lpc-reveal">
                    <div class="lpc-section-head">
                        <span class="lpc-eyebrow">Bảng giá</span>
                        <h2 class="lpc-section-title">Chọn gói thành viên phù hợp</h2>
                        <div class="lpc-rule"></div>
                    </div>
                    <div class="lpc-pricing-grid">
                        @foreach ($pricingPlans as $plan)
                            <div class="lpc-pricing-card {{ ! empty($plan['highlighted']) ? 'is-highlighted' : '' }}">
                                @if (! empty($plan['highlighted']))
                                    <span class="lpc-pricing-badge">Được ưa chuộng</span>
                                @endif
                                <h3>{{ $plan['name'] }}</h3>
                                <div class="lpc-pricing-amount">
                                    {{ $plan['price'] }}
                                    @if (! empty($plan['unit']))
                                        <span>{{ $plan['unit'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($plan['features']))
                                    <ul class="lpc-pricing-features">
                                        @foreach ($plan['features'] as $line)
                                            <li><x-heroicon-s-check-circle class="h-4 w-4" />{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#lp-venues" class="{{ ! empty($plan['highlighted']) ? 'lpc-btn-primary' : 'lpc-btn-outline' }}">Đăng ký ngay</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── THÀNH TỰU (nền than chì đậm) ──────────────────────────────────── --}}
        <section class="lpc-stats">
            <div class="lpc-stats-inner">
                <span class="lpc-eyebrow lpc-eyebrow-dark">Thành tựu</span>
                <h2 class="lpc-section-title lpc-section-title-dark">Được tin tưởng qua từng năm tháng</h2>
                <div class="lpc-rule lpc-rule-dark"></div>
                <div class="lpc-stats-grid">
                    @foreach ($stats as $stat)
                        <div class="lpc-stat">
                            <span class="lpc-stat-num">{{ $stat['number'] }}</span>
                            <span class="lpc-stat-label">{{ $stat['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── ĐÁNH GIÁ (chỉ hiện khi admin có cấu hình) ─────────────────────────── --}}
        @if (! empty($testimonials))
            <section class="lpc-testimonials" x-data="{ active: 0, total: {{ count($testimonials) }} }">
                <div class="lpc-testimonials-inner">
                    <span class="lpc-eyebrow">Khách hàng nói gì</span>
                    <h2 class="lpc-section-title">Lời chứng thực từ hội viên</h2>
                    <div class="lpc-rule"></div>

                    @foreach ($testimonials as $i => $t)
                        <div class="lpc-testimonial" x-show="active === {{ $i }}" x-cloak>
                            <svg viewBox="0 0 24 24" fill="currentColor" class="lpc-quote-mark"><path d="M9.5 6C6 6 3.5 8.7 3.5 12.4c0 3 1.9 5.3 4.6 5.3.4 0 .8-.1 1.1-.2-.4 1.6-1.7 3-3.4 3.5l.6 1.7c3-1 5.4-3.7 5.4-7.6 0-4.9-2.7-9.1-2.3-9.1zm10 0c-3.5 0-6 2.7-6 6.4 0 3 1.9 5.3 4.6 5.3.4 0 .8-.1 1.1-.2-.4 1.6-1.7 3-3.4 3.5l.6 1.7c3-1 5.4-3.7 5.4-7.6 0-4.9-2.7-9.1-2.3-9.1z" /></svg>
                            <p class="lpc-testimonial-quote">&ldquo;{{ $t['quote'] }}&rdquo;</p>
                            <div class="lpc-testimonial-author">
                                @if (! empty($t['image']))
                                    <img src="{{ $t['image'] }}" alt="{{ $t['name'] }}">
                                @else
                                    <span class="lpc-testimonial-avatar">{{ mb_substr($t['name'], 0, 1) }}</span>
                                @endif
                                <span class="lpc-testimonial-name-wrap">
                                    <strong>{{ $t['name'] }}</strong>
                                    @if (! empty($t['role']))
                                        <span>{{ $t['role'] }}</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endforeach

                    @if (count($testimonials) > 1)
                        <div class="lpc-testimonial-dots">
                            @foreach ($testimonials as $i => $t)
                                <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'is-active' : ''" aria-label="Đánh giá {{ $i + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- ── DANH SÁCH CHI NHÁNH ───────────────────────────────────────────────── --}}
        <section id="lp-venues" class="lpc-venues">
            <div class="lpc-venues-inner">
                @if ($venues->isNotEmpty())
                    <div class="lpc-section-head">
                        <span class="lpc-eyebrow">Chi nhánh</span>
                        <h2 class="lpc-section-title">Chi nhánh của {{ $partner->name }}</h2>
                        <div class="lpc-rule"></div>
                        <p class="lpc-section-sub">Chọn một chi nhánh để xem lịch trống và đặt sân ngay.</p>
                    </div>

                    <div class="lpc-venues-grid">
                        @foreach ($venues as $venue)
                            <x-bladethemev1::venues.card :venue="$venue" />
                        @endforeach
                    </div>
                @else
                    <p class="lpc-empty">{{ $partner->name }} hiện chưa có chi nhánh nào hiển thị.</p>
                @endif
            </div>
        </section>

        {{-- ── TIN TỨC / ƯU ĐÃI (chỉ hiện khi admin có cấu hình) ─────────────────── --}}
        @if (! empty($news))
            <section class="lpc-news">
                <div class="lpc-news-inner">
                    <div class="lpc-section-head">
                        <span class="lpc-eyebrow">Cập nhật</span>
                        <h2 class="lpc-section-title">Tin tức &amp; ưu đãi mới nhất</h2>
                        <div class="lpc-rule"></div>
                    </div>
                    <div class="lpc-news-grid">
                        @foreach ($news as $item)
                            <a href="{{ $item['url'] ?: '#' }}" @if ($item['url']) target="_blank" rel="noopener" @endif class="lpc-news-card">
                                <div class="lpc-news-media">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                                    @else
                                        <x-heroicon-o-photo class="h-8 w-8" />
                                    @endif
                                </div>
                                <div class="lpc-news-body">
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

        {{-- ── FAQ ──────────────────────────────────────────────────────────────── --}}
        <section class="lpc-faq">
            <div class="lpc-faq-inner">
                <div class="lpc-faq-content">
                    <span class="lpc-eyebrow">Hỗ trợ</span>
                    <h2 class="lpc-section-title" style="text-align:left;">Các câu hỏi thường gặp</h2>
                    <div class="lpc-rule lpc-rule-left"></div>
                    <div class="lpc-faq-list">
                        @foreach ($faqs as $i => $faq)
                            <div class="lpc-faq-item" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                                <button type="button" class="lpc-faq-question" @click="open = !open">
                                    <span>{{ $faq['question'] }}</span>
                                    <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 transition-transform" x-bind:style="open ? 'transform:rotate(180deg)' : ''" />
                                </button>
                                <div class="lpc-faq-answer" x-show="open" x-collapse x-cloak>{{ $faq['answer'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="lpc-faq-media">
                    @if ($faqImageUrl)
                        <img src="{{ $faqImageUrl }}" alt="Câu hỏi thường gặp" loading="lazy">
                    @else
                        <div class="lpc-hero-media-placeholder"><x-heroicon-o-chat-bubble-left-right class="h-12 w-12" /></div>
                    @endif
                    <div class="lpc-faq-media-frame" aria-hidden="true"></div>
                </div>
            </div>
        </section>

        {{-- ── CTA CUỐI TRANG ─────────────────────────────────────────────────── --}}
        <section class="lpc-cta">
            <div class="lpc-cta-inner">
                <span class="lpc-eyebrow lpc-eyebrow-dark">Đặt lịch</span>
                <h2>Sẵn sàng trải nghiệm đẳng cấp ngay hôm nay?</h2>
                <div class="lpc-rule lpc-rule-dark"></div>
                <p>Chọn chi nhánh phù hợp và đặt lịch chỉ trong vài phút.</p>
                <a href="#lp-venues" class="lpc-btn-primary lpc-btn-on-dark">Đặt sân ngay</a>
            </div>
        </section>

        {{-- ── FOOTER ─────────────────────────────────────────────────────────── --}}
        <footer id="lp-contact" class="lpc-footer">
            <div class="lpc-footer-inner">
                <div class="lpc-footer-grid">
                    <div class="lpc-footer-col lpc-footer-col-brand">
                        <div class="lpc-footer-brand">
                            @if ($partnerLogoUrl)
                                <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpc-brand-logo">
                            @else
                                <span class="lpc-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                            @endif
                            <span class="lpc-brand-name">{{ $partner->name }}</span>
                        </div>
                        <p>{{ $description ? \Illuminate\Support\Str::limit($description, 140) : ('Đặt sân thể thao trực tuyến tại ' . $partner->name . ' — tinh tế, tiện lợi, xác nhận tức thì.') }}</p>
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" class="lpc-footer-social" aria-label="Facebook">
                                <x-heroicon-o-globe-alt class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    <div class="lpc-footer-col">
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

                    <div class="lpc-footer-col">
                        <h4>Liên hệ</h4>
                        @if ($partner->address)
                            <span class="lpc-footer-item"><x-heroicon-o-map-pin class="h-4 w-4" />{{ $partner->address }}</span>
                        @endif
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lpc-footer-item"><x-heroicon-o-phone class="h-4 w-4" />{{ $partner->phone }}</a>
                        @endif
                        @if ($partner->email)
                            <a href="mailto:{{ $partner->email }}" class="lpc-footer-item"><x-heroicon-o-envelope class="h-4 w-4" />{{ $partner->email }}</a>
                        @endif
                    </div>
                </div>

                <div class="lpc-footer-bottom">
                    © {{ now()->year }} {{ $partner->name }} — Cung cấp bởi {{ config('app.name') }}
                </div>
            </div>
        </footer>
    </div>

    @once
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        {{-- Font serif trình diễn riêng cho bản "Classic" (khác 'Inter' đã nạp sẵn ở layout gốc) —
             Cormorant Garamond: nét mảnh, thanh lịch, đúng tinh thần "câu lạc bộ cao cấp" thay vì
             một font grotesque hiện đại. Chỉ nạp trọng số cần dùng (500/600/700 + italic 500) để
             nhẹ băng thông. --}}
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">

        <script>
            // Bóng đổ header khi cuộn xuống + hiệu ứng "fade lên khi cuộn tới" cho .lpc-reveal —
            // thuần vanilla JS/IntersectionObserver, cùng pattern với bản "bento" gốc nhưng scope
            // riêng theo class .lpc-* để không đụng bản kia nếu cả 2 từng render chung 1 trang test.
            document.addEventListener('DOMContentLoaded', function () {
                var header = document.querySelector('.lpc-header');
                if (header) {
                    window.addEventListener('scroll', function () {
                        header.classList.toggle('is-scrolled', window.scrollY > 8);
                    }, { passive: true });
                }

                var revealTargets = document.querySelectorAll('.lpc-reveal');
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
            /* ── Nền tảng / bảng màu ─────────────────────────────────────────────
               Ivory ấm (#faf7f0) + than chì đậm gần đen-xanh (#1c2128) thay vì đen thuần — cảm giác
               "khách sạn/câu lạc bộ" ấm áp hơn thay vì lạnh lẽo kiểu tech. Màu chủ đạo của đối tác
               CHỈ dùng cho các chi tiết nhỏ (đường kẻ, icon, số liệu) qua color-mix, không tô nền lớn. */
            .partner-landing-classic-scope {
                --lpc-ivory: #faf7f0;
                --lpc-ivory-deep: #f2ede1;
                --lpc-charcoal: #1c2128;
                --lpc-charcoal-soft: #262c35;
                --lpc-ink: #2b2b26;
                --lpc-muted: #6b6558;
                --color-primary: var(--lpc-color);
                background: var(--lpc-ivory);
                color: var(--lpc-ink);
                font-family: 'Inter', sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            .partner-landing-classic-scope * { scroll-behavior: smooth; box-sizing: border-box; }
            .partner-landing-classic-scope a, .partner-landing-classic-scope button { transition: transform .2s ease, box-shadow .2s ease, background-color .2s ease, opacity .2s ease, border-color .2s ease; }
            .partner-landing-classic-scope img { max-width: 100%; display: block; }
            .partner-landing-classic-scope h1, .partner-landing-classic-scope h2, .partner-landing-classic-scope h3 { font-family: 'Cormorant Garamond', 'Playfair Display', serif; font-weight: 600; letter-spacing: .01em; margin: 0; }

            .lpc-reveal { opacity: 0; transform: translateY(20px); transition: opacity .7s ease, transform .7s ease; }
            .lpc-reveal.is-visible { opacity: 1; transform: translateY(0); }
            .no-js .lpc-reveal { opacity: 1; transform: none; }

            /* Eyebrow + tiêu đề section + đường phân cách trang trí — nhắc lại ở nhiều section nên
               tách class dùng chung, canh giữa mặc định (FAQ override lệch trái riêng). */
            .lpc-section-head { text-align: center; max-width: 44rem; margin: 0 auto 2.5rem; }
            .lpc-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .18em; color: color-mix(in srgb, var(--lpc-color-heavy) 75%, var(--lpc-ink)); margin-bottom: .85rem; }
            .lpc-eyebrow::before, .lpc-eyebrow::after { content: ''; width: 18px; height: 1px; background: var(--lpc-color); }
            .lpc-eyebrow-dark { color: var(--lpc-color); }
            .lpc-eyebrow-dark::before, .lpc-eyebrow-dark::after { background: color-mix(in srgb, var(--lpc-color) 70%, transparent); }
            .lpc-section-title { font-size: 1.7rem; line-height: 1.25; text-align: center; color: var(--lpc-ink); }
            .lpc-section-title-dark { color: var(--lpc-ivory); }
            .lpc-section-sub { margin-top: 1.1rem; font-size: .88rem; color: var(--lpc-muted); text-align: center; }

            /* Đường kẻ trang trí nhỏ dưới tiêu đề — 2 nét mảnh + 1 hoa văn kim cương/sao ở giữa,
               dùng lại ở hero + mọi section-head, kiểu "ornamental rule" của thiệp mời/menu nhà hàng
               cao cấp thay vì gạch ngang trơn. */
            .lpc-rule { position: relative; height: 1px; margin: 1.1rem auto 0; width: 5rem; background: color-mix(in srgb, var(--lpc-color) 55%, #d8d0bd); }
            .lpc-rule-dark { background: color-mix(in srgb, var(--lpc-color) 55%, rgba(255,255,255,.3)); }
            .lpc-rule-left { margin-left: 0; }

            /* Header */
            .lpc-header { position: sticky; top: 0; z-index: 40; background: color-mix(in srgb, var(--lpc-ivory) 94%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid #e7e0cf; transition: box-shadow .2s ease; }
            .lpc-header.is-scrolled { box-shadow: 0 6px 24px rgba(28,33,40,.06); }
            .lpc-header-inner { max-width: 90rem; margin: 0 auto; padding: 1rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .lpc-brand { display: inline-flex; align-items: center; gap: .65rem; text-decoration: none; }
            .lpc-brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 9999px; border: 1px solid color-mix(in srgb, var(--lpc-color) 55%, #d8d0bd); background: var(--lpc-ivory-deep); color: color-mix(in srgb, var(--lpc-color-heavy) 80%, var(--lpc-ink)); font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 1.15rem; flex-shrink: 0; }
            .lpc-brand-logo { width: 38px; height: 38px; border-radius: 9999px; object-fit: cover; flex-shrink: 0; border: 1px solid #e7e0cf; }
            .lpc-brand-name { font-family: 'Cormorant Garamond', serif; font-weight: 700; font-size: 1.25rem; color: var(--lpc-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; letter-spacing: .01em; }
            .lpc-header-nav { display: flex; align-items: center; gap: 1.5rem; }
            .lpc-header-link { display: none; font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--lpc-muted); text-decoration: none; }
            .lpc-header-link:hover { color: var(--lpc-ink); }
            .lpc-header-contact { display: none; align-items: center; gap: .4rem; font-size: .82rem; font-weight: 600; color: var(--lpc-ink); text-decoration: none; }
            .lpc-header-cta { display: inline-flex; align-items: center; padding: .55rem 1.35rem; border: 1px solid var(--lpc-charcoal); border-radius: 9999px; background: var(--lpc-charcoal); color: var(--lpc-ivory); font-size: .78rem; font-weight: 700; letter-spacing: .03em; text-decoration: none; white-space: nowrap; }
            .lpc-header-cta:hover { background: var(--lpc-ivory); color: var(--lpc-charcoal); }
            @media (min-width: 640px) { .lpc-header-contact { display: inline-flex; } }
            @media (min-width: 1024px) { .lpc-header-link { display: inline-flex; } }

            /* Buttons */
            .lpc-btn-primary { display: inline-flex; align-items: center; justify-content: center; padding: .85rem 2rem; border-radius: 9999px; border: 1px solid var(--lpc-charcoal); background: var(--lpc-charcoal); color: var(--lpc-ivory); font-weight: 700; font-size: .82rem; letter-spacing: .03em; text-decoration: none; }
            .lpc-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(28,33,40,.22); }
            .lpc-btn-primary.lpc-btn-on-dark { border-color: var(--lpc-ivory); background: var(--lpc-ivory); color: var(--lpc-charcoal); }
            .lpc-btn-primary.lpc-btn-on-dark:hover { background: transparent; color: var(--lpc-ivory); }
            .lpc-btn-outline { display: inline-flex; align-items: center; justify-content: center; padding: .8rem 1.85rem; border-radius: 9999px; border: 1px solid color-mix(in srgb, var(--lpc-ink) 35%, transparent); color: var(--lpc-ink); font-weight: 700; font-size: .82rem; letter-spacing: .03em; text-decoration: none; }
            .lpc-btn-outline:hover { border-color: var(--lpc-ink); background: color-mix(in srgb, var(--lpc-ink) 5%, transparent); }

            /* Hero */
            .lpc-hero { padding: 3.5rem 1.25rem 0; max-width: 62rem; margin: 0 auto; text-align: center; }
            .lpc-hero-rating { display: inline-flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 600; color: var(--lpc-muted); margin-bottom: 1.25rem; }
            .lpc-hero-rating-empty { color: var(--lpc-muted); font-style: italic; }
            .lpc-stars { display: inline-flex; gap: .1rem; color: var(--lpc-color-heavy); }
            .lpc-stars svg.is-dim { color: #d8d0bd; }
            .lpc-hero-title { font-size: 2.15rem; line-height: 1.2; color: var(--lpc-ink); }
            .lpc-divider { display: flex; align-items: center; justify-content: center; gap: .75rem; width: 12rem; margin: 1.35rem auto 0; color: var(--lpc-color-heavy); }
            .lpc-divider span { flex: 1; height: 1px; background: color-mix(in srgb, var(--lpc-color) 55%, #d8d0bd); }
            .lpc-divider-icon { width: 14px; height: 14px; flex-shrink: 0; }
            .lpc-hero-desc { margin: 1.5rem auto 0; max-width: 38rem; font-size: .95rem; line-height: 1.8; color: var(--lpc-muted); }
            .lpc-hero-actions { margin-top: 2rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 1rem; }

            .lpc-hero-gallery { margin: 3rem auto 0; max-width: 78rem; padding: 0 1.25rem 4rem; display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
            .lpc-hero-media { position: relative; overflow: hidden; border-radius: .5rem; background: var(--lpc-charcoal-soft); border: 1px solid #e7e0cf; }
            .lpc-hero-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpc-hero-media-main { aspect-ratio: 16 / 10; }
            .lpc-hero-media-secondary { aspect-ratio: 16 / 10; }
            .lpc-hero-media-placeholder { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(250,247,240,.35); }
            .lpc-hero-scrim { position: absolute; inset: 0; background: linear-gradient(to top, rgba(20,20,18,.55) 0%, transparent 45%); }
            .lpc-hero-play-btn { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; border-radius: 9999px; border: 1px solid rgba(250,247,240,.7); background: rgba(28,33,40,.55); backdrop-filter: blur(2px); color: var(--lpc-ivory); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
            .lpc-hero-play-btn:hover { background: var(--lpc-color); border-color: var(--lpc-color); color: var(--lpc-charcoal); transform: translate(-50%, -50%) scale(1.06); }
            .lpc-hero-video-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
            @media (min-width: 768px) {
                .lpc-hero-gallery { grid-template-columns: 1.4fr 1fr; align-items: stretch; }
                .lpc-hero-media-main, .lpc-hero-media-secondary { aspect-ratio: auto; min-height: 22rem; }
                .lpc-hero-media-secondary { margin-top: 3rem; }
            }
            @media (min-width: 1024px) { .lpc-hero-title { font-size: 2.75rem; } }

            /* Features */
            .lpc-features { padding: 4.5rem 1.25rem; background: var(--lpc-ivory-deep); border-top: 1px solid #e7e0cf; border-bottom: 1px solid #e7e0cf; }
            .lpc-features-inner { max-width: 78rem; margin: 0 auto; }
            .lpc-features-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; }
            .lpc-feature { flex: 1 1 230px; max-width: 260px; text-align: center; padding: 2rem 1.35rem; background: var(--lpc-ivory); border: 1px solid #e7e0cf; }
            .lpc-feature:hover { border-color: color-mix(in srgb, var(--lpc-color) 45%, #e7e0cf); box-shadow: 0 14px 30px rgba(28,33,40,.06); }
            .lpc-feature-icon { display: inline-flex; align-items: center; justify-content: center; width: 46px; height: 46px; border-radius: 9999px; border: 1px solid color-mix(in srgb, var(--lpc-color) 50%, #e7e0cf); color: color-mix(in srgb, var(--lpc-color-heavy) 75%, var(--lpc-ink)); }
            .lpc-feature h3 { margin-top: 1.1rem; font-size: 1.05rem; }
            .lpc-feature p { margin-top: .55rem; font-size: .82rem; color: var(--lpc-muted); line-height: 1.7; }

            /* About */
            .lpc-about { padding: 4.5rem 1.25rem; }
            .lpc-about-inner { max-width: 48rem; margin: 0 auto; text-align: center; }
            .lpc-about-text { margin-top: 1.5rem; font-size: .92rem; line-height: 1.85; color: var(--lpc-muted); white-space: pre-line; }

            /* Services */
            .lpc-services { padding: 4.5rem 1.25rem; }
            .lpc-services-inner { max-width: 78rem; margin: 0 auto; }
            .lpc-services-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lpc-service-card { flex: 1 1 280px; max-width: 320px; background: var(--lpc-ivory); border: 1px solid #e7e0cf; }
            .lpc-service-card:hover { box-shadow: 0 16px 34px rgba(28,33,40,.08); }
            .lpc-service-media { height: 160px; background: var(--lpc-charcoal-soft); display: flex; align-items: center; justify-content: center; overflow: hidden; color: rgba(250,247,240,.35); }
            .lpc-service-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpc-service-body { padding: 1.5rem 1.5rem 1.75rem; text-align: center; }
            .lpc-service-body h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.15rem; font-weight: 700; color: var(--lpc-ink); }
            .lpc-service-body p { margin-top: .5rem; font-size: .82rem; color: var(--lpc-muted); line-height: 1.65; }
            .lpc-section-cta { margin-top: 2.75rem; text-align: center; }

            /* Pricing */
            .lpc-pricing { padding: 4.5rem 1.25rem; background: var(--lpc-ivory-deep); border-top: 1px solid #e7e0cf; border-bottom: 1px solid #e7e0cf; }
            .lpc-pricing-inner { max-width: 78rem; margin: 0 auto; }
            .lpc-pricing-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lpc-pricing-card { position: relative; flex: 1 1 280px; max-width: 320px; padding: 2.5rem 2rem; text-align: center; background: var(--lpc-ivory); border: 1px solid #e7e0cf; }
            .lpc-pricing-card.is-highlighted { border-color: var(--lpc-color); box-shadow: 0 18px 40px color-mix(in srgb, var(--lpc-color) 18%, transparent); }
            .lpc-pricing-badge { display: inline-block; margin-bottom: 1rem; padding: .3rem .9rem; border: 1px solid var(--lpc-color); border-radius: 9999px; font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: color-mix(in srgb, var(--lpc-color-heavy) 75%, var(--lpc-ink)); }
            .lpc-pricing-card h3 { font-size: 1.2rem; }
            .lpc-pricing-amount { margin-top: .75rem; font-family: 'Cormorant Garamond', serif; font-size: 2.1rem; font-weight: 700; color: var(--lpc-ink); }
            .lpc-pricing-amount span { font-family: 'Inter', sans-serif; font-size: .75rem; font-weight: 600; color: var(--lpc-muted); }
            .lpc-pricing-features { margin-top: 1.5rem; display: flex; flex-direction: column; gap: .6rem; text-align: left; }
            .lpc-pricing-features li { display: flex; align-items: flex-start; gap: .55rem; font-size: .82rem; color: var(--lpc-muted); }
            .lpc-pricing-features li svg { color: color-mix(in srgb, var(--lpc-color-heavy) 75%, var(--lpc-ink)); margin-top: .1rem; flex-shrink: 0; }
            .lpc-pricing-card .lpc-btn-primary, .lpc-pricing-card .lpc-btn-outline { display: flex; width: 100%; margin-top: 1.75rem; }

            /* Stats (charcoal) */
            .lpc-stats { padding: 4.5rem 1.25rem; background: var(--lpc-charcoal); text-align: center; }
            .lpc-stats-inner { max-width: 60rem; margin: 0 auto; }
            .lpc-stats-grid { margin-top: 3rem; display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; }
            .lpc-stat-num { display: block; font-family: 'Cormorant Garamond', serif; font-size: 2.1rem; font-weight: 700; color: var(--lpc-color); }
            .lpc-stat-label { display: block; margin-top: .4rem; font-size: .75rem; letter-spacing: .04em; text-transform: uppercase; color: rgba(250,247,240,.6); }
            @media (min-width: 640px) { .lpc-stats-grid { grid-template-columns: repeat(4, 1fr); } }

            /* Testimonials */
            .lpc-testimonials { padding: 4.5rem 1.25rem; }
            .lpc-testimonials-inner { max-width: 46rem; margin: 0 auto; text-align: center; }
            .lpc-quote-mark { width: 32px; height: 32px; margin: 1.75rem auto 0; color: color-mix(in srgb, var(--lpc-color) 60%, #d8d0bd); }
            .lpc-testimonial-quote { margin-top: 1rem; font-family: 'Cormorant Garamond', serif; font-style: italic; font-size: 1.3rem; line-height: 1.65; color: var(--lpc-ink); }
            .lpc-testimonial-author { margin-top: 1.75rem; display: flex; align-items: center; justify-content: center; gap: .75rem; }
            .lpc-testimonial-author img { width: 48px; height: 48px; border-radius: 9999px; object-fit: cover; }
            .lpc-testimonial-avatar { width: 48px; height: 48px; border-radius: 9999px; background: var(--lpc-charcoal); color: var(--lpc-ivory); display: inline-flex; align-items: center; justify-content: center; font-family: 'Cormorant Garamond', serif; font-weight: 700; }
            .lpc-testimonial-name-wrap { display: flex; flex-direction: column; align-items: flex-start; text-align: left; }
            .lpc-testimonial-name-wrap strong { font-size: .88rem; color: var(--lpc-ink); }
            .lpc-testimonial-name-wrap span { font-size: .75rem; color: var(--lpc-muted); }
            .lpc-testimonial-dots { margin-top: 2rem; display: flex; align-items: center; justify-content: center; gap: .5rem; }
            .lpc-testimonial-dots button { width: 7px; height: 7px; border-radius: 9999px; background: #d8d0bd; border: none; padding: 0; cursor: pointer; }
            .lpc-testimonial-dots button.is-active { background: var(--lpc-color-heavy); width: 20px; }

            /* Venues */
            .lpc-venues { padding: 4.5rem 1.25rem 5rem; background: var(--lpc-ivory-deep); border-top: 1px solid #e7e0cf; }
            .lpc-venues-inner { max-width: 90rem; margin: 0 auto; }
            .lpc-venues-grid { margin-top: 2.5rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; }
            .lpc-venues-grid > * { flex: 1 1 240px; max-width: 300px; }
            .lpc-empty { padding: 4rem 0; text-align: center; font-size: .9rem; color: var(--lpc-muted); }

            /* News */
            .lpc-news { padding: 4.5rem 1.25rem; }
            .lpc-news-inner { max-width: 78rem; margin: 0 auto; }
            .lpc-news-grid { margin-top: 2.5rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; }
            .lpc-news-card { flex: 1 1 240px; max-width: 290px; display: block; background: var(--lpc-ivory); border: 1px solid #e7e0cf; text-decoration: none; }
            .lpc-news-card:hover { border-color: color-mix(in srgb, var(--lpc-color) 45%, #e7e0cf); box-shadow: 0 14px 30px rgba(28,33,40,.08); }
            .lpc-news-media { height: 150px; background: var(--lpc-charcoal-soft); display: flex; align-items: center; justify-content: center; overflow: hidden; color: rgba(250,247,240,.35); }
            .lpc-news-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpc-news-body { padding: 1.35rem 1.5rem; }
            .lpc-news-body h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.1rem; font-weight: 700; color: var(--lpc-ink); line-height: 1.4; }
            .lpc-news-body p { margin-top: .5rem; font-size: .8rem; color: var(--lpc-muted); line-height: 1.6; }

            /* FAQ */
            .lpc-faq { padding: 4.5rem 1.25rem; background: var(--lpc-ivory-deep); border-top: 1px solid #e7e0cf; border-bottom: 1px solid #e7e0cf; }
            .lpc-faq-inner { max-width: 78rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 2.5rem; align-items: start; }
            .lpc-faq-content .lpc-section-title { text-align: left; }
            .lpc-faq-list { margin-top: 2.25rem; display: flex; flex-direction: column; gap: .75rem; }
            .lpc-faq-item { background: var(--lpc-ivory); border: 1px solid #e7e0cf; }
            .lpc-faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.4rem; background: none; border: none; text-align: left; font-size: .88rem; font-weight: 600; color: var(--lpc-ink); cursor: pointer; }
            .lpc-faq-question svg { color: color-mix(in srgb, var(--lpc-color-heavy) 75%, var(--lpc-ink)); }
            .lpc-faq-answer { padding: 0 1.4rem 1.25rem; font-size: .84rem; color: var(--lpc-muted); line-height: 1.7; }
            .lpc-faq-media { position: relative; min-height: 18rem; overflow: hidden; border: 1px solid #e7e0cf; background: var(--lpc-charcoal-soft); display: flex; align-items: center; justify-content: center; }
            .lpc-faq-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lpc-faq-media-frame { position: absolute; inset: 10px; border: 1px solid rgba(250,247,240,.35); pointer-events: none; }
            @media (min-width: 900px) { .lpc-faq-inner { grid-template-columns: 1.15fr .85fr; } .lpc-faq-media { min-height: 100%; } }

            /* CTA */
            .lpc-cta { padding: 5rem 1.25rem; background: var(--lpc-charcoal); text-align: center; }
            .lpc-cta-inner { max-width: 38rem; margin: 0 auto; }
            .lpc-cta h2 { margin-top: .5rem; font-size: 1.8rem; color: var(--lpc-ivory); }
            .lpc-cta p { margin-top: 1.35rem; font-size: .9rem; color: rgba(250,247,240,.72); }
            .lpc-cta .lpc-btn-primary { margin-top: 2rem; }

            /* Footer */
            .lpc-footer { background: var(--lpc-charcoal); color: rgba(250,247,240,.7); padding: 4rem 1.25rem 1.75rem; border-top: 1px solid rgba(250,247,240,.1); }
            .lpc-footer-inner { max-width: 90rem; margin: 0 auto; }
            .lpc-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; text-align: center; }
            .lpc-footer-col { display: flex; flex-direction: column; align-items: center; gap: .7rem; }
            .lpc-footer-col h4 { font-family: 'Cormorant Garamond', serif; font-size: 1rem; font-weight: 700; color: var(--lpc-ivory); text-transform: uppercase; letter-spacing: .06em; margin-bottom: .4rem; }
            .lpc-footer-col a { font-size: .82rem; color: rgba(250,247,240,.65); text-decoration: none; }
            .lpc-footer-col a:hover { color: var(--lpc-color); }
            .lpc-footer-col-brand { max-width: 24rem; margin: 0 auto; }
            .lpc-footer-brand { display: flex; align-items: center; gap: .65rem; }
            .lpc-footer-brand .lpc-brand-name { color: var(--lpc-ivory); }
            .lpc-footer-col-brand p { font-size: .82rem; color: rgba(250,247,240,.6); line-height: 1.7; }
            .lpc-footer-social { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 9999px; border: 1px solid rgba(250,247,240,.25); color: rgba(250,247,240,.8); }
            .lpc-footer-social:hover { background: var(--lpc-color); border-color: var(--lpc-color); color: var(--lpc-charcoal); }
            .lpc-footer-item { display: inline-flex; align-items: center; gap: .45rem; font-size: .82rem; color: rgba(250,247,240,.65); text-decoration: none; }
            .lpc-footer-bottom { margin-top: 3rem; text-align: center; font-size: .74rem; color: rgba(250,247,240,.4); border-top: 1px solid rgba(250,247,240,.1); padding-top: 1.5rem; }
            @media (min-width: 768px) { .lpc-footer-grid { grid-template-columns: 1.4fr 1fr 1fr; text-align: left; } .lpc-footer-col { align-items: flex-start; } .lpc-footer-col-brand { margin: 0; } }
        </style>
    @endonce
@endsection
