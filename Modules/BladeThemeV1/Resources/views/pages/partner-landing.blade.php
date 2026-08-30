@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Trang landing page RIÊNG cho 1 đối tác theo subdomain (vd mamble.365sport-cms.test) — CHỈ
         1 giao diện duy nhất dùng chung cho MỌI đối tác có tính năng 'landing_page' VÀ đang bật
         landing_page_enabled (BladeThemeV1Controller::partnerLandingPage()). Bố cục tham khảo mẫu
         thiết kế người dùng cung cấp (kiểu "Mona Pickleball": header → hero → điểm nổi bật → dịch
         vụ → bảng giá → thành tựu (dark) → đánh giá (dark) → chi nhánh/đặt sân → tin tức (dark) →
         FAQ → CTA → footer) — MỌI khối (trừ header/hero/chi nhánh/CTA/footer) đều là nội dung LINH
         HOẠT do admin tự cấu hình qua Repeater ở tab "Trang landing" (PartnerForm), rỗng thì dùng
         bộ mặc định (BladeThemeV1Controller::defaultLandingSections()) hoặc tự ẩn nếu là nội dung
         mang tính "bằng chứng xã hội" (đánh giá/tin tức/bảng giá — không có bộ mặc định giả).

         Nút "Đặt lịch" trên từng thẻ chi nhánh trỏ THẲNG về domain chính (route('branch.booking'),
         xem components/venues/card.blade.php) — đặt sân vẫn xử lý ở domain gốc.

         Màu chủ đạo ($landingColor/...Heavy/...Light) admin tự chọn riêng cho từng đối tác (tab
         "Trang landing" của PartnerResource) — scope bằng CSS custom property cục bộ trong
         .partner-landing-scope, KHÔNG đổi --color-primary toàn site. --}}
    <div class="partner-landing-scope" style="
        --lp-color: {{ $landingColor }};
        --lp-color-heavy: {{ $landingColorHeavy }};
        --lp-color-light: {{ $landingColorLight }};
    ">
        {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
        <header class="lp-header">
            <div class="lp-header-inner">
                <a href="#" class="lp-brand">
                    @if ($partnerLogoUrl)
                        <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lp-brand-logo">
                    @else
                        <span class="lp-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                    @endif
                    <span class="lp-brand-name">{{ $partner->name }}</span>
                </a>

                <nav class="lp-header-nav">
                    @if ($description)
                        <a href="#lp-about" class="lp-header-link">Giới thiệu</a>
                    @endif
                    <a href="#lp-services" class="lp-header-link">Dịch vụ</a>
                    @if ($pricingPlans->isNotEmpty())
                        <a href="#lp-pricing" class="lp-header-link">Bảng giá</a>
                    @endif
                    <a href="#lp-contact" class="lp-header-link">Liên hệ</a>
                    @if ($partner->phone)
                        <a href="tel:{{ $partner->phone }}" class="lp-header-contact">
                            <x-heroicon-o-phone class="h-4 w-4" />
                            <span>{{ $partner->phone }}</span>
                        </a>
                    @endif
                    <a href="#lp-venues" class="lp-header-cta">Đặt sân ngay</a>
                </nav>
            </div>
        </header>

        {{-- ── HERO (bố cục bento 3 ô, tham khảo mẫu Mona Pickleball) ────────────
             Ô trái: chữ giới thiệu + CTA (nền đen). Ô giữa: ảnh ngang (trên) + 2 thẻ đánh giá/số
             liệu (dưới, dùng DỮ LIỆU THẬT — rating trung bình từ chi nhánh + 2 mục đầu của
             landing_stats, không phải số trang trí giả). Ô phải: ảnh dọc + nút Play video (nếu có
             cấu hình link YouTube) + chú thích. Thiếu ảnh nào thì hiện khung trống thay thế, không
             vỡ layout. --}}
        <section class="lp-hero">
            <div class="lp-bento">
                <div class="lp-bento-left">
                    <h1 class="lp-hero-title">{{ $tagline }}</h1>
                    <p class="lp-hero-desc">{{ $description ?: ('Đặt sân trực tuyến tại ' . $partner->name . ' — chọn chi nhánh, xem lịch trống theo thời gian thực và đặt lịch chỉ trong vài bước.') }}</p>
                    <a href="#lp-venues" class="lp-bento-cta">
                        <span>Đặt sân ngay</span>
                        <span class="lp-bento-cta-icon"><x-heroicon-o-arrow-right class="h-4 w-4" /></span>
                    </a>
                </div>

                <div class="lp-bento-middle">
                    <div class="lp-bento-media">
                        @if ($heroSecondaryImageUrl)
                            <img src="{{ $heroSecondaryImageUrl }}" alt="{{ $partner->name }}" loading="lazy">
                        @else
                            <x-heroicon-o-photo class="h-10 w-10 text-white/30" />
                        @endif
                    </div>
                    <div class="lp-bento-cards">
                        <div class="lp-bento-card lp-bento-card-rating">
                            <div class="lp-bento-avatar-stack">
                                <span class="lp-bento-avatar"><x-heroicon-s-user class="h-4 w-4" /></span>
                                <span class="lp-bento-avatar"><x-heroicon-s-user class="h-4 w-4" /></span>
                                <span class="lp-bento-avatar"><x-heroicon-s-user class="h-4 w-4" /></span>
                                @if (! empty($stats[2]['number']))
                                    <span class="lp-bento-avatar lp-bento-avatar-count">{{ $stats[2]['number'] }}</span>
                                @endif
                            </div>
                            @if ($avgRating)
                                <div class="lp-bento-stars">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <x-heroicon-s-star class="h-4 w-4 {{ $i <= round($avgRating) ? '' : 'is-dim' }}" />
                                    @endfor
                                </div>
                                <span class="lp-bento-rating-num">Đánh giá {{ number_format($avgRating, 1) }} / 5</span>
                            @else
                                <div class="lp-bento-stars">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <x-heroicon-s-star class="h-4 w-4 is-dim" />
                                    @endfor
                                </div>
                                <span class="lp-bento-rating-num">Chưa có đánh giá</span>
                            @endif
                        </div>
                        <div class="lp-bento-card lp-bento-card-dark">
                            @forelse (array_slice($stats, 0, 2) as $stat)
                                <div class="lp-bento-stat-row">
                                    <strong>{{ $stat['number'] }}</strong>
                                    <span>{{ $stat['label'] }}</span>
                                </div>
                            @empty
                                <div class="lp-bento-stat-row"><strong>{{ $venues->count() }}</strong><span>Chi nhánh</span></div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="lp-bento-right" @if ($heroYoutubeId) x-data="{ playing: false }" @endif>
                    @if ($heroImageUrl)
                        <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="lazy" @if ($heroYoutubeId) x-show="!playing" @endif>
                    @elseif ($heroYoutubeId)
                        <img src="https://i3.ytimg.com/vi/{{ $heroYoutubeId }}/hqdefault.jpg" alt="{{ $partner->name }}" x-show="!playing">
                    @else
                        <div class="lp-bento-media" style="height:100%;"><x-heroicon-o-photo class="h-10 w-10 text-white/30" /></div>
                    @endif

                    @if ($heroYoutubeId)
                        <div class="lp-bento-scrim" x-show="!playing"></div>
                        <div class="lp-bento-video-overlay" x-show="!playing">
                            {{-- Nhúng TRỰC TIẾP bằng iframe ngay khi bấm Play (không mở tab mới) —
                                 chỉ tạo iframe SAU khi bấm (autoplay=1), đúng pattern "lite youtube
                                 embed" chuẩn, không tự tải sẵn video nặng lúc mới vào trang. --}}
                            <button type="button" class="lp-bento-play-btn" @click="playing = true" aria-label="Xem video giới thiệu">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M8 5v14l11-7z" /></svg>
                            </button>
                            <p>Xem video giới thiệu và đặt sân nhanh chóng tại {{ $partner->name }}!</p>
                        </div>
                        <template x-if="playing">
                            <iframe
                                class="lp-bento-video-frame"
                                src="https://www.youtube-nocookie.com/embed/{{ $heroYoutubeId }}?autoplay=1&rel=0"
                                title="Video giới thiệu {{ $partner->name }}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </template>
                    @endif
                </div>
            </div>
        </section>

        {{-- ── ĐIỂM NỔI BẬT ───────────────────────────────────────────────────── --}}
        <section class="lp-features">
            <div class="lp-features-inner lp-reveal">
                <div style="text-align:center;"><span class="lp-eyebrow">Vì sao chọn chúng tôi</span></div>
                <h2 class="lp-section-title">Điều thú vị về {{ $partner->name }}</h2>
                <div class="lp-features-grid">
                    @foreach ($features as $feature)
                        <div class="lp-feature">
                            <span class="lp-feature-icon">
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
            <section id="lp-about" class="lp-about">
                <div class="lp-about-inner lp-reveal">
                    <span class="lp-eyebrow">Câu chuyện của chúng tôi</span>
                    <h2 class="lp-section-title">Giới thiệu về {{ $partner->name }}</h2>
                    <p class="lp-about-text">{{ $description }}</p>
                </div>
            </section>
        @endif

        {{-- ── DỊCH VỤ ────────────────────────────────────────────────────────── --}}
        <section id="lp-services" class="lp-services">
            <div class="lp-services-inner lp-reveal">
                <span class="lp-eyebrow">Dịch vụ</span>
                <h2 class="lp-section-title">Cung cấp dịch vụ thuê sân tốt nhất</h2>
                <div class="lp-services-grid">
                    @foreach ($services as $service)
                        <div class="lp-service-card">
                            <div class="lp-service-media">
                                @if (! empty($service['image']))
                                    <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy">
                                @else
                                    <x-heroicon-o-photo class="h-8 w-8 text-gray-300" />
                                @endif
                            </div>
                            <div class="lp-service-body">
                                <h3>{{ $service['title'] }}</h3>
                                @if (! empty($service['description']))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="lp-section-cta">
                    <a href="#lp-venues" class="lp-btn-dark">Đặt sân ngay</a>
                </div>
            </div>
        </section>

        {{-- ── BẢNG GIÁ (chỉ hiện khi admin có cấu hình landing_pricing_plans) ──── --}}
        @if ($pricingPlans->isNotEmpty())
            <section id="lp-pricing" class="lp-pricing">
                <div class="lp-pricing-inner lp-reveal">
                    <span class="lp-eyebrow">Bảng giá</span>
                    <h2 class="lp-section-title">Chọn gói phù hợp với bạn</h2>
                    <div class="lp-pricing-grid">
                        @foreach ($pricingPlans as $plan)
                            <div class="lp-pricing-card {{ ! empty($plan['highlighted']) ? 'is-highlighted' : '' }}">
                                <h3>{{ $plan['name'] }}</h3>
                                <div class="lp-pricing-amount">
                                    {{ $plan['price'] }}
                                    @if (! empty($plan['unit']))
                                        <span>{{ $plan['unit'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($plan['features']))
                                    <ul class="lp-pricing-features">
                                        @foreach ($plan['features'] as $line)
                                            <li><x-heroicon-s-check-circle class="h-4 w-4" />{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#lp-venues" class="lp-btn-{{ ! empty($plan['highlighted']) ? 'primary' : 'outline-dark' }}">Đăng ký ngay</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── THÀNH TỰU (dark) ──────────────────────────────────────────────── --}}
        <section class="lp-stats">
            <div class="lp-stats-inner">
                <div class="lp-stats-text">
                    <span class="lp-eyebrow lp-eyebrow-dark">Thành tựu</span>
                    <h2>Điều thú vị về {{ $partner->name }}</h2>
                    <p>Đồng hành cùng hàng nghìn khách hàng yêu thể thao, {{ $partner->name }} không ngừng nâng cao chất lượng dịch vụ.</p>
                </div>
                <div class="lp-stats-grid">
                    @foreach ($stats as $stat)
                        <div class="lp-stat">
                            <span class="lp-stat-num">{{ $stat['number'] }}</span>
                            <span class="lp-stat-label">{{ $stat['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── ĐÁNH GIÁ (dark, chỉ hiện khi admin có cấu hình) ──────────────────── --}}
        @if (! empty($testimonials))
            <section class="lp-testimonials" x-data="{ active: 0, total: {{ count($testimonials) }} }">
                <div class="lp-testimonials-inner">
                    <span class="lp-eyebrow lp-eyebrow-dark">Khách hàng nói gì</span>
                    <h2 class="lp-section-title lp-section-title-dark">Đánh giá từ khách hàng</h2>

                    @foreach ($testimonials as $i => $t)
                        <div class="lp-testimonial" x-show="active === {{ $i }}" x-cloak>
                            <div class="lp-testimonial-media">
                                @if (! empty($t['image']))
                                    <img src="{{ $t['image'] }}" alt="{{ $t['name'] }}">
                                @else
                                    <div class="lp-testimonial-avatar">{{ mb_substr($t['name'], 0, 1) }}</div>
                                @endif
                            </div>
                            <div class="lp-testimonial-card">
                                <p>"{{ $t['quote'] }}"</p>
                                <div class="lp-testimonial-author">
                                    <strong>{{ $t['name'] }}</strong>
                                    @if (! empty($t['role']))
                                        <span>{{ $t['role'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if (count($testimonials) > 1)
                        <div class="lp-testimonial-dots">
                            @foreach ($testimonials as $i => $t)
                                <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'is-active' : ''" aria-label="Đánh giá {{ $i + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- ── DANH SÁCH CHI NHÁNH ───────────────────────────────────────────────── --}}
        <section id="lp-venues" class="lp-venues">
            <div class="lp-venues-inner">
                @if ($venues->isNotEmpty())
                    <div style="text-align:center;"><span class="lp-eyebrow">Chi nhánh</span></div>
                    <h2 class="lp-section-title">Chi nhánh của {{ $partner->name }}</h2>
                    <p class="lp-section-sub">Chọn 1 chi nhánh để xem lịch trống và đặt sân ngay.</p>

                    <div class="lp-venues-grid">
                        @foreach ($venues as $venue)
                            <x-bladethemev1::venues.card :venue="$venue" />
                        @endforeach
                    </div>
                @else
                    <p class="lp-empty">{{ $partner->name }} hiện chưa có chi nhánh nào hiển thị.</p>
                @endif
            </div>
        </section>

        {{-- ── TIN TỨC / ƯU ĐÃI (dark, chỉ hiện khi admin có cấu hình) ──────────── --}}
        @if (! empty($news))
            <section class="lp-news">
                <div class="lp-news-inner">
                    <span class="lp-eyebrow lp-eyebrow-dark">Cập nhật</span>
                    <h2 class="lp-section-title lp-section-title-dark">Tin tức &amp; ưu đãi mới nhất</h2>
                    <div class="lp-news-grid">
                        @foreach ($news as $item)
                            <a href="{{ $item['url'] ?: '#' }}" @if ($item['url']) target="_blank" rel="noopener" @endif class="lp-news-card">
                                <div class="lp-news-media">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                                    @else
                                        <x-heroicon-o-photo class="h-8 w-8 text-gray-500" />
                                    @endif
                                </div>
                                <div class="lp-news-body">
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

        {{-- ── FAQ (bố cục 2 cột: ảnh trái + danh sách phải, tham khảo mẫu) ──────── --}}
        <section class="lp-faq">
            <div class="lp-faq-inner">
                <div class="lp-faq-media">
                    @if ($faqImageUrl)
                        <img src="{{ $faqImageUrl }}" alt="Câu hỏi thường gặp" loading="lazy">
                    @else
                        <x-heroicon-o-chat-bubble-left-right class="h-12 w-12 text-white/30" />
                    @endif
                </div>
                <div class="lp-faq-content">
                    <span class="lp-eyebrow">Hỗ trợ</span>
                    <h2 class="lp-section-title" style="text-align:left;">Các câu hỏi thường gặp</h2>
                    <div class="lp-faq-list">
                        @foreach ($faqs as $i => $faq)
                            <div class="lp-faq-item" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                                <button type="button" class="lp-faq-question" @click="open = !open">
                                    <span>{{ $faq['question'] }}</span>
                                    <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 transition-transform" x-bind:style="open ? 'transform:rotate(180deg)' : ''" />
                                </button>
                                <div class="lp-faq-answer" x-show="open" x-collapse x-cloak>{{ $faq['answer'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ── CTA CUỐI TRANG ─────────────────────────────────────────────────── --}}
        <section class="lp-cta">
            <div class="lp-cta-inner">
                <h2>Sẵn sàng đặt sân ngay hôm nay?</h2>
                <p>Chọn chi nhánh phù hợp và đặt lịch chỉ trong vài phút.</p>
                <a href="#lp-venues" class="lp-btn-primary">Đặt sân ngay</a>
            </div>
        </section>

        {{-- ── FOOTER (nhiều cột: thương hiệu/mô tả — liên kết nhanh — liên hệ, tham khảo mẫu) ──── --}}
        <footer id="lp-contact" class="lp-footer">
            <div class="lp-footer-inner">
                <div class="lp-footer-grid">
                    <div class="lp-footer-col lp-footer-col-brand">
                        <div class="lp-footer-brand">
                            @if ($partnerLogoUrl)
                                <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lp-brand-logo">
                            @else
                                <span class="lp-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                            @endif
                            <span class="lp-brand-name">{{ $partner->name }}</span>
                        </div>
                        <p>{{ $description ? \Illuminate\Support\Str::limit($description, 140) : ('Đặt sân thể thao trực tuyến tại ' . $partner->name . ' — nhanh chóng, tiện lợi, xác nhận tức thì.') }}</p>
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" class="lp-footer-social" aria-label="Facebook">
                                <x-heroicon-o-globe-alt class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    <div class="lp-footer-col">
                        <h4>Liên kết nhanh</h4>
                        <a href="#lp-about">Giới thiệu</a>
                        <a href="#lp-services">Dịch vụ</a>
                        <a href="#lp-venues">Chi nhánh &amp; đặt sân</a>
                        @if ($pricingPlans->isNotEmpty())
                            <a href="#lp-pricing">Bảng giá</a>
                        @endif
                    </div>

                    <div class="lp-footer-col">
                        <h4>Liên hệ</h4>
                        @if ($partner->address)
                            <span class="lp-footer-item"><x-heroicon-o-map-pin class="h-4 w-4" />{{ $partner->address }}</span>
                        @endif
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lp-footer-item"><x-heroicon-o-phone class="h-4 w-4" />{{ $partner->phone }}</a>
                        @endif
                        @if ($partner->email)
                            <a href="mailto:{{ $partner->email }}" class="lp-footer-item"><x-heroicon-o-envelope class="h-4 w-4" />{{ $partner->email }}</a>
                        @endif
                    </div>
                </div>

                <div class="lp-footer-bottom">
                    © {{ now()->year }} {{ $partner->name }} — Cung cấp bởi {{ config('app.name') }}
                </div>
            </div>
        </footer>
    </div>

    @once
        <script>
            // Bóng đổ header khi cuộn xuống (tách biệt tinh tế với nội dung bên dưới) + hiệu ứng
            // "fade lên khi cuộn tới" cho các khối .lp-reveal — thuần vanilla JS/IntersectionObserver
            // (không phụ thuộc plugin Alpine ngoài x-collapse/x-show đã dùng sẵn ở nơi khác trong dự
            // án), an toàn nếu JS bị chặn (phần tử vẫn có nội dung trong DOM, chỉ mất hiệu ứng).
            document.addEventListener('DOMContentLoaded', function () {
                var header = document.querySelector('.lp-header');
                if (header) {
                    window.addEventListener('scroll', function () {
                        header.classList.toggle('is-scrolled', window.scrollY > 8);
                    }, { passive: true });
                }

                var revealTargets = document.querySelectorAll('.lp-reveal');
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
            .partner-landing-scope { --color-primary: var(--lp-color); font-family: inherit; color: #111827; -webkit-font-smoothing: antialiased; }
            .partner-landing-scope * { scroll-behavior: smooth; }
            .partner-landing-scope a, .partner-landing-scope button { transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, opacity .18s ease; }

            {{-- Nhãn nhỏ phía trên mỗi tiêu đề section (kiểu "eyebrow label" các trang SaaS/agency
                 hiện đại — tạo phân cấp thị giác rõ ràng hơn, giúp trang trông chuyên nghiệp hơn
                 thay vì tiêu đề trơn không có ngữ cảnh). --}}
            .lp-eyebrow { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .85rem; border-radius: 9999px; background: color-mix(in srgb, var(--lp-color) 14%, #fff); color: color-mix(in srgb, var(--lp-color) 70%, #111827); font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .75rem; }
            .lp-eyebrow::before { content: ''; width: 6px; height: 6px; border-radius: 9999px; background: var(--lp-color); }
            .lp-eyebrow-dark { background: rgba(255,255,255,.08); color: var(--lp-color); }

            {{-- Fade-up khi cuộn tới — nhẹ, không phụ thuộc plugin ngoài (chỉ CSS transition +
                 IntersectionObserver thuần, xem script cuối trang). Nếu JS chưa kịp chạy (chặn
                 script hiếm gặp), phần tử vẫn hiển thị bình thường nhờ noscript fallback bên dưới. --}}
            .lp-reveal { opacity: 0; transform: translateY(24px); transition: opacity .7s ease, transform .7s ease; }
            .lp-reveal.is-visible { opacity: 1; transform: translateY(0); }
            .no-js .lp-reveal { opacity: 1; transform: none; }

            /* Header */
            .lp-header { position: sticky; top: 0; z-index: 40; background: rgba(255,255,255,.92); backdrop-filter: blur(8px); border-bottom: 1px solid #e5e7eb; transition: box-shadow .2s ease; }
            .lp-header.is-scrolled { box-shadow: 0 4px 20px rgba(0,0,0,.06); }
            .lp-header-inner { max-width: 110rem; margin: 0 auto; padding: .85rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .lp-brand { display: inline-flex; align-items: center; gap: .6rem; text-decoration: none; }
            .lp-brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: .7rem; background: var(--lp-color); color: #111111; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; }
            .lp-brand-logo { width: 38px; height: 38px; border-radius: .7rem; object-fit: cover; flex-shrink: 0; }
            .lp-brand-name { font-weight: 800; font-size: 1.02rem; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; letter-spacing: -.01em; }
            .lp-header-nav { display: flex; align-items: center; gap: 1.5rem; }
            .lp-header-link { display: none; position: relative; font-size: .82rem; font-weight: 700; color: #4b5563; text-decoration: none; padding: .25rem 0; }
            .lp-header-link::after { content: ''; position: absolute; left: 0; right: 100%; bottom: 0; height: 2px; background: var(--lp-color); transition: right .2s ease; }
            .lp-header-link:hover { color: #111827; }
            .lp-header-link:hover::after { right: 0; }
            .lp-header-contact { display: none; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 700; color: #374151; text-decoration: none; }
            .lp-header-cta { display: inline-flex; align-items: center; padding: .55rem 1.25rem; border-radius: 9999px; background: #111111; color: var(--lp-color); font-size: .82rem; font-weight: 800; text-decoration: none; white-space: nowrap; box-shadow: 0 4px 14px rgba(0,0,0,.15); }
            .lp-header-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(0,0,0,.22); }
            @media (min-width: 640px) { .lp-header-contact { display: inline-flex; } }
            @media (min-width: 900px) { .lp-header-link { display: inline-flex; } }

            /* Hero — bố cục bento 3 ô (tham khảo mẫu Mona Pickleball). Toàn khối nền trắng, các ô
               con tự có nền riêng (đen/màu chủ đạo) thay vì cả section 1 màu gradient như bản cũ. */
            .lp-hero { padding: 1.5rem 1rem; background: #fff; }
            .lp-btn-primary { display: inline-flex; align-items: center; padding: .75rem 1.5rem; border-radius: 9999px; background: #fff; color: var(--lp-color); font-weight: 800; font-size: .88rem; text-decoration: none; box-shadow: 0 8px 20px rgba(0,0,0,.12); }
            .lp-btn-dark { display: inline-flex; align-items: center; padding: .75rem 1.75rem; border-radius: 9999px; background: #111827; color: #fff; font-weight: 800; font-size: .85rem; text-decoration: none; }
            .lp-btn-outline-dark { display: inline-flex; align-items: center; justify-content: center; margin-top: 1.25rem; padding: .65rem 1.25rem; border-radius: 9999px; border: 1.5px solid #111827; color: #111827; font-weight: 700; font-size: .82rem; text-decoration: none; }

            .lp-bento { max-width: 110rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 1rem; }
            .lp-bento-left { position: relative; overflow: hidden; background: #111111; border-radius: 1.5rem; padding: 2.25rem 1.75rem; display: flex; flex-direction: column; justify-content: center; }
            {{-- Hào quang mờ trang trí (glow) — lấy đúng màu chủ đạo của đối tác, tạo chiều sâu cho
                 mảng nền đen thay vì phẳng lì, phong cách landing page hiện đại. --}}
            .lp-bento-left::before { content: ''; position: absolute; top: -40%; right: -30%; width: 20rem; height: 20rem; border-radius: 9999px; background: radial-gradient(circle, color-mix(in srgb, var(--lp-color) 55%, transparent) 0%, transparent 70%); pointer-events: none; }
            {{-- Vòng tròn viền mảnh trang trí (tham khảo mẫu Mona Pickleball có các đường tròn nét
                 mảnh chạy khắp mảng đen) — 2 vòng lệch tâm, chỉ viền không tô, độ mờ thấp để không
                 chen lấn nội dung. --}}
            .lp-bento-left::after { content: ''; position: absolute; inset: 0; pointer-events: none; background-image:
                radial-gradient(circle at 15% 85%, transparent 59%, rgba(255,255,255,.08) 60%, rgba(255,255,255,.08) 61%, transparent 62%),
                radial-gradient(circle at 90% 15%, transparent 74%, rgba(255,255,255,.06) 75%, rgba(255,255,255,.06) 76%, transparent 77%); }
            .lp-bento-left > * { position: relative; }
            .lp-bento-left .lp-hero-title { font-size: 1.7rem; font-weight: 800; color: #fff; line-height: 1.25; text-align: left; margin: 0; letter-spacing: -.01em; }
            .lp-bento-left .lp-hero-desc { margin: 1rem 0 0; font-size: .85rem; line-height: 1.75; color: #9ca3af; text-align: left; max-width: none; }
            .lp-bento-cta { margin-top: 1.75rem; align-self: flex-start; display: inline-flex; align-items: center; gap: .75rem; padding: .4rem .4rem .4rem 1.5rem; border-radius: 9999px; background: var(--lp-color); color: #111111; font-weight: 800; font-size: .82rem; text-decoration: none; text-transform: uppercase; letter-spacing: .03em; box-shadow: 0 8px 24px color-mix(in srgb, var(--lp-color) 35%, transparent); }
            .lp-bento-cta:hover { transform: translateY(-2px); box-shadow: 0 12px 28px color-mix(in srgb, var(--lp-color) 45%, transparent); }
            .lp-bento-cta-icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 9999px; background: #111111; color: var(--lp-color); transition: transform .2s ease; }
            .lp-bento-cta:hover .lp-bento-cta-icon { transform: translateX(3px); }

            .lp-bento-middle { display: flex; flex-direction: column; gap: 1rem; }
            .lp-bento-media { position: relative; flex: 1.5; min-height: 12rem; border-radius: 1.25rem; overflow: hidden; background: #1a1a1a; display: flex; align-items: center; justify-content: center; }
            .lp-bento-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lp-bento-cards { display: flex; gap: 1rem; flex: 1; min-height: 8rem; }
            .lp-bento-card { flex: 1; border-radius: 1.25rem; padding: 1.25rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: .5rem; }
            .lp-bento-card-rating { background: color-mix(in srgb, var(--lp-color) 16%, #fefce8); }
            {{-- Dải avatar chồng nhau + badge số — tham khảo mẫu (3 ảnh khách hàng chồng viền trắng
                 + bubble số lớn). Không có ảnh khách hàng thật nên dùng icon người chung (an toàn,
                 không giả mạo ảnh thật) thay vì ảnh ngẫu nhiên từ nơi khác. --}}
            .lp-bento-avatar-stack { display: flex; align-items: center; }
            .lp-bento-avatar { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 9999px; background: #d1d5db; color: #6b7280; border: 2px solid #fff; margin-left: -10px; }
            .lp-bento-avatar:first-child { margin-left: 0; }
            .lp-bento-avatar-count { background: var(--lp-color); color: #111111; font-size: .62rem; font-weight: 800; margin-left: -6px; z-index: 1; }
            .lp-bento-stars { display: flex; gap: .15rem; color: var(--lp-color); }
            .lp-bento-stars svg.is-dim { color: #d1d5db; }
            .lp-bento-rating-num { font-size: .78rem; font-weight: 700; color: #374151; }
            .lp-bento-card-dark { background: #111111; gap: .85rem; }
            .lp-bento-stat-row { display: flex; flex-direction: column; }
            .lp-bento-stat-row strong { font-size: 1.4rem; font-weight: 800; color: var(--lp-color); }
            .lp-bento-stat-row span { font-size: .72rem; color: #9ca3af; }

            .lp-bento-media, .lp-bento-right, .lp-bento-left, .lp-bento-card { border-radius: 1.5rem; }
            .lp-bento-right { position: relative; min-height: 16rem; overflow: hidden; background: #1a1a1a; }
            .lp-bento-right img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lp-bento-right iframe.lp-bento-video-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; z-index: 2; }
            {{-- Lớp phủ gradient tối dần xuống đáy ảnh — để chữ chú thích LUÔN đọc được bất kể ảnh
                 nền sáng/tối (tham khảo mẫu: caption trắng đè trên ảnh, ảnh có thể sáng bất kỳ). --}}
            .lp-bento-scrim { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.65) 0%, transparent 45%); pointer-events: none; }
            .lp-bento-video-overlay { position: absolute; left: 1.25rem; right: 1.25rem; bottom: 1.25rem; z-index: 1; display: flex; align-items: center; gap: 1rem; }
            .lp-bento-play-btn { flex-shrink: 0; width: 48px; height: 48px; border-radius: 9999px; background: var(--lp-color); border: none; cursor: pointer; color: #111111; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 6px 18px rgba(0,0,0,.35); }
            .lp-bento-video-overlay p { margin: 0; font-size: .8rem; font-weight: 600; color: #fff; line-height: 1.4; text-shadow: 0 1px 4px rgba(0,0,0,.6); }

            @media (min-width: 1024px) {
                .lp-hero { padding: 2rem 1.5rem 3rem; }
                .lp-bento { grid-template-columns: 1fr 1.1fr 1fr; align-items: stretch; min-height: 32rem; }
                .lp-bento-left .lp-hero-title { font-size: 2.1rem; }
                .lp-bento-right { min-height: 0; }
            }

            /* Features */
            .lp-features { padding: 3.5rem 1rem; background: #fff; }
            .lp-features-inner { max-width: 96rem; margin: 0 auto; }
            {{-- flex-wrap + justify-content:center (thay vì grid cột cố định) — khi chưa đủ số mục
                 (vd mới nhập 1/4 điểm nổi bật), thẻ tự CĂN GIỮA thay vì dạt trái để lại khoảng
                 trắng lớn bên phải (bug đã gặp: "hai trang hoàn toàn khác nhau" — do lưới trống
                 chứ không phải sai cấu trúc). flex-basis cố định cũng tự xuống 1 cột trên mobile mà
                 không cần thêm media query riêng. --}}
            .lp-features-grid { margin-top: 2rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lp-feature { flex: 1 1 240px; max-width: 270px; background: #f9fafb; border: 1px solid #eef0f2; border-radius: 1.25rem; padding: 1.75rem 1.5rem; text-align: center; transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
            .lp-feature:hover { transform: translateY(-5px); box-shadow: 0 16px 32px rgba(17,24,39,.08); border-color: color-mix(in srgb, var(--lp-color) 30%, #eef0f2); }
            .lp-feature-icon { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; border-radius: .9rem; background: color-mix(in srgb, var(--lp-color) 12%, #fff); color: var(--lp-color); }
            .lp-feature h3 { margin-top: 1rem; font-size: .95rem; font-weight: 800; color: #111827; }
            .lp-feature p { margin-top: .45rem; font-size: .82rem; color: #6b7280; line-height: 1.6; }
            /* About */
            .lp-about { padding: 3rem 1rem; }
            .lp-about-inner { max-width: 56rem; margin: 0 auto; text-align: center; }
            .lp-about-text { margin-top: 1rem; font-size: .9rem; line-height: 1.75; color: #4b5563; white-space: pre-line; }

            /* Services */
            .lp-services { padding: 3.5rem 1rem; background: color-mix(in srgb, var(--lp-color) 6%, #fff); }
            .lp-services-inner { max-width: 96rem; margin: 0 auto; text-align: center; }
            .lp-services-grid { margin-top: 2rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; text-align: left; }
            .lp-service-card { flex: 1 1 280px; max-width: 340px; background: #fff; border-radius: 1.25rem; overflow: hidden; border: 1px solid #eef0f2; transition: transform .25s ease, box-shadow .25s ease; }
            .lp-service-card:hover { transform: translateY(-5px); box-shadow: 0 16px 32px rgba(17,24,39,.1); }
            .lp-service-media { height: 150px; background: #f3f4f6; display: flex; align-items: center; justify-content: center; overflow: hidden; }
            .lp-service-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lp-service-card:hover .lp-service-media img { transform: scale(1.06); }
            .lp-service-body { padding: 1.25rem 1.4rem; }
            .lp-service-body h3 { font-size: .94rem; font-weight: 800; color: #111827; }
            .lp-service-body p { margin-top: .4rem; font-size: .8rem; color: #6b7280; line-height: 1.55; }
            .lp-section-cta { margin-top: 2.25rem; }

            /* Pricing */
            .lp-pricing { padding: 3.5rem 1rem; }
            .lp-pricing-inner { max-width: 96rem; margin: 0 auto; text-align: center; }
            .lp-pricing-grid { margin-top: 2.25rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lp-pricing-card { flex: 1 1 300px; max-width: 340px; border: 1px solid #e5e7eb; border-radius: 1.5rem; padding: 2.25rem 1.75rem; text-align: left; transition: transform .25s ease, box-shadow .25s ease; }
            .lp-pricing-card:not(.is-highlighted):hover { transform: translateY(-4px); box-shadow: 0 16px 32px rgba(17,24,39,.08); }
            .lp-pricing-card.is-highlighted { position: relative; border-color: var(--lp-color); box-shadow: 0 16px 36px color-mix(in srgb, var(--lp-color) 22%, transparent); transform: translateY(-6px); }
            .lp-pricing-card.is-highlighted::before { content: 'Phổ biến nhất'; position: absolute; top: -13px; left: 50%; transform: translateX(-50%); padding: .3rem .9rem; border-radius: 9999px; background: var(--lp-color); color: #111111; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
            .lp-pricing-card h3 { font-size: 1rem; font-weight: 800; color: #111827; }
            .lp-pricing-amount { margin-top: .5rem; font-size: 1.6rem; font-weight: 800; color: var(--lp-color); }
            .lp-pricing-amount span { font-size: .78rem; font-weight: 600; color: #9ca3af; }
            .lp-pricing-features { margin-top: 1.25rem; display: flex; flex-direction: column; gap: .55rem; }
            .lp-pricing-features li { display: flex; align-items: flex-start; gap: .5rem; font-size: .8rem; color: #4b5563; }
            .lp-pricing-features li svg { color: var(--lp-color); margin-top: .1rem; }
            .lp-pricing-card .lp-btn-primary, .lp-pricing-card .lp-btn-outline-dark { display: flex; width: 100%; justify-content: center; margin-top: 1.5rem; }
            .lp-pricing-card .lp-btn-primary { background: var(--lp-color); color: #fff; box-shadow: none; }

            /* Stats (dark) */
            .lp-stats { position: relative; overflow: hidden; padding: 4rem 1rem; background: #111111; color: #fff; }
            .lp-stats::before { content: ''; position: absolute; top: -30%; left: -10%; width: 24rem; height: 24rem; border-radius: 9999px; background: radial-gradient(circle, color-mix(in srgb, var(--lp-color) 30%, transparent) 0%, transparent 70%); pointer-events: none; }
            .lp-stats > * { position: relative; }
            .lp-stats-inner { max-width: 96rem; margin: 0 auto; display: flex; flex-direction: column; gap: 2rem; }
            .lp-stats-text h2 { font-size: 1.4rem; font-weight: 800; }
            .lp-stats-text p { margin-top: .5rem; font-size: .85rem; color: #9ca3af; max-width: 36rem; }
            .lp-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
            .lp-stat-num { display: block; font-size: 1.9rem; font-weight: 800; color: var(--lp-color); }
            .lp-stat-label { display: block; margin-top: .25rem; font-size: .78rem; color: #9ca3af; }
            @media (min-width: 768px) { .lp-stats-inner { flex-direction: row; align-items: center; justify-content: space-between; } .lp-stats-grid { grid-template-columns: repeat(4, 1fr); } }

            /* Testimonials (dark) */
            .lp-testimonials { position: relative; overflow: hidden; padding: 4rem 1rem; background: #0b0b0b; }
            .lp-testimonials::before { content: ''; position: absolute; bottom: -30%; right: -10%; width: 24rem; height: 24rem; border-radius: 9999px; background: radial-gradient(circle, color-mix(in srgb, var(--lp-color) 25%, transparent) 0%, transparent 70%); pointer-events: none; }
            .lp-testimonials-inner { position: relative; max-width: 64rem; margin: 0 auto; text-align: center; }
            {{-- 2 class ghép (không phải 1) — bắt buộc để LUÔN thắng .lp-section-title (định nghĩa
                 SAU trong file, cùng độ ưu tiên 1-class thì rule khai báo sau thắng bất kể thứ tự
                 class trong thuộc tính HTML) — bug đã gặp: heading trên nền tối (Đánh giá/Tin tức)
                 bị màu xám tối #111827 đè lên thay vì trắng, gần như vô hình trên nền đen. --}}
            .lp-section-title.lp-section-title-dark { color: #fff; }
            .lp-testimonial { margin-top: 2rem; display: flex; flex-direction: column; align-items: center; gap: 1.5rem; }
            .lp-testimonial-media img { width: 96px; height: 96px; border-radius: 9999px; object-fit: cover; }
            .lp-testimonial-avatar { width: 96px; height: 96px; border-radius: 9999px; background: var(--lp-color); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; }
            .lp-testimonial-card { background: #17181a; border-radius: 1rem; padding: 1.5rem; }
            .lp-testimonial-card p { font-size: .92rem; color: #e5e7eb; line-height: 1.7; font-style: italic; }
            .lp-testimonial-author { margin-top: 1rem; display: flex; flex-direction: column; }
            .lp-testimonial-author strong { color: #fff; font-size: .88rem; }
            .lp-testimonial-author span { font-size: .76rem; color: #9ca3af; }
            .lp-testimonial-dots { margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: .5rem; }
            .lp-testimonial-dots button { width: 8px; height: 8px; border-radius: 9999px; background: #3f3f3f; border: none; padding: 0; cursor: pointer; }
            .lp-testimonial-dots button.is-active { background: var(--lp-color); width: 20px; }
            @media (min-width: 640px) { .lp-testimonial { flex-direction: row; text-align: left; } }

            /* Venues */
            .lp-venues { padding: 3.5rem 1rem 4rem; }
            .lp-venues-inner { max-width: 110rem; margin: 0 auto; }
            .lp-section-title { font-size: 1.4rem; font-weight: 800; color: #111827; text-align: center; }
            .lp-section-sub { margin-top: .35rem; font-size: .85rem; color: #6b7280; text-align: center; }
            .lp-venues-grid { margin-top: 1.75rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1rem; }
            .lp-venues-grid > * { flex: 1 1 220px; max-width: 280px; }
            .lp-empty { padding: 4rem 0; text-align: center; font-size: .9rem; color: #9ca3af; }

            /* News (dark) */
            .lp-news { padding: 4rem 1rem; background: #111111; }
            .lp-news-inner { max-width: 96rem; margin: 0 auto; text-align: center; }
            .lp-news-grid { margin-top: 2rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; text-align: left; }
            .lp-news-card { flex: 1 1 240px; max-width: 280px; display: block; background: #191a1c; border-radius: 1.25rem; overflow: hidden; text-decoration: none; border: 1px solid #232427; transition: transform .25s ease, border-color .25s ease; }
            .lp-news-card:hover { transform: translateY(-5px); border-color: color-mix(in srgb, var(--lp-color) 40%, #232427); }
            .lp-news-media { height: 150px; background: #22242a; display: flex; align-items: center; justify-content: center; overflow: hidden; }
            .lp-news-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lp-news-card:hover .lp-news-media img { transform: scale(1.06); }
            .lp-news-body { padding: 1.25rem 1.4rem; }
            .lp-news-body h3 { font-size: .88rem; font-weight: 800; color: #fff; line-height: 1.4; }
            .lp-news-body p { margin-top: .4rem; font-size: .78rem; color: #9ca3af; line-height: 1.55; }
            @media (min-width: 640px) { .lp-news-grid { grid-template-columns: repeat(2, 1fr); } }

            /* FAQ — 2 cột: ảnh trái + danh sách phải (tham khảo mẫu) */
            .lp-faq { padding: 4rem 1rem; background: #f9fafb; }
            .lp-faq-inner { max-width: 96rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 2rem; align-items: center; }
            .lp-faq-media { min-height: 14rem; border-radius: 1.5rem; overflow: hidden; background: #111111; display: flex; align-items: center; justify-content: center; }
            .lp-faq-media img { width: 100%; height: 100%; object-fit: cover; }
            .lp-faq-list { margin-top: 2rem; display: flex; flex-direction: column; gap: .75rem; }
            @media (min-width: 900px) { .lp-faq-inner { grid-template-columns: .85fr 1.15fr; } .lp-faq-media { height: 100%; min-height: 26rem; } }
            .lp-faq-item { background: #fff; border: 1px solid #e5e7eb; border-radius: 1rem; overflow: hidden; transition: border-color .2s ease; }
            .lp-faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.35rem; background: none; border: none; text-align: left; font-size: .88rem; font-weight: 700; color: #111827; cursor: pointer; }
            .lp-faq-question svg { color: var(--lp-color); }
            .lp-faq-answer { padding: 0 1.35rem 1.2rem; font-size: .83rem; color: #6b7280; line-height: 1.65; }

            /* CTA */
            .lp-cta { position: relative; overflow: hidden; padding: 4.5rem 1rem; text-align: center; background: linear-gradient(135deg, var(--lp-color) 0%, var(--lp-color-heavy) 100%); }
            .lp-cta::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 20% 20%, rgba(255,255,255,.18) 0%, transparent 45%), radial-gradient(circle at 80% 80%, rgba(0,0,0,.15) 0%, transparent 45%); pointer-events: none; }
            .lp-cta-inner { position: relative; max-width: 42rem; margin: 0 auto; }
            .lp-cta h2 { font-size: 1.6rem; font-weight: 800; color: #fff; letter-spacing: -.01em; }
            .lp-cta p { margin-top: .6rem; font-size: .9rem; color: rgba(255,255,255,.9); }
            .lp-cta .lp-btn-primary { margin-top: 1.75rem; }
            .lp-cta .lp-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(0,0,0,.2); }

            /* Footer — nhiều cột (thương hiệu/mô tả, liên kết nhanh, liên hệ) */
            .lp-footer { background: #0b0b0b; color: #e5e7eb; padding: 3.5rem 1rem 1.5rem; }
            .lp-footer-inner { max-width: 110rem; margin: 0 auto; }
            .lp-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; text-align: center; }
            .lp-footer-col { display: flex; flex-direction: column; align-items: center; gap: .65rem; }
            .lp-footer-col h4 { font-size: .82rem; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .35rem; }
            .lp-footer-col a { font-size: .82rem; color: #9ca3af; text-decoration: none; }
            .lp-footer-col a:hover { color: var(--lp-color); }
            .lp-footer-col-brand { max-width: 24rem; margin: 0 auto; }
            .lp-footer-brand { display: flex; align-items: center; gap: .6rem; }
            .lp-footer-brand .lp-brand-name { color: #fff; }
            .lp-footer-col-brand p { font-size: .82rem; color: #9ca3af; line-height: 1.6; }
            .lp-footer-social { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 9999px; background: #1a1a1a; color: #d1d5db; }
            .lp-footer-social:hover { background: var(--lp-color); color: #111111; }
            .lp-footer-item { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; color: #9ca3af; text-decoration: none; }
            .lp-footer-bottom { margin-top: 2.5rem; text-align: center; font-size: .74rem; color: #6b7280; border-top: 1px solid #1f2937; padding-top: 1.25rem; }
            @media (min-width: 768px) { .lp-footer-grid { grid-template-columns: 1.4fr 1fr 1fr; text-align: left; } .lp-footer-col { align-items: flex-start; } .lp-footer-col-brand { margin: 0; } }
        </style>
    @endonce
@endsection
