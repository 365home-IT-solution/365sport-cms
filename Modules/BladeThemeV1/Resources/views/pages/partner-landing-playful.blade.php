@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Biến thể "Playful" của trang landing page riêng cho từng đối tác theo subdomain — cùng bộ
         biến Blade/hợp đồng dữ liệu với bladethemev1::pages.partner-landing (bento, mặc định) và 2
         biến thể editorial/minimal/split khác, CHỈ khác ngôn ngữ thị giác: nền sáng, khối "blob"
         bo méo trang trí bằng màu chủ đạo độ mờ thấp, mọi góc đều bo tròn lớn (thẻ 1.5–2.5rem+, nút
         dạng viên thuốc, icon tròn), hiệu ứng hover "nảy" (scale + xoay nhẹ) — hướng tới cảm giác
         1 app thể thao/mạng xã hội vui vẻ, thân thiện, rủ bạn bè đi chơi thể thao, KHÔNG phải công
         cụ đặt sân nghiêm túc kiểu doanh nghiệp. Admin chọn biến thể ở tab "Trang landing"
         (PartnerForm, cột landing_template) — xem BladeThemeV1Controller::partnerLandingPage().

         Nút "Đặt lịch" trên từng thẻ chi nhánh trỏ THẲNG về domain chính (xem
         components/venues/card.blade.php, dùng chung y hệt bản gốc) — đặt sân vẫn xử lý ở domain
         gốc, trang này chỉ giới thiệu + điều hướng.

         Màu chủ đạo ($landingColor/...Heavy/...Light) admin tự chọn riêng cho từng đối tác — scope
         bằng CSS custom property cục bộ trong .partner-landing-playful-scope, KHÔNG đổi
         --color-primary toàn site. --}}
    <div class="partner-landing-playful-scope" style="
        --lpp-color: {{ $landingColor }};
        --lpp-color-heavy: {{ $landingColorHeavy }};
        --lpp-color-light: {{ $landingColorLight }};
    ">
        {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
        <header class="lpp-header">
            <div class="lpp-header-inner">
                <a href="#" class="lpp-brand">
                    @if ($partnerLogoUrl)
                        <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpp-brand-logo">
                    @else
                        <span class="lpp-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                    @endif
                    <span class="lpp-brand-name">{{ $partner->name }}</span>
                </a>

                <nav class="lpp-header-nav">
                    @if ($description)
                        <a href="#lp-about" class="lpp-header-link">Giới thiệu</a>
                    @endif
                    <a href="#lp-services" class="lpp-header-link">Dịch vụ</a>
                    @if ($pricingPlans->isNotEmpty())
                        <a href="#lp-pricing" class="lpp-header-link">Bảng giá</a>
                    @endif
                    <a href="#lp-contact" class="lpp-header-link">Liên hệ</a>
                    @if ($partner->phone)
                        <a href="tel:{{ $partner->phone }}" class="lpp-header-contact">
                            <x-heroicon-o-phone class="h-4 w-4" />
                            <span>{{ $partner->phone }}</span>
                        </a>
                    @endif
                    <a href="#lp-venues" class="lpp-header-cta">
                        <span>Chơi ngay</span>
                        <x-heroicon-o-sparkles class="h-4 w-4" />
                    </a>
                </nav>
            </div>
        </header>

        {{-- ── HERO ────────────────────────────────────────────────────────────
             Nền sáng + blob trang trí lớn phía sau, chữ nghiêng ngộ nghĩnh nhẹ, ảnh chính lồng
             trong khung bo méo, ảnh phụ làm thẻ nổi nhỏ đè lên góc — dùng CẢ 2 ảnh hero, khác hẳn
             layout bento 3 cột phẳng của bản gốc. --}}
        <section class="lpp-hero">
            <div class="lpp-blob lpp-blob-1" aria-hidden="true"></div>
            <div class="lpp-blob lpp-blob-2" aria-hidden="true"></div>
            <div class="lpp-dots" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span>
            </div>

            <div class="lpp-hero-inner">
                <div class="lpp-hero-text">
                    <span class="lpp-eyebrow">
                        <x-heroicon-s-face-smile class="h-4 w-4" />
                        Chào mừng đến với {{ $partner->name }}
                    </span>
                    <h1 class="lpp-hero-title">{{ $tagline }}</h1>
                    <p class="lpp-hero-desc">{{ $description ?: ('Đặt sân trực tuyến tại ' . $partner->name . ' — chọn chi nhánh, xem lịch trống theo thời gian thực và rủ hội bạn ra sân chỉ trong vài bước.') }}</p>

                    <div class="lpp-hero-actions">
                        <a href="#lp-venues" class="lpp-btn-primary">
                            <span>Đặt sân ngay</span>
                            <span class="lpp-btn-primary-icon"><x-heroicon-o-arrow-right class="h-4 w-4" /></span>
                        </a>
                        @if ($heroYoutubeId)
                            <a href="#lpp-hero-video" class="lpp-btn-ghost" onclick="document.getElementById('lpp-hero-video').scrollIntoView({behavior:'smooth'})">
                                <span class="lpp-btn-ghost-icon"><svg viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"><path d="M8 5v14l11-7z" /></svg></span>
                                <span>Xem video</span>
                            </a>
                        @endif
                    </div>

                    <div class="lpp-hero-rating">
                        @if ($avgRating)
                            <div class="lpp-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 {{ $i <= round($avgRating) ? '' : 'is-dim' }}" />
                                @endfor
                            </div>
                            <span>{{ number_format($avgRating, 1) }} / 5 — hội bạn đã đánh giá</span>
                        @else
                            <div class="lpp-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 is-dim" />
                                @endfor
                            </div>
                            <span>Chưa có đánh giá</span>
                        @endif
                    </div>
                </div>

                <div class="lpp-hero-media" id="lpp-hero-video" @if ($heroYoutubeId) x-data="{ playing: false }" @endif>
                    <div class="lpp-hero-media-main">
                        @if ($heroYoutubeId)
                            @if ($heroImageUrl)
                                <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="lazy" x-show="!playing">
                            @else
                                <img src="https://i3.ytimg.com/vi/{{ $heroYoutubeId }}/hqdefault.jpg" alt="{{ $partner->name }}" x-show="!playing">
                            @endif
                            <button type="button" class="lpp-play-btn" x-show="!playing" @click="playing = true" aria-label="Xem video giới thiệu">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M8 5v14l11-7z" /></svg>
                            </button>
                            <template x-if="playing">
                                <iframe
                                    class="lpp-hero-video-frame"
                                    src="https://www.youtube-nocookie.com/embed/{{ $heroYoutubeId }}?autoplay=1&rel=0"
                                    title="Video giới thiệu {{ $partner->name }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </template>
                        @elseif ($heroImageUrl)
                            <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="lazy">
                        @else
                            <div class="lpp-media-placeholder"><x-heroicon-o-photo class="h-10 w-10" /></div>
                        @endif
                    </div>

                    @if ($heroSecondaryImageUrl)
                        <div class="lpp-hero-media-float">
                            <img src="{{ $heroSecondaryImageUrl }}" alt="{{ $partner->name }}" loading="lazy">
                        </div>
                    @endif

                    <div class="lpp-hero-badge">
                        <span class="lpp-hero-badge-icon"><x-heroicon-s-bolt class="h-4 w-4" /></span>
                        <span>Đặt lịch trong 30 giây!</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── ĐIỂM NỔI BẬT ───────────────────────────────────────────────────── --}}
        <section class="lpp-features">
            <div class="lpp-features-inner lpp-reveal">
                <div style="text-align:center;"><span class="lpp-eyebrow lpp-eyebrow-onlight">Vì sao bạn sẽ mê nơi này</span></div>
                <h2 class="lpp-section-title">Điều thú vị về {{ $partner->name }}</h2>
                <div class="lpp-features-grid">
                    @foreach ($features as $i => $feature)
                        <div class="lpp-feature lpp-tilt-{{ $i % 2 === 0 ? 'a' : 'b' }}">
                            <span class="lpp-feature-icon">
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
            <section id="lp-about" class="lpp-about">
                <div class="lpp-blob lpp-blob-3" aria-hidden="true"></div>
                <div class="lpp-about-inner lpp-reveal">
                    <span class="lpp-eyebrow lpp-eyebrow-onlight">Câu chuyện của chúng tôi</span>
                    <h2 class="lpp-section-title">Giới thiệu về {{ $partner->name }}</h2>
                    <p class="lpp-about-text">{{ $description }}</p>
                </div>
            </section>
        @endif

        {{-- ── DỊCH VỤ ────────────────────────────────────────────────────────── --}}
        <section id="lp-services" class="lpp-services">
            <div class="lpp-services-inner lpp-reveal">
                <span class="lpp-eyebrow lpp-eyebrow-onlight">Dịch vụ</span>
                <h2 class="lpp-section-title">Cung cấp dịch vụ thuê sân vui hết nấc</h2>
                <div class="lpp-services-grid">
                    @foreach ($services as $service)
                        <div class="lpp-service-card">
                            <div class="lpp-service-media">
                                @if (! empty($service['image']))
                                    <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy">
                                @else
                                    <x-heroicon-o-photo class="h-8 w-8" />
                                @endif
                            </div>
                            <div class="lpp-service-body">
                                <h3>{{ $service['title'] }}</h3>
                                @if (! empty($service['description']))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="lpp-section-cta">
                    <a href="#lp-venues" class="lpp-btn-dark">Đặt sân ngay</a>
                </div>
            </div>
        </section>

        {{-- ── BẢNG GIÁ (chỉ hiện khi admin có cấu hình landing_pricing_plans) ──── --}}
        @if ($pricingPlans->isNotEmpty())
            <section id="lp-pricing" class="lpp-pricing">
                <div class="lpp-pricing-inner lpp-reveal">
                    <span class="lpp-eyebrow lpp-eyebrow-onlight">Bảng giá</span>
                    <h2 class="lpp-section-title">Chọn gói vui phù hợp với bạn</h2>
                    <div class="lpp-pricing-grid">
                        @foreach ($pricingPlans as $plan)
                            <div class="lpp-pricing-card {{ ! empty($plan['highlighted']) ? 'is-highlighted' : '' }}">
                                @if (! empty($plan['highlighted']))
                                    <span class="lpp-pricing-badge">
                                        <x-heroicon-s-fire class="h-3.5 w-3.5" />
                                        Được yêu thích nhất
                                    </span>
                                @endif
                                <h3>{{ $plan['name'] }}</h3>
                                <div class="lpp-pricing-amount">
                                    {{ $plan['price'] }}
                                    @if (! empty($plan['unit']))
                                        <span>{{ $plan['unit'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($plan['features']))
                                    <ul class="lpp-pricing-features">
                                        @foreach ($plan['features'] as $line)
                                            <li><x-heroicon-s-check-circle class="h-4 w-4" />{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#lp-venues" class="{{ ! empty($plan['highlighted']) ? 'lpp-btn-primary' : 'lpp-btn-outline' }}">Đăng ký ngay</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── THÀNH TỰU ──────────────────────────────────────────────────────── --}}
        <section class="lpp-stats">
            <div class="lpp-blob lpp-blob-4" aria-hidden="true"></div>
            <div class="lpp-stats-inner lpp-reveal">
                <div class="lpp-stats-text">
                    <span class="lpp-eyebrow lpp-eyebrow-onlight">Thành tựu</span>
                    <h2>Con số biết nói của {{ $partner->name }}</h2>
                    <p>Đồng hành cùng hàng nghìn khách hàng yêu thể thao, {{ $partner->name }} không ngừng nâng cao chất lượng dịch vụ mỗi ngày.</p>
                </div>
                <div class="lpp-stats-grid">
                    @foreach ($stats as $stat)
                        <div class="lpp-stat">
                            <span class="lpp-stat-num">{{ $stat['number'] }}</span>
                            <span class="lpp-stat-label">{{ $stat['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── ĐÁNH GIÁ (chỉ hiện khi admin có cấu hình) ─────────────────────────── --}}
        @if (! empty($testimonials))
            <section class="lpp-testimonials">
                <div class="lpp-testimonials-inner lpp-reveal">
                    <div style="text-align:center;"><span class="lpp-eyebrow lpp-eyebrow-onlight">Hội bạn nói gì</span></div>
                    <h2 class="lpp-section-title">Khách hàng thân thiết review</h2>

                    <div class="lpp-testimonials-grid">
                        @foreach ($testimonials as $t)
                            <div class="lpp-testimonial-card">
                                <div class="lpp-testimonial-quote-icon"><x-heroicon-s-chat-bubble-bottom-center-text class="h-5 w-5" /></div>
                                <p>"{{ $t['quote'] }}"</p>
                                <div class="lpp-testimonial-author">
                                    @if (! empty($t['image']))
                                        <img src="{{ $t['image'] }}" alt="{{ $t['name'] }}">
                                    @else
                                        <span class="lpp-testimonial-avatar">{{ mb_substr($t['name'], 0, 1) }}</span>
                                    @endif
                                    <div>
                                        <strong>{{ $t['name'] }}</strong>
                                        @if (! empty($t['role']))
                                            <span>{{ $t['role'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── DANH SÁCH CHI NHÁNH ───────────────────────────────────────────────── --}}
        <section id="lp-venues" class="lpp-venues">
            <div class="lpp-venues-inner lpp-reveal">
                @if ($venues->isNotEmpty())
                    <div style="text-align:center;"><span class="lpp-eyebrow lpp-eyebrow-onlight">Chi nhánh</span></div>
                    <h2 class="lpp-section-title">Chọn sân và bắt đầu chơi thôi!</h2>
                    <p class="lpp-section-sub">Chọn 1 chi nhánh gần bạn để xem lịch trống và đặt sân ngay.</p>

                    <div class="lpp-venues-grid">
                        @foreach ($venues as $venue)
                            <x-bladethemev1::venues.card :venue="$venue" />
                        @endforeach
                    </div>
                @else
                    <div class="lpp-empty">
                        <span class="lpp-empty-icon"><x-heroicon-o-map-pin class="h-8 w-8" /></span>
                        <p>{{ $partner->name }} hiện chưa có chi nhánh nào hiển thị. Ghé lại sau nhé!</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- ── TIN TỨC / ƯU ĐÃI (chỉ hiện khi admin có cấu hình) ─────────────────── --}}
        @if (! empty($news))
            <section class="lpp-news">
                <div class="lpp-news-inner lpp-reveal">
                    <span class="lpp-eyebrow lpp-eyebrow-onlight">Cập nhật</span>
                    <h2 class="lpp-section-title">Tin tức &amp; ưu đãi cực vui</h2>
                    <div class="lpp-news-grid">
                        @foreach ($news as $item)
                            <a href="{{ $item['url'] ?: '#' }}" @if ($item['url']) target="_blank" rel="noopener" @endif class="lpp-news-card">
                                <div class="lpp-news-media">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                                    @else
                                        <x-heroicon-o-photo class="h-8 w-8" />
                                    @endif
                                </div>
                                <div class="lpp-news-body">
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

        {{-- ── FAQ ─────────────────────────────────────────────────────────────── --}}
        <section class="lpp-faq">
            <div class="lpp-faq-inner lpp-reveal">
                <div class="lpp-faq-media">
                    @if ($faqImageUrl)
                        <img src="{{ $faqImageUrl }}" alt="Câu hỏi thường gặp" loading="lazy">
                    @else
                        <x-heroicon-o-chat-bubble-left-right class="h-12 w-12" />
                    @endif
                    <div class="lpp-faq-media-badge">
                        <x-heroicon-s-question-mark-circle class="h-5 w-5" />
                        <span>Có gì thắc mắc cứ hỏi nhé!</span>
                    </div>
                </div>
                <div class="lpp-faq-content">
                    <span class="lpp-eyebrow lpp-eyebrow-onlight">Hỗ trợ</span>
                    <h2 class="lpp-section-title" style="text-align:left;">Các câu hỏi thường gặp</h2>
                    <div class="lpp-faq-list">
                        @foreach ($faqs as $i => $faq)
                            <div class="lpp-faq-item" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                                <button type="button" class="lpp-faq-question" @click="open = !open">
                                    <span>{{ $faq['question'] }}</span>
                                    <span class="lpp-faq-question-icon" x-bind:style="open ? 'transform:rotate(180deg)' : ''">
                                        <x-heroicon-o-chevron-down class="h-4 w-4" />
                                    </span>
                                </button>
                                <div class="lpp-faq-answer" x-show="open" x-collapse x-cloak>{{ $faq['answer'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ── CTA CUỐI TRANG ─────────────────────────────────────────────────── --}}
        <section class="lpp-cta">
            <div class="lpp-dots lpp-dots-cta" aria-hidden="true">
                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
            <div class="lpp-cta-inner lpp-reveal">
                <span class="lpp-cta-badge"><x-heroicon-s-sparkles class="h-4 w-4" /></span>
                <h2>Sẵn sàng ra sân chưa nào?</h2>
                <p>Chọn chi nhánh phù hợp, rủ hội bạn và đặt lịch chỉ trong vài phút.</p>
                <a href="#lp-venues" class="lpp-btn-white">Đặt sân ngay</a>
            </div>
        </section>

        {{-- ── FOOTER ─────────────────────────────────────────────────────────── --}}
        <footer id="lp-contact" class="lpp-footer">
            <div class="lpp-footer-inner">
                <div class="lpp-footer-grid">
                    <div class="lpp-footer-col lpp-footer-col-brand">
                        <div class="lpp-footer-brand">
                            @if ($partnerLogoUrl)
                                <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpp-brand-logo">
                            @else
                                <span class="lpp-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                            @endif
                            <span class="lpp-brand-name">{{ $partner->name }}</span>
                        </div>
                        <p>{{ $description ? \Illuminate\Support\Str::limit($description, 140) : ('Đặt sân thể thao trực tuyến tại ' . $partner->name . ' — nhanh chóng, tiện lợi, xác nhận tức thì.') }}</p>
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" class="lpp-footer-social" aria-label="Facebook">
                                <x-heroicon-o-globe-alt class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    <div class="lpp-footer-col">
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

                    <div class="lpp-footer-col">
                        <h4>Liên hệ</h4>
                        @if ($partner->address)
                            <span class="lpp-footer-item"><x-heroicon-o-map-pin class="h-4 w-4" />{{ $partner->address }}</span>
                        @endif
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lpp-footer-item"><x-heroicon-o-phone class="h-4 w-4" />{{ $partner->phone }}</a>
                        @endif
                        @if ($partner->email)
                            <a href="mailto:{{ $partner->email }}" class="lpp-footer-item"><x-heroicon-o-envelope class="h-4 w-4" />{{ $partner->email }}</a>
                        @endif
                    </div>
                </div>

                <div class="lpp-footer-bottom">
                    © {{ now()->year }} {{ $partner->name }} — Cung cấp bởi {{ config('app.name') }}
                </div>
            </div>
        </footer>
    </div>

    @once
        <script>
            // Bóng đổ header khi cuộn xuống + hiệu ứng "nảy lên khi cuộn tới" cho các khối
            // .lpp-reveal — thuần vanilla JS/IntersectionObserver, không phụ thuộc plugin ngoài
            // (an toàn nếu JS bị chặn, phần tử vẫn có nội dung trong DOM, chỉ mất hiệu ứng).
            document.addEventListener('DOMContentLoaded', function () {
                var header = document.querySelector('.lpp-header');
                if (header) {
                    window.addEventListener('scroll', function () {
                        header.classList.toggle('is-scrolled', window.scrollY > 8);
                    }, { passive: true });
                }

                var revealTargets = document.querySelectorAll('.lpp-reveal');
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
            .partner-landing-playful-scope { --color-primary: var(--lpp-color); font-family: inherit; color: #1f2430; -webkit-font-smoothing: antialiased; background: #fff; overflow-x: hidden; }
            .partner-landing-playful-scope * { scroll-behavior: smooth; }
            .partner-landing-playful-scope a, .partner-landing-playful-scope button { transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s ease, background-color .22s ease, opacity .22s ease, border-color .22s ease; }
            .partner-landing-playful-scope section { position: relative; }

            {{-- Nhãn nhỏ "eyebrow" viên thuốc phía trên tiêu đề — dùng chung cho khối trên nền
                 sáng lẫn khối màu (bento/testimonials...), 2 biến thể màu khác nhau qua modifier
                 class thay vì trùng lặp toàn bộ khối thuộc tính. --}}
            .lpp-eyebrow { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .9rem; border-radius: 9999px; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .85rem; }
            .lpp-eyebrow-onlight { background: color-mix(in srgb, var(--lpp-color) 14%, #fff); color: color-mix(in srgb, var(--lpp-color) 65%, #1f2430); }

            {{-- Fade + nảy nhẹ khi cuộn tới — thuần CSS transition + IntersectionObserver (xem
                 script phía trên). Không có noscript fallback riêng vì observer JS luôn set
                 is-visible ngay khi không hỗ trợ IntersectionObserver. --}}
            .lpp-reveal { opacity: 0; transform: translateY(28px) scale(.98); transition: opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1); }
            .lpp-reveal.is-visible { opacity: 1; transform: translateY(0) scale(1); }

            {{-- Chấm bi trang trí rải rác — mảng span rỗng định vị bằng nth-child, tạo cảm giác
                 "confetti" vui tươi mà không cần ảnh nền. --}}
            .lpp-dots { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 0; }
            .lpp-dots span { position: absolute; width: 10px; height: 10px; border-radius: 9999px; background: var(--lpp-color); opacity: .35; }
            .lpp-dots span:nth-child(1) { top: 12%; left: 6%; width: 14px; height: 14px; }
            .lpp-dots span:nth-child(2) { top: 22%; left: 88%; }
            .lpp-dots span:nth-child(3) { top: 68%; left: 4%; width: 8px; height: 8px; }
            .lpp-dots span:nth-child(4) { top: 82%; left: 92%; width: 16px; height: 16px; }
            .lpp-dots span:nth-child(5) { top: 45%; left: 95%; width: 6px; height: 6px; }
            .lpp-dots span:nth-child(6) { top: 8%; left: 45%; width: 8px; height: 8px; }
            .lpp-dots-cta span { background: #fff; opacity: .5; }

            {{-- Blob hữu cơ trang trí — border-radius méo + màu chủ đạo độ mờ thấp, đặt phía sau
                 nội dung (z-index thấp hơn). Không dùng ảnh/svg ngoài, thuần CSS để nhẹ. --}}
            .lpp-blob { position: absolute; z-index: 0; pointer-events: none; filter: blur(2px); }
            .lpp-blob-1 { top: -6rem; right: -8rem; width: 26rem; height: 26rem; border-radius: 63% 37% 54% 46% / 55% 45% 55% 45%; background: color-mix(in srgb, var(--lpp-color) 22%, transparent); }
            .lpp-blob-2 { bottom: -10rem; left: -10rem; width: 22rem; height: 22rem; border-radius: 42% 58% 65% 35% / 48% 40% 60% 52%; background: color-mix(in srgb, var(--lpp-color-light) 30%, transparent); }
            .lpp-blob-3 { top: -4rem; left: 50%; transform: translateX(-50%); width: 30rem; height: 18rem; border-radius: 55% 45% 40% 60% / 60% 50% 50% 40%; background: color-mix(in srgb, var(--lpp-color) 10%, transparent); }
            .lpp-blob-4 { bottom: -8rem; right: -6rem; width: 24rem; height: 24rem; border-radius: 48% 52% 62% 38% / 40% 48% 52% 60%; background: color-mix(in srgb, var(--lpp-color) 24%, transparent); }

            /* Header */
            .lpp-header { position: sticky; top: 0; z-index: 40; background: rgba(255,255,255,.9); backdrop-filter: blur(10px); border-bottom: 1px solid color-mix(in srgb, var(--lpp-color) 12%, #fff); transition: box-shadow .2s ease; }
            .lpp-header.is-scrolled { box-shadow: 0 6px 24px rgba(31,36,48,.08); }
            .lpp-header-inner { max-width: 110rem; margin: 0 auto; padding: .85rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .lpp-brand { display: inline-flex; align-items: center; gap: .6rem; text-decoration: none; }
            .lpp-brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 999px; background: var(--lpp-color); color: #fff; font-weight: 800; font-size: 1.1rem; flex-shrink: 0; }
            .lpp-brand-logo { width: 40px; height: 40px; border-radius: 999px; object-fit: cover; flex-shrink: 0; border: 2px solid color-mix(in srgb, var(--lpp-color) 30%, #fff); }
            .lpp-brand-name { font-weight: 800; font-size: 1.02rem; color: #1f2430; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; letter-spacing: -.01em; }
            .lpp-header-nav { display: flex; align-items: center; gap: 1.5rem; }
            .lpp-header-link { display: none; font-size: .82rem; font-weight: 700; color: #5b6270; text-decoration: none; padding: .4rem .1rem; }
            .lpp-header-link:hover { color: var(--lpp-color); }
            .lpp-header-contact { display: none; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 700; color: #5b6270; text-decoration: none; }
            .lpp-header-cta { display: inline-flex; align-items: center; gap: .4rem; padding: .6rem 1.3rem; border-radius: 9999px; background: var(--lpp-color); color: #fff; font-size: .82rem; font-weight: 800; text-decoration: none; white-space: nowrap; box-shadow: 0 6px 16px color-mix(in srgb, var(--lpp-color) 40%, transparent); }
            .lpp-header-cta:hover { transform: scale(1.06) rotate(-2deg); box-shadow: 0 10px 22px color-mix(in srgb, var(--lpp-color) 50%, transparent); }
            @media (min-width: 640px) { .lpp-header-contact { display: inline-flex; } }
            @media (min-width: 900px) { .lpp-header-link { display: inline-flex; } }

            /* Buttons — mọi nút đều dạng viên thuốc bo tròn tuyệt đối */
            .lpp-btn-primary { display: inline-flex; align-items: center; gap: .8rem; padding: .5rem .5rem .5rem 1.6rem; border-radius: 9999px; background: var(--lpp-color); color: #fff; font-weight: 800; font-size: .88rem; text-decoration: none; box-shadow: 0 10px 24px color-mix(in srgb, var(--lpp-color) 38%, transparent); }
            .lpp-btn-primary:hover { transform: scale(1.05) rotate(-1deg); box-shadow: 0 14px 30px color-mix(in srgb, var(--lpp-color) 48%, transparent); }
            .lpp-btn-primary-icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 9999px; background: rgba(255,255,255,.25); color: #fff; transition: transform .2s ease; }
            .lpp-btn-primary:hover .lpp-btn-primary-icon { transform: translateX(3px) rotate(15deg); }
            .lpp-btn-ghost { display: inline-flex; align-items: center; gap: .65rem; padding: .5rem 1.4rem .5rem .5rem; border-radius: 9999px; background: #fff; border: 1.5px solid color-mix(in srgb, var(--lpp-color) 30%, #fff); color: #1f2430; font-weight: 800; font-size: .85rem; text-decoration: none; }
            .lpp-btn-ghost:hover { transform: scale(1.05) rotate(1deg); border-color: var(--lpp-color); }
            .lpp-btn-ghost-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9999px; background: var(--lpp-color); color: #fff; }
            .lpp-btn-dark { display: inline-flex; align-items: center; padding: .8rem 1.9rem; border-radius: 9999px; background: #1f2430; color: #fff; font-weight: 800; font-size: .85rem; text-decoration: none; }
            .lpp-btn-dark:hover { transform: scale(1.05) rotate(-1deg); }
            .lpp-btn-outline { display: inline-flex; align-items: center; justify-content: center; margin-top: 1.5rem; padding: .7rem 1.3rem; border-radius: 9999px; border: 2px solid #1f2430; color: #1f2430; font-weight: 800; font-size: .82rem; text-decoration: none; }
            .lpp-btn-outline:hover { transform: scale(1.04) rotate(1deg); background: #1f2430; color: #fff; }
            .lpp-btn-white { display: inline-flex; align-items: center; margin-top: 1.75rem; padding: .85rem 2rem; border-radius: 9999px; background: #fff; color: var(--lpp-color-heavy); font-weight: 800; font-size: .9rem; text-decoration: none; box-shadow: 0 12px 28px rgba(0,0,0,.18); }
            .lpp-btn-white:hover { transform: scale(1.06) rotate(-2deg); }

            /* Hero */
            .lpp-hero { padding: 2.5rem 1rem 3rem; background: #fff; }
            .lpp-hero-inner { position: relative; z-index: 1; max-width: 100rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 2.5rem; align-items: center; }
            .lpp-hero-title { margin: 0; font-size: 2rem; font-weight: 800; line-height: 1.2; letter-spacing: -.01em; color: #1f2430; }
            .lpp-hero-desc { margin: 1.1rem 0 0; font-size: .92rem; line-height: 1.75; color: #5b6270; max-width: 34rem; }
            .lpp-hero-actions { margin-top: 1.75rem; display: flex; flex-wrap: wrap; gap: .9rem; }
            .lpp-hero-rating { margin-top: 1.75rem; display: flex; align-items: center; gap: .65rem; }
            .lpp-hero-stars { display: flex; gap: .15rem; color: var(--lpp-color); }
            .lpp-hero-stars svg.is-dim { color: #dfe3e8; }
            .lpp-hero-rating span { font-size: .8rem; font-weight: 700; color: #5b6270; }

            .lpp-hero-media { position: relative; }
            .lpp-hero-media-main { position: relative; aspect-ratio: 4/3; border-radius: 2.25rem; overflow: hidden; background: color-mix(in srgb, var(--lpp-color) 10%, #f4f5f7); display: flex; align-items: center; justify-content: center; box-shadow: 0 24px 48px -12px color-mix(in srgb, var(--lpp-color) 30%, rgba(31,36,48,.2)); }
            .lpp-hero-media-main img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lpp-media-placeholder { display: flex; align-items: center; justify-content: center; color: color-mix(in srgb, var(--lpp-color) 45%, #9aa1ad); }
            .lpp-play-btn { position: relative; z-index: 2; width: 64px; height: 64px; border-radius: 9999px; background: #fff; border: none; cursor: pointer; color: var(--lpp-color); display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 10px 26px rgba(0,0,0,.25); }
            .lpp-play-btn:hover { transform: scale(1.12); }
            .lpp-hero-video-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
            .lpp-hero-media-float { position: absolute; left: -1.25rem; bottom: -1.75rem; width: 42%; aspect-ratio: 1/1; border-radius: 1.75rem; overflow: hidden; border: 5px solid #fff; box-shadow: 0 16px 32px rgba(31,36,48,.2); transform: rotate(-4deg); transition: transform .3s cubic-bezier(.34,1.56,.64,1); }
            .lpp-hero-media-float:hover { transform: rotate(0deg) scale(1.04); }
            .lpp-hero-media-float img { width: 100%; height: 100%; object-fit: cover; }
            .lpp-hero-badge { position: absolute; top: -1rem; right: -.5rem; display: flex; align-items: center; gap: .5rem; padding: .6rem 1.1rem; border-radius: 9999px; background: #fff; box-shadow: 0 10px 24px rgba(31,36,48,.16); font-size: .76rem; font-weight: 800; color: #1f2430; transform: rotate(4deg); }
            .lpp-hero-badge-icon { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 9999px; background: var(--lpp-color); color: #fff; }
            @media (min-width: 1024px) {
                .lpp-hero { padding: 3.5rem 1.5rem 6rem; }
                .lpp-hero-inner { grid-template-columns: 1.05fr .95fr; gap: 3.5rem; }
                .lpp-hero-title { font-size: 2.9rem; }
            }

            /* Features */
            .lpp-features { padding: 4rem 1rem; background: #fff; }
            .lpp-features-inner { max-width: 96rem; margin: 0 auto; }
            .lpp-features-grid { margin-top: 2.25rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lpp-feature { flex: 1 1 250px; max-width: 280px; background: #f9fafb; border-radius: 1.75rem; padding: 2rem 1.6rem; text-align: center; transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease, background-color .3s ease; }
            .lpp-feature:hover { transform: translateY(-6px) rotate(-1.5deg) scale(1.02); box-shadow: 0 20px 36px rgba(31,36,48,.1); background: #fff; }
            .lpp-tilt-b:hover { transform: translateY(-6px) rotate(1.5deg) scale(1.02); }
            .lpp-feature-icon { display: inline-flex; align-items: center; justify-content: center; width: 58px; height: 58px; border-radius: 9999px; background: color-mix(in srgb, var(--lpp-color) 16%, #fff); color: var(--lpp-color); }
            .lpp-feature h3 { margin-top: 1.1rem; font-size: .96rem; font-weight: 800; color: #1f2430; }
            .lpp-feature p { margin-top: .5rem; font-size: .82rem; color: #6b7280; line-height: 1.65; }

            /* About */
            .lpp-about { padding: 4rem 1rem; overflow: hidden; }
            .lpp-about-inner { position: relative; z-index: 1; max-width: 54rem; margin: 0 auto; text-align: center; }
            .lpp-about-text { margin-top: 1rem; font-size: .92rem; line-height: 1.8; color: #4b5563; white-space: pre-line; }

            /* Services */
            .lpp-services { padding: 4rem 1rem; background: color-mix(in srgb, var(--lpp-color) 6%, #fff); }
            .lpp-services-inner { max-width: 96rem; margin: 0 auto; text-align: center; }
            .lpp-services-grid { margin-top: 2.25rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.4rem; text-align: left; }
            .lpp-service-card { flex: 1 1 270px; max-width: 320px; background: #fff; border-radius: 1.75rem; overflow: hidden; box-shadow: 0 10px 24px rgba(31,36,48,.06); transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease; }
            .lpp-service-card:hover { transform: translateY(-6px) rotate(-1deg) scale(1.02); box-shadow: 0 20px 40px rgba(31,36,48,.12); }
            .lpp-service-media { height: 155px; background: color-mix(in srgb, var(--lpp-color) 12%, #f3f4f6); display: flex; align-items: center; justify-content: center; overflow: hidden; color: color-mix(in srgb, var(--lpp-color) 45%, #9aa1ad); }
            .lpp-service-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lpp-service-card:hover .lpp-service-media img { transform: scale(1.08) rotate(1deg); }
            .lpp-service-body { padding: 1.4rem 1.5rem; }
            .lpp-service-body h3 { font-size: .95rem; font-weight: 800; color: #1f2430; }
            .lpp-service-body p { margin-top: .45rem; font-size: .81rem; color: #6b7280; line-height: 1.6; }
            .lpp-section-cta { margin-top: 2.5rem; }

            /* Pricing */
            .lpp-pricing { padding: 4rem 1rem; }
            .lpp-pricing-inner { max-width: 96rem; margin: 0 auto; text-align: center; }
            .lpp-pricing-grid { margin-top: 2.5rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.6rem; }
            .lpp-pricing-card { position: relative; flex: 1 1 300px; max-width: 340px; background: #f9fafb; border-radius: 2rem; padding: 2.5rem 1.85rem; text-align: left; transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease; }
            .lpp-pricing-card:not(.is-highlighted):hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 20px 36px rgba(31,36,48,.1); }
            .lpp-pricing-card.is-highlighted { background: var(--lpp-color); color: #fff; transform: scale(1.04); box-shadow: 0 24px 48px color-mix(in srgb, var(--lpp-color) 40%, transparent); }
            .lpp-pricing-card.is-highlighted:hover { transform: scale(1.06) rotate(-.5deg); }
            .lpp-pricing-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .8rem; border-radius: 9999px; background: rgba(255,255,255,.25); color: #fff; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 1rem; }
            .lpp-pricing-card h3 { font-size: 1.05rem; font-weight: 800; color: inherit; }
            .lpp-pricing-amount { margin-top: .55rem; font-size: 1.7rem; font-weight: 800; color: inherit; }
            .lpp-pricing-card:not(.is-highlighted) .lpp-pricing-amount { color: var(--lpp-color); }
            .lpp-pricing-amount span { font-size: .78rem; font-weight: 600; opacity: .75; }
            .lpp-pricing-features { margin-top: 1.35rem; display: flex; flex-direction: column; gap: .6rem; }
            .lpp-pricing-features li { display: flex; align-items: flex-start; gap: .5rem; font-size: .81rem; opacity: .92; }
            .lpp-pricing-card:not(.is-highlighted) .lpp-pricing-features li svg { color: var(--lpp-color); }
            .lpp-pricing-features li svg { margin-top: .1rem; flex-shrink: 0; }
            .lpp-pricing-card .lpp-btn-primary, .lpp-pricing-card .lpp-btn-outline { display: flex; width: 100%; justify-content: center; margin-top: 1.6rem; }
            .lpp-pricing-card.is-highlighted .lpp-btn-primary { background: #fff; color: var(--lpp-color-heavy); box-shadow: none; }
            .lpp-pricing-card.is-highlighted .lpp-btn-primary .lpp-btn-primary-icon { background: color-mix(in srgb, var(--lpp-color) 18%, #fff); color: var(--lpp-color-heavy); }
            .lpp-pricing-card.is-highlighted .lpp-btn-outline { border-color: rgba(255,255,255,.6); color: #fff; }

            /* Stats */
            .lpp-stats { overflow: hidden; padding: 4.5rem 1rem; background: color-mix(in srgb, var(--lpp-color) 8%, #fff); }
            .lpp-stats-inner { position: relative; z-index: 1; max-width: 96rem; margin: 0 auto; display: flex; flex-direction: column; gap: 2.25rem; }
            .lpp-stats-text h2 { font-size: 1.5rem; font-weight: 800; color: #1f2430; }
            .lpp-stats-text p { margin-top: .55rem; font-size: .87rem; color: #5b6270; max-width: 36rem; }
            .lpp-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
            .lpp-stat { background: #fff; border-radius: 1.5rem; padding: 1.4rem 1.2rem; box-shadow: 0 10px 22px rgba(31,36,48,.06); transition: transform .3s cubic-bezier(.34,1.56,.64,1); }
            .lpp-stat:hover { transform: translateY(-4px) scale(1.03); }
            .lpp-stat-num { display: block; font-size: 2rem; font-weight: 800; color: var(--lpp-color); }
            .lpp-stat-label { display: block; margin-top: .3rem; font-size: .78rem; font-weight: 700; color: #6b7280; }
            @media (min-width: 768px) { .lpp-stats-inner { flex-direction: row; align-items: center; justify-content: space-between; } .lpp-stats-grid { grid-template-columns: repeat(4, 1fr); flex: 1; margin-left: 2rem; } }

            /* Testimonials */
            .lpp-testimonials { padding: 4.5rem 1rem; background: #fff; }
            .lpp-testimonials-inner { max-width: 96rem; margin: 0 auto; text-align: center; }
            .lpp-testimonials-grid { margin-top: 2.5rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.4rem; text-align: left; }
            .lpp-testimonial-card { flex: 1 1 280px; max-width: 340px; background: #f9fafb; border-radius: 1.75rem; padding: 1.9rem 1.7rem; transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease; }
            .lpp-testimonial-card:hover { transform: translateY(-6px) rotate(-1deg); box-shadow: 0 20px 36px rgba(31,36,48,.1); }
            .lpp-testimonial-quote-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 9999px; background: color-mix(in srgb, var(--lpp-color) 16%, #fff); color: var(--lpp-color); }
            .lpp-testimonial-card p { margin-top: 1rem; font-size: .88rem; color: #374151; line-height: 1.7; }
            .lpp-testimonial-author { margin-top: 1.25rem; display: flex; align-items: center; gap: .75rem; }
            .lpp-testimonial-author img { width: 44px; height: 44px; border-radius: 9999px; object-fit: cover; }
            .lpp-testimonial-avatar { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 9999px; background: var(--lpp-color); color: #fff; font-weight: 800; }
            .lpp-testimonial-author div { display: flex; flex-direction: column; }
            .lpp-testimonial-author strong { font-size: .84rem; color: #1f2430; }
            .lpp-testimonial-author span { font-size: .74rem; color: #9ca3af; }

            /* Venues */
            .lpp-venues { padding: 4rem 1rem 4.5rem; background: color-mix(in srgb, var(--lpp-color) 5%, #fff); }
            .lpp-venues-inner { max-width: 110rem; margin: 0 auto; }
            .lpp-section-title { font-size: 1.55rem; font-weight: 800; color: #1f2430; text-align: center; letter-spacing: -.01em; }
            .lpp-section-sub { margin-top: .4rem; font-size: .87rem; color: #6b7280; text-align: center; }
            .lpp-venues-grid { margin-top: 2rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.1rem; }
            .lpp-venues-grid > * { flex: 1 1 220px; max-width: 280px; }
            {{-- Bo tròn + hiệu ứng nảy đè lên card sân dùng chung (đã có style riêng của component)
                 để card khớp ngôn ngữ "playful" mà không phải sửa component dùng chung. --}}
            .lpp-venues-grid :deep(.venue-card), .lpp-venues-grid > .venue-card { border-radius: 1.5rem !important; transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease !important; }
            .lpp-venues-grid > .venue-card:hover { transform: translateY(-6px) scale(1.02); }
            .lpp-empty { padding: 4rem 0; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 1rem; }
            .lpp-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 9999px; background: color-mix(in srgb, var(--lpp-color) 14%, #fff); color: var(--lpp-color); }
            .lpp-empty p { font-size: .9rem; color: #6b7280; max-width: 24rem; }

            /* News */
            .lpp-news { padding: 4.5rem 1rem; background: color-mix(in srgb, var(--lpp-color) 6%, #fff); }
            .lpp-news-inner { max-width: 96rem; margin: 0 auto; text-align: center; }
            .lpp-news-grid { margin-top: 2.25rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 1.3rem; text-align: left; }
            .lpp-news-card { flex: 1 1 250px; max-width: 290px; display: block; background: #fff; border-radius: 1.75rem; overflow: hidden; text-decoration: none; box-shadow: 0 10px 24px rgba(31,36,48,.06); transition: transform .3s cubic-bezier(.34,1.56,.64,1), box-shadow .3s ease; }
            .lpp-news-card:hover { transform: translateY(-6px) rotate(1deg) scale(1.02); box-shadow: 0 20px 40px rgba(31,36,48,.12); }
            .lpp-news-media { height: 155px; background: color-mix(in srgb, var(--lpp-color) 12%, #f3f4f6); display: flex; align-items: center; justify-content: center; overflow: hidden; color: color-mix(in srgb, var(--lpp-color) 45%, #9aa1ad); }
            .lpp-news-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lpp-news-card:hover .lpp-news-media img { transform: scale(1.08); }
            .lpp-news-body { padding: 1.35rem 1.5rem; }
            .lpp-news-body h3 { font-size: .9rem; font-weight: 800; color: #1f2430; line-height: 1.45; }
            .lpp-news-body p { margin-top: .45rem; font-size: .79rem; color: #6b7280; line-height: 1.55; }

            /* FAQ */
            .lpp-faq { padding: 4.5rem 1rem; background: #fff; }
            .lpp-faq-inner { max-width: 96rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 2.25rem; align-items: center; }
            .lpp-faq-media { position: relative; min-height: 15rem; border-radius: 2.25rem; overflow: hidden; background: color-mix(in srgb, var(--lpp-color) 14%, #f4f5f7); display: flex; align-items: center; justify-content: center; color: color-mix(in srgb, var(--lpp-color) 50%, #9aa1ad); }
            .lpp-faq-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpp-faq-media-badge { position: absolute; left: 1.25rem; bottom: 1.25rem; display: flex; align-items: center; gap: .5rem; padding: .6rem 1rem; border-radius: 9999px; background: #fff; box-shadow: 0 10px 22px rgba(31,36,48,.16); font-size: .76rem; font-weight: 800; color: #1f2430; transform: rotate(-2deg); }
            .lpp-faq-media-badge svg { color: var(--lpp-color); }
            .lpp-faq-list { margin-top: 2rem; display: flex; flex-direction: column; gap: .85rem; }
            @media (min-width: 900px) { .lpp-faq-inner { grid-template-columns: .85fr 1.15fr; } .lpp-faq-media { height: 100%; min-height: 26rem; } }
            .lpp-faq-item { background: #f9fafb; border-radius: 1.5rem; overflow: hidden; transition: background-color .2s ease; }
            .lpp-faq-item:has(.lpp-faq-answer[style*="block"]) { background: color-mix(in srgb, var(--lpp-color) 8%, #f9fafb); }
            .lpp-faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.2rem 1.5rem; background: none; border: none; text-align: left; font-size: .89rem; font-weight: 700; color: #1f2430; cursor: pointer; }
            .lpp-faq-question-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 9999px; background: color-mix(in srgb, var(--lpp-color) 16%, #fff); color: var(--lpp-color); flex-shrink: 0; transition: transform .25s ease; }
            .lpp-faq-answer { padding: 0 1.5rem 1.3rem; font-size: .84rem; color: #6b7280; line-height: 1.7; }

            /* CTA */
            .lpp-cta { position: relative; overflow: hidden; padding: 5rem 1rem; text-align: center; background: linear-gradient(135deg, var(--lpp-color) 0%, var(--lpp-color-heavy) 100%); border-radius: 3rem 3rem 0 0; margin-top: -1px; }
            .lpp-cta-inner { position: relative; z-index: 1; max-width: 42rem; margin: 0 auto; }
            .lpp-cta-badge { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 9999px; background: rgba(255,255,255,.2); color: #fff; margin-bottom: 1.25rem; }
            .lpp-cta h2 { font-size: 1.75rem; font-weight: 800; color: #fff; letter-spacing: -.01em; }
            .lpp-cta p { margin-top: .65rem; font-size: .92rem; color: rgba(255,255,255,.9); }

            /* Footer */
            .lpp-footer { background: #1f2430; color: #d1d5db; padding: 3.75rem 1rem 1.75rem; }
            .lpp-footer-inner { max-width: 110rem; margin: 0 auto; }
            .lpp-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; text-align: center; }
            .lpp-footer-col { display: flex; flex-direction: column; align-items: center; gap: .7rem; }
            .lpp-footer-col h4 { font-size: .82rem; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .35rem; }
            .lpp-footer-col a { font-size: .83rem; color: #9ca3af; text-decoration: none; }
            .lpp-footer-col a:hover { color: var(--lpp-color); }
            .lpp-footer-col-brand { max-width: 24rem; margin: 0 auto; }
            .lpp-footer-brand { display: flex; align-items: center; gap: .6rem; }
            .lpp-footer-brand .lpp-brand-name { color: #fff; }
            .lpp-footer-col-brand p { font-size: .83rem; color: #9ca3af; line-height: 1.6; }
            .lpp-footer-social { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 9999px; background: rgba(255,255,255,.08); color: #d1d5db; }
            .lpp-footer-social:hover { background: var(--lpp-color); color: #fff; transform: scale(1.1) rotate(-6deg); }
            .lpp-footer-item { display: inline-flex; align-items: center; gap: .4rem; font-size: .83rem; color: #9ca3af; text-decoration: none; }
            .lpp-footer-bottom { margin-top: 2.5rem; text-align: center; font-size: .75rem; color: #6b7280; border-top: 1px solid rgba(255,255,255,.08); padding-top: 1.35rem; }
            @media (min-width: 768px) { .lpp-footer-grid { grid-template-columns: 1.4fr 1fr 1fr; text-align: left; } .lpp-footer-col { align-items: flex-start; } .lpp-footer-col-brand { margin: 0; } }
        </style>
    @endonce
@endsection
