@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Trang landing page "Neon" — 1 trong 4 giao diện landing cho đối tác theo subdomain (biến thể
         thứ 4 sau bento/editorial/minimal/split, dùng chung 1 controller
         BladeThemeV1Controller::partnerLandingPage() và CÙNG bộ biến — chỉ khác class scope + CSS).
         Hướng thiết kế: đấu trường thể thao ban đêm / esports — nền GẦN NHƯ ĐEN xuyên suốt (không
         chỉ hero), khối kính mờ nổi trên nền tối (backdrop-filter blur + nền bán trong suốt), viền
         gradient phát sáng mảnh quanh nút/thẻ/đường phân cách, lưới scanline CSS thuần trang trí nền
         (repeating-linear-gradient độ mờ thấp, KHÔNG dùng ảnh/thư viện ngoài). Mọi màu neon đều suy
         ra từ 3 biến $landingColor/...Heavy/...Light qua color-mix(), không hardcode màu cố định —
         vì mỗi đối tác tự chọn màu chủ đạo riêng (tab "Trang landing" của PartnerResource). --}}
    {{-- BUG đã sửa: toàn bộ CSS bên dưới dùng biến "--lpn-neon-color..." (tiền tố lpn- khớp với các
         class .lpn-*), nhưng ở đây trước đó lại khai báo biến với tên "--lp-neon-color..." (thiếu chữ
         "n") — khớp SAI TÊN nên mọi var(--lpn-neon-color...) không có giá trị, khiến toàn bộ màu nhấn/
         viền phát sáng/icon biến mất, chữ chìm vào nền tối. Sửa lại đúng tên biến. --}}
    <div class="partner-landing-neon-scope" style="
        --lpn-neon-color: {{ $landingColor }};
        --lpn-neon-color-heavy: {{ $landingColorHeavy }};
        --lpn-neon-color-light: {{ $landingColorLight }};
    ">
        <div class="lpn-grid-overlay" aria-hidden="true"></div>

        {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
        <header class="lpn-header">
            <div class="lpn-header-inner">
                <a href="#" class="lpn-brand">
                    @if ($partnerLogoUrl)
                        <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpn-brand-logo">
                    @else
                        <span class="lpn-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                    @endif
                    <span class="lpn-brand-name">{{ $partner->name }}</span>
                </a>

                <nav class="lpn-header-nav">
                    @if ($description)
                        <a href="#lp-about" class="lpn-header-link">Giới thiệu</a>
                    @endif
                    <a href="#lp-services" class="lpn-header-link">Dịch vụ</a>
                    @if ($pricingPlans->isNotEmpty())
                        <a href="#lp-pricing" class="lpn-header-link">Bảng giá</a>
                    @endif
                    <a href="#lp-contact" class="lpn-header-link">Liên hệ</a>
                    @if ($partner->phone)
                        <a href="tel:{{ $partner->phone }}" class="lpn-header-contact">
                            <x-heroicon-o-phone class="h-4 w-4" />
                            <span>{{ $partner->phone }}</span>
                        </a>
                    @endif
                    <a href="#lp-venues" class="lpn-header-cta">Đặt sân ngay</a>
                </nav>
            </div>
        </header>

        {{-- ── HERO ───────────────────────────────────────────────────────────────
             Sân đấu ban đêm: tiêu đề lớn bên trái + thẻ đánh giá kính mờ, ảnh phụ (heroSecondaryImageUrl)
             làm nền mờ phía sau khối chữ, ảnh chính (heroImageUrl) hoặc video (heroYoutubeId) làm ô
             "sân đấu" phát sáng bên phải. Thiếu ảnh nào thì hiện khung trống, không vỡ layout. --}}
        <section class="lpn-hero">
            @if ($heroSecondaryImageUrl)
                <div class="lpn-hero-bg" style="background-image: url('{{ $heroSecondaryImageUrl }}');"></div>
            @endif
            <div class="lpn-hero-scrim"></div>

            <div class="lpn-hero-inner">
                <div class="lpn-hero-left lpn-reveal">
                    <span class="lpn-eyebrow">
                        <span class="lpn-eyebrow-dot"></span>
                        Đấu trường thể thao trực tuyến
                    </span>
                    <h1 class="lpn-hero-title">{{ $tagline }}</h1>
                    <p class="lpn-hero-desc">{{ $description ?: ('Đặt sân trực tuyến tại ' . $partner->name . ' — chọn chi nhánh, xem lịch trống theo thời gian thực và vào trận chỉ trong vài bước.') }}</p>

                    <div class="lpn-hero-actions">
                        <a href="#lp-venues" class="lpn-btn-primary">
                            <span>Đặt sân ngay</span>
                            <x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lpn-btn-ghost">
                                <x-heroicon-o-phone class="h-4 w-4" />
                                <span>{{ $partner->phone }}</span>
                            </a>
                        @endif
                    </div>

                    <div class="lpn-hero-rating">
                        @if ($avgRating)
                            <div class="lpn-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 {{ $i <= round($avgRating) ? '' : 'is-dim' }}" />
                                @endfor
                            </div>
                            <span>{{ number_format($avgRating, 1) }} / 5 · Đánh giá thật từ chi nhánh</span>
                        @else
                            <div class="lpn-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 is-dim" />
                                @endfor
                            </div>
                            <span>Chưa có đánh giá</span>
                        @endif
                    </div>
                </div>

                <div class="lpn-hero-right lpn-reveal" @if ($heroYoutubeId) x-data="{ playing: false }" @endif>
                    <div class="lpn-hero-media">
                        @if ($heroImageUrl)
                            <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="lazy" @if ($heroYoutubeId) x-show="!playing" @endif>
                        @elseif ($heroYoutubeId)
                            <img src="https://i3.ytimg.com/vi/{{ $heroYoutubeId }}/hqdefault.jpg" alt="{{ $partner->name }}" x-show="!playing">
                        @else
                            <div class="lpn-hero-media-empty"><x-heroicon-o-photo class="h-10 w-10" /></div>
                        @endif

                        @if ($heroYoutubeId)
                            <div class="lpn-hero-video-overlay" x-show="!playing">
                                {{-- Nhúng trực tiếp iframe SAU khi bấm Play (autoplay=1) — đúng pattern "lite
                                     youtube embed", không tải sẵn video nặng ngay khi vào trang. --}}
                                <button type="button" class="lpn-play-btn" @click="playing = true" aria-label="Xem video giới thiệu">
                                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6"><path d="M8 5v14l11-7z" /></svg>
                                </button>
                            </div>
                            <template x-if="playing">
                                <iframe
                                    class="lpn-hero-video-frame"
                                    src="https://www.youtube-nocookie.com/embed/{{ $heroYoutubeId }}?autoplay=1&rel=0"
                                    title="Video giới thiệu {{ $partner->name }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </template>
                        @endif
                    </div>

                    <div class="lpn-hero-stat-strip">
                        @forelse (array_slice($stats, 0, 3) as $stat)
                            <div class="lpn-hero-stat">
                                <strong>{{ $stat['number'] }}</strong>
                                <span>{{ $stat['label'] }}</span>
                            </div>
                        @empty
                            <div class="lpn-hero-stat"><strong>{{ $venues->count() }}</strong><span>Chi nhánh</span></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        {{-- ── ĐIỂM NỔI BẬT ───────────────────────────────────────────────────── --}}
        <section class="lpn-section">
            <div class="lpn-container lpn-reveal">
                <div class="lpn-section-head">
                    <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Vì sao chọn chúng tôi</span>
                    <h2 class="lpn-section-title">Điều thú vị về {{ $partner->name }}</h2>
                </div>
                <div class="lpn-features-grid">
                    @foreach ($features as $feature)
                        <div class="lpn-glass-card lpn-feature">
                            <span class="lpn-feature-icon">
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
            <section id="lp-about" class="lpn-section">
                <div class="lpn-container-narrow lpn-reveal lpn-about">
                    <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Câu chuyện của chúng tôi</span>
                    <h2 class="lpn-section-title">Giới thiệu về {{ $partner->name }}</h2>
                    <p class="lpn-about-text">{{ $description }}</p>
                </div>
            </section>
        @endif

        {{-- ── DỊCH VỤ ────────────────────────────────────────────────────────── --}}
        <section id="lp-services" class="lpn-section lpn-section-alt">
            <div class="lpn-container lpn-reveal">
                <div class="lpn-section-head">
                    <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Dịch vụ</span>
                    <h2 class="lpn-section-title">Cung cấp dịch vụ thuê sân tốt nhất</h2>
                </div>
                <div class="lpn-services-grid">
                    @foreach ($services as $service)
                        <div class="lpn-glass-card lpn-service-card">
                            <div class="lpn-service-media">
                                @if (! empty($service['image']))
                                    <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy">
                                @else
                                    <x-heroicon-o-photo class="h-8 w-8" />
                                @endif
                            </div>
                            <div class="lpn-service-body">
                                <h3>{{ $service['title'] }}</h3>
                                @if (! empty($service['description']))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="lpn-section-cta">
                    <a href="#lp-venues" class="lpn-btn-primary"><span>Đặt sân ngay</span><x-heroicon-o-arrow-right class="h-4 w-4" /></a>
                </div>
            </div>
        </section>

        {{-- ── BẢNG GIÁ (chỉ hiện khi admin có cấu hình landing_pricing_plans) ──── --}}
        @if ($pricingPlans->isNotEmpty())
            <section id="lp-pricing" class="lpn-section">
                <div class="lpn-container lpn-reveal">
                    <div class="lpn-section-head">
                        <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Bảng giá</span>
                        <h2 class="lpn-section-title">Chọn gói phù hợp với bạn</h2>
                    </div>
                    <div class="lpn-pricing-grid">
                        @foreach ($pricingPlans as $plan)
                            <div class="lpn-glass-card lpn-pricing-card {{ ! empty($plan['highlighted']) ? 'is-highlighted' : '' }}">
                                @if (! empty($plan['highlighted']))
                                    <span class="lpn-pricing-badge">Phổ biến nhất</span>
                                @endif
                                <h3>{{ $plan['name'] }}</h3>
                                <div class="lpn-pricing-amount">
                                    {{ $plan['price'] }}
                                    @if (! empty($plan['unit']))
                                        <span>{{ $plan['unit'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($plan['features']))
                                    <ul class="lpn-pricing-features">
                                        @foreach ($plan['features'] as $line)
                                            <li><x-heroicon-s-check-circle class="h-4 w-4" />{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#lp-venues" class="{{ ! empty($plan['highlighted']) ? 'lpn-btn-primary' : 'lpn-btn-ghost' }} lpn-pricing-btn">Đăng ký ngay</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── THÀNH TỰU ──────────────────────────────────────────────────────── --}}
        <section class="lpn-section lpn-stats">
            <div class="lpn-container lpn-reveal lpn-stats-inner">
                <div class="lpn-stats-text">
                    <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Thành tựu</span>
                    <h2 class="lpn-section-title">Điều thú vị về {{ $partner->name }}</h2>
                    <p>Đồng hành cùng hàng nghìn khách hàng yêu thể thao, {{ $partner->name }} không ngừng nâng cao chất lượng dịch vụ.</p>
                </div>
                <div class="lpn-stats-grid">
                    @foreach ($stats as $stat)
                        <div class="lpn-glass-card lpn-stat">
                            <span class="lpn-stat-num">{{ $stat['number'] }}</span>
                            <span class="lpn-stat-label">{{ $stat['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── ĐÁNH GIÁ (chỉ hiện khi admin có cấu hình) ─────────────────────────── --}}
        @if (! empty($testimonials))
            <section class="lpn-section" x-data="{ active: 0, total: {{ count($testimonials) }} }">
                <div class="lpn-container-narrow lpn-reveal">
                    <div class="lpn-section-head">
                        <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Khách hàng nói gì</span>
                        <h2 class="lpn-section-title">Đánh giá từ khách hàng</h2>
                    </div>

                    @foreach ($testimonials as $i => $t)
                        <div class="lpn-glass-card lpn-testimonial" x-show="active === {{ $i }}" x-cloak>
                            <div class="lpn-testimonial-media">
                                @if (! empty($t['image']))
                                    <img src="{{ $t['image'] }}" alt="{{ $t['name'] }}">
                                @else
                                    <div class="lpn-testimonial-avatar">{{ mb_substr($t['name'], 0, 1) }}</div>
                                @endif
                            </div>
                            <div class="lpn-testimonial-body">
                                <p>"{{ $t['quote'] }}"</p>
                                <div class="lpn-testimonial-author">
                                    <strong>{{ $t['name'] }}</strong>
                                    @if (! empty($t['role']))
                                        <span>{{ $t['role'] }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if (count($testimonials) > 1)
                        <div class="lpn-dots">
                            @foreach ($testimonials as $i => $t)
                                <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'is-active' : ''" aria-label="Đánh giá {{ $i + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- ── DANH SÁCH CHI NHÁNH ───────────────────────────────────────────────── --}}
        <section id="lp-venues" class="lpn-section lpn-section-alt">
            <div class="lpn-container lpn-reveal">
                @if ($venues->isNotEmpty())
                    <div class="lpn-section-head">
                        <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Chi nhánh</span>
                        <h2 class="lpn-section-title">Chi nhánh của {{ $partner->name }}</h2>
                        <p class="lpn-section-sub">Chọn 1 chi nhánh để xem lịch trống và vào trận ngay.</p>
                    </div>

                    <div class="lpn-venues-grid">
                        @foreach ($venues as $venue)
                            <div class="lpn-venue-frame">
                                <x-bladethemev1::venues.card :venue="$venue" />
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="lpn-empty">{{ $partner->name }} hiện chưa có chi nhánh nào hiển thị.</p>
                @endif
            </div>
        </section>

        {{-- ── TIN TỨC / ƯU ĐÃI (chỉ hiện khi admin có cấu hình) ─────────────────── --}}
        @if (! empty($news))
            <section class="lpn-section">
                <div class="lpn-container lpn-reveal">
                    <div class="lpn-section-head">
                        <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Cập nhật</span>
                        <h2 class="lpn-section-title">Tin tức &amp; ưu đãi mới nhất</h2>
                    </div>
                    <div class="lpn-news-grid">
                        @foreach ($news as $item)
                            <a href="{{ $item['url'] ?: '#' }}" @if ($item['url']) target="_blank" rel="noopener" @endif class="lpn-glass-card lpn-news-card">
                                <div class="lpn-news-media">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                                    @else
                                        <x-heroicon-o-photo class="h-8 w-8" />
                                    @endif
                                </div>
                                <div class="lpn-news-body">
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
        <section class="lpn-section lpn-section-alt">
            <div class="lpn-container lpn-reveal lpn-faq-inner">
                <div class="lpn-faq-media">
                    @if ($faqImageUrl)
                        <img src="{{ $faqImageUrl }}" alt="Câu hỏi thường gặp" loading="lazy">
                    @else
                        <x-heroicon-o-chat-bubble-left-right class="h-12 w-12" />
                    @endif
                </div>
                <div class="lpn-faq-content">
                    <span class="lpn-eyebrow"><span class="lpn-eyebrow-dot"></span>Hỗ trợ</span>
                    <h2 class="lpn-section-title lpn-section-title-left">Các câu hỏi thường gặp</h2>
                    <div class="lpn-faq-list">
                        @foreach ($faqs as $i => $faq)
                            <div class="lpn-glass-card lpn-faq-item" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                                <button type="button" class="lpn-faq-question" @click="open = !open">
                                    <span>{{ $faq['question'] }}</span>
                                    <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 transition-transform" x-bind:style="open ? 'transform:rotate(180deg)' : ''" />
                                </button>
                                <div class="lpn-faq-answer" x-show="open" x-collapse x-cloak>{{ $faq['answer'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        {{-- ── CTA CUỐI TRANG ─────────────────────────────────────────────────── --}}
        <section class="lpn-cta">
            <div class="lpn-cta-inner">
                <h2>Sẵn sàng bước vào sân đấu?</h2>
                <p>Chọn chi nhánh phù hợp và đặt lịch chỉ trong vài phút.</p>
                <a href="#lp-venues" class="lpn-btn-primary lpn-btn-lg"><span>Đặt sân ngay</span><x-heroicon-o-arrow-right class="h-4 w-4" /></a>
            </div>
        </section>

        {{-- ── FOOTER ─────────────────────────────────────────────────────────── --}}
        <footer id="lp-contact" class="lpn-footer">
            <div class="lpn-container">
                <div class="lpn-footer-grid">
                    <div class="lpn-footer-col lpn-footer-col-brand">
                        <div class="lpn-footer-brand">
                            @if ($partnerLogoUrl)
                                <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpn-brand-logo">
                            @else
                                <span class="lpn-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                            @endif
                            <span class="lpn-brand-name">{{ $partner->name }}</span>
                        </div>
                        <p>{{ $description ? \Illuminate\Support\Str::limit($description, 140) : ('Đặt sân thể thao trực tuyến tại ' . $partner->name . ' — nhanh chóng, tiện lợi, xác nhận tức thì.') }}</p>
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" class="lpn-footer-social" aria-label="Facebook">
                                <x-heroicon-o-globe-alt class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    <div class="lpn-footer-col">
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

                    <div class="lpn-footer-col">
                        <h4>Liên hệ</h4>
                        @if ($partner->address)
                            <span class="lpn-footer-item"><x-heroicon-o-map-pin class="h-4 w-4" />{{ $partner->address }}</span>
                        @endif
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lpn-footer-item"><x-heroicon-o-phone class="h-4 w-4" />{{ $partner->phone }}</a>
                        @endif
                        @if ($partner->email)
                            <a href="mailto:{{ $partner->email }}" class="lpn-footer-item"><x-heroicon-o-envelope class="h-4 w-4" />{{ $partner->email }}</a>
                        @endif
                    </div>
                </div>

                <div class="lpn-footer-bottom">
                    © {{ now()->year }} {{ $partner->name }} — Cung cấp bởi {{ config('app.name') }}
                </div>
            </div>
        </footer>
    </div>

    @once
        <script>
            // Bóng đổ + tăng độ mờ header khi cuộn xuống, và hiệu ứng "fade lên khi cuộn tới" cho các
            // khối .lpn-reveal — thuần vanilla JS/IntersectionObserver, scope riêng biến cục bộ (không
            // đụng namespace của các mẫu landing khác trong cùng site).
            document.addEventListener('DOMContentLoaded', function () {
                var lpnHeader = document.querySelector('.lpn-header');
                if (lpnHeader) {
                    window.addEventListener('scroll', function () {
                        lpnHeader.classList.toggle('is-scrolled', window.scrollY > 8);
                    }, { passive: true });
                }

                var lpnRevealTargets = document.querySelectorAll('.lpn-reveal');
                if (lpnRevealTargets.length && 'IntersectionObserver' in window) {
                    var lpnObserver = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('is-visible');
                                lpnObserver.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
                    lpnRevealTargets.forEach(function (el) { lpnObserver.observe(el); });
                } else {
                    lpnRevealTargets.forEach(function (el) { el.classList.add('is-visible'); });
                }
            });
        </script>

        <style>
            /* ── NEON THEME — nền gần như đen xuyên suốt, kính mờ nổi, viền/glow phát sáng theo màu
               chủ đạo của đối tác. Toàn bộ class dùng tiền tố riêng .lpn-* + scope
               .partner-landing-neon-scope, không đụng .lp-*/.lped-*/.lpm-*/.lps-* của 3 mẫu kia. */
            {{-- BUG đã sửa (khiếu nại "toàn trang màu chìm, khó nhìn" — header/footer/hỗ trợ/khách
                 hàng nói gì đều bị): các biến CSS nền/chữ cố định (--lpn-bg, --lpn-text-dim...) khai
                 báo qua custom property ở đây không lên màu trên trình duyệt thật của đối tác (nền
                 tối lẫn chữ xám nhạt đều "biến mất", chỉ những gì có màu viết thẳng — không qua var()
                 — như background/color hard-code mới hiện đúng). Để chắc chắn không phụ thuộc vào
                 việc custom property có tính toán được hay không, bỏ hẳn các biến cố định này (chúng
                 KHÔNG phụ thuộc đối tác, luôn là 1 giá trị duy nhất) và viết thẳng giá trị vào từng
                 nơi sử dụng. CHỈ giữ lại 3 biến --lpn-neon-color/-heavy/-light (đặt qua style="" trực
                 tiếp trên thẻ, đã xác nhận hoạt động đúng — các nút/badge màu xanh vẫn lên đúng màu)
                 vì đây là màu do đối tác tự chọn, bắt buộc phải động. --}}
            .partner-landing-neon-scope {
                --color-primary: var(--lpn-neon-color);
                position: relative;
                background: #060608;
                color: #f4f4f6;
                font-family: inherit;
                -webkit-font-smoothing: antialiased;
                overflow: hidden;
            }
            .partner-landing-neon-scope * { scroll-behavior: smooth; }
            .partner-landing-neon-scope a, .partner-landing-neon-scope button { transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease, opacity .18s ease; }
            .partner-landing-neon-scope img { max-width: 100%; }

            {{-- Lưới scanline CSS thuần trang trí toàn trang — 2 lớp repeating-linear-gradient chồng
                 nhau (ngang + dọc) độ mờ rất thấp, mô phỏng bề mặt sân đấu LED ban đêm. Cố định theo
                 viewport (position: fixed) để không lặp lại/nặng máy khi trang dài, pointer-events:none
                 để không cản tương tác. --}}
            .lpn-grid-overlay {
                position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: .5;
                background-image:
                    repeating-linear-gradient(0deg, rgba(255,255,255,.035) 0px, rgba(255,255,255,.035) 1px, transparent 1px, transparent 42px),
                    repeating-linear-gradient(90deg, rgba(255,255,255,.035) 0px, rgba(255,255,255,.035) 1px, transparent 1px, transparent 42px),
                    radial-gradient(ellipse 60% 40% at 50% 0%, color-mix(in srgb, var(--lpn-neon-color) 16%, transparent) 0%, transparent 70%);
            }
            .partner-landing-neon-scope > * { position: relative; z-index: 1; }

            .lpn-container { max-width: 110rem; margin: 0 auto; padding: 0 1rem; }
            .lpn-container-narrow { max-width: 56rem; margin: 0 auto; padding: 0 1rem; text-align: center; }

            {{-- Nhãn "eyebrow" phát sáng nhẹ — chấm tròn + viền mảnh gradient màu chủ đạo, tương phản
                 với nền tối tốt hơn nhãn nền đặc như 2 mẫu khác. --}}
            .lpn-eyebrow { display: inline-flex; align-items: center; gap: .5rem; padding: .35rem .9rem; border-radius: 9999px; background: color-mix(in srgb, var(--lpn-neon-color) 12%, rgba(255,255,255,.04)); border: 1px solid color-mix(in srgb, var(--lpn-neon-color) 35%, transparent); color: color-mix(in srgb, var(--lpn-neon-color) 75%, #fff); font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .9rem; }
            .lpn-eyebrow-dot { width: 6px; height: 6px; border-radius: 9999px; background: var(--lpn-neon-color); box-shadow: 0 0 8px 1px var(--lpn-neon-color); }

            .lpn-reveal { opacity: 0; transform: translateY(26px); transition: opacity .7s ease, transform .7s ease; }
            .lpn-reveal.is-visible { opacity: 1; transform: translateY(0); }
            .no-js .lpn-reveal { opacity: 1; transform: none; }

            {{-- Thẻ "kính mờ nổi" dùng chung — nền bán trong suốt + backdrop-filter blur + viền
                 gradient phát sáng mảnh (kỹ thuật: nền gradient 2 lớp qua padding-box/border-box +
                 mask, cho hiệu ứng viền gradient thay vì border-color đặc). Glow khi hover mạnh hơn để
                 tạo cảm giác "đèn LED sáng lên khi chạm vào". --}}
            .lpn-glass-card {
                position: relative;
                background: linear-gradient(160deg, rgba(255,255,255,.045) 0%, rgba(255,255,255,.02) 100%);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                border-radius: 1.1rem;
                border: 1px solid rgba(255,255,255,.09);
                box-shadow: 0 0 0 1px rgba(0,0,0,.2), 0 18px 40px -12px rgba(0,0,0,.6);
                transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
            }
            .lpn-glass-card::before {
                content: ''; position: absolute; inset: 0; border-radius: inherit; padding: 1px;
                background: linear-gradient(135deg, color-mix(in srgb, var(--lpn-neon-color) 65%, transparent) 0%, transparent 35%, transparent 65%, color-mix(in srgb, var(--lpn-neon-color) 45%, transparent) 100%);
                -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
                -webkit-mask-composite: xor; mask-composite: exclude;
                pointer-events: none; opacity: .55;
            }
            .lpn-glass-card:hover { transform: translateY(-4px); border-color: color-mix(in srgb, var(--lpn-neon-color) 55%, rgba(255,255,255,.09)); box-shadow: 0 0 0 1px rgba(0,0,0,.2), 0 22px 48px -12px rgba(0,0,0,.7), 0 0 32px -6px color-mix(in srgb, var(--lpn-neon-color) 45%, transparent); }

            /* Buttons */
            .lpn-btn-primary { display: inline-flex; align-items: center; gap: .6rem; padding: .8rem 1.6rem; border-radius: 9999px; background: linear-gradient(135deg, var(--lpn-neon-color) 0%, var(--lpn-neon-color-heavy) 100%); color: #06060a; font-weight: 800; font-size: .85rem; text-decoration: none; box-shadow: 0 0 24px -4px color-mix(in srgb, var(--lpn-neon-color) 70%, transparent), 0 8px 20px rgba(0,0,0,.35); }
            .lpn-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 0 36px -2px color-mix(in srgb, var(--lpn-neon-color) 85%, transparent), 0 10px 24px rgba(0,0,0,.4); }
            .lpn-btn-lg { padding: 1rem 2.1rem; font-size: .92rem; }
            .lpn-btn-ghost { display: inline-flex; align-items: center; gap: .55rem; padding: .78rem 1.4rem; border-radius: 9999px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.09); color: #fff; font-weight: 700; font-size: .85rem; text-decoration: none; backdrop-filter: blur(8px); }
            .lpn-btn-ghost:hover { border-color: color-mix(in srgb, var(--lpn-neon-color) 55%, rgba(255,255,255,.09)); box-shadow: 0 0 20px -6px color-mix(in srgb, var(--lpn-neon-color) 60%, transparent); }

            /* Header */
            {{-- BUG đã sửa (khiếu nại "header/footer tối màu"): viền phân cách trước đây dùng
                 rgba(255,255,255,.09) (trắng mờ 9%) trên nền gần đen — gần như vô hình, khiến
                 header/footer trông như 1 khối đen phẳng, không có điểm nhấn. Trộn thêm màu chủ đạo
                 (đã làm sáng) vào viền, cùng kỹ thuật "glow divider" đã dùng ở .lpn-section::before,
                 để header/footer có đường viền phát sáng rõ, tách bạch khỏi nền trang. --}}
            .lpn-header { position: sticky; top: 0; z-index: 50; background: rgba(6,6,8,.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid color-mix(in srgb, var(--lpn-neon-color-heavy) 35%, rgba(255,255,255,.09)); transition: box-shadow .2s ease, background-color .2s ease; }
            .lpn-header.is-scrolled { background: rgba(6,6,8,.92); box-shadow: 0 4px 24px rgba(0,0,0,.5), 0 0 20px -8px color-mix(in srgb, var(--lpn-neon-color) 40%, transparent); }
            .lpn-header-inner { max-width: 110rem; margin: 0 auto; padding: .85rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .lpn-brand { display: inline-flex; align-items: center; gap: .6rem; text-decoration: none; }
            .lpn-brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: .7rem; background: linear-gradient(135deg, var(--lpn-neon-color), var(--lpn-neon-color-heavy)); color: #06060a; font-weight: 800; font-size: 1.05rem; flex-shrink: 0; box-shadow: 0 0 16px -2px color-mix(in srgb, var(--lpn-neon-color) 60%, transparent); }
            .lpn-brand-logo { width: 38px; height: 38px; border-radius: .7rem; object-fit: cover; flex-shrink: 0; }
            .lpn-brand-name { font-weight: 800; font-size: 1.02rem; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; letter-spacing: -.01em; }
            .lpn-header-nav { display: flex; align-items: center; gap: 1.5rem; }
            .lpn-header-link { display: none; position: relative; font-size: .82rem; font-weight: 700; color: #9a9aa4; text-decoration: none; padding: .25rem 0; }
            .lpn-header-link::after { content: ''; position: absolute; left: 0; right: 100%; bottom: 0; height: 2px; background: var(--lpn-neon-color); box-shadow: 0 0 8px var(--lpn-neon-color); transition: right .2s ease; }
            .lpn-header-link:hover { color: #fff; }
            .lpn-header-link:hover::after { right: 0; }
            .lpn-header-contact { display: none; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 700; color: #9a9aa4; text-decoration: none; }
            .lpn-header-contact:hover { color: #fff; }
            .lpn-header-cta { display: inline-flex; align-items: center; padding: .55rem 1.25rem; border-radius: 9999px; background: linear-gradient(135deg, var(--lpn-neon-color), var(--lpn-neon-color-heavy)); color: #06060a; font-size: .82rem; font-weight: 800; text-decoration: none; white-space: nowrap; box-shadow: 0 0 18px -4px color-mix(in srgb, var(--lpn-neon-color) 65%, transparent); }
            .lpn-header-cta:hover { transform: translateY(-1px); box-shadow: 0 0 26px -2px color-mix(in srgb, var(--lpn-neon-color) 80%, transparent); }
            @media (min-width: 640px) { .lpn-header-contact { display: inline-flex; } }
            @media (min-width: 900px) { .lpn-header-link { display: inline-flex; } }

            /* Hero */
            .lpn-hero { position: relative; overflow: hidden; padding: 3rem 1rem 3.5rem; background: radial-gradient(ellipse 80% 60% at 80% -10%, color-mix(in srgb, var(--lpn-neon-color) 22%, transparent) 0%, transparent 60%), #060608; }
            .lpn-hero-bg { position: absolute; inset: 0; background-size: cover; background-position: center; opacity: .22; filter: saturate(1.1); }
            .lpn-hero-scrim { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(6,6,8,.55) 0%, #060608 92%); }
            .lpn-hero-inner { position: relative; max-width: 110rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 2.5rem; align-items: center; }
            .lpn-hero-title { margin: 0; font-size: 2rem; font-weight: 800; line-height: 1.18; letter-spacing: -.01em; color: #fff; text-shadow: 0 0 40px color-mix(in srgb, var(--lpn-neon-color) 35%, transparent); }
            .lpn-hero-desc { margin: 1.15rem 0 0; font-size: .92rem; line-height: 1.75; color: #9a9aa4; max-width: 34rem; }
            .lpn-hero-actions { margin-top: 2rem; display: flex; flex-wrap: wrap; gap: .9rem; }
            .lpn-hero-rating { margin-top: 1.75rem; display: flex; align-items: center; gap: .65rem; font-size: .8rem; font-weight: 600; color: #9a9aa4; }
            {{-- BUG đã sửa: màu chủ đạo gốc của đối tác (mặc định #0B6B3A, xanh lá đậm) tương phản chỉ
                 ~3:1 trên nền gần đen của theme Neon (dưới ngưỡng đọc được AA 4.5:1) — chữ/icon dùng
                 thẳng var(--lpn-neon-color) làm màu tiền cảnh trên nền tối sẽ bị "chìm". Đổi sang bản
                 đã làm sáng var(--lpn-neon-color-heavy) (~6.8:1) cho MỌI chỗ dùng làm màu chữ/icon
                 trên nền tối trong toàn trang — giữ nguyên màu gốc cho nền/viền/glow (không cần độ
                 tương phản chữ). --}}
            .lpn-hero-stars { display: flex; gap: .15rem; color: var(--lpn-neon-color-heavy); filter: drop-shadow(0 0 4px color-mix(in srgb, var(--lpn-neon-color) 60%, transparent)); }
            .lpn-hero-stars svg.is-dim { color: #3a3a42; filter: none; }

            .lpn-hero-right { display: flex; flex-direction: column; gap: 1.1rem; }
            .lpn-hero-media { position: relative; border-radius: 1.5rem; overflow: hidden; aspect-ratio: 4/3; background: #0d0d10; border: 1px solid rgba(255,255,255,.09); box-shadow: 0 0 0 1px rgba(0,0,0,.3), 0 24px 60px -16px rgba(0,0,0,.7), 0 0 50px -10px color-mix(in srgb, var(--lpn-neon-color) 40%, transparent); }
            .lpn-hero-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lpn-hero-media-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.25); }
            .lpn-hero-media iframe.lpn-hero-video-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; z-index: 2; }
            .lpn-hero-video-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.28); }
            .lpn-play-btn { width: 64px; height: 64px; border-radius: 9999px; background: linear-gradient(135deg, var(--lpn-neon-color), var(--lpn-neon-color-heavy)); border: none; cursor: pointer; color: #06060a; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 0 0 6px rgba(255,255,255,.06), 0 0 30px 2px color-mix(in srgb, var(--lpn-neon-color) 70%, transparent); }
            .lpn-play-btn:hover { transform: scale(1.06); }

            .lpn-hero-stat-strip { display: flex; gap: .9rem; }
            .lpn-hero-stat { flex: 1; text-align: center; border-radius: 1rem; padding: 1rem .5rem; background: linear-gradient(160deg, rgba(255,255,255,.045) 0%, rgba(255,255,255,.02) 100%); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,.09); }
            .lpn-hero-stat strong { display: block; font-size: 1.3rem; font-weight: 800; color: var(--lpn-neon-color-heavy); text-shadow: 0 0 16px color-mix(in srgb, var(--lpn-neon-color) 55%, transparent); }
            .lpn-hero-stat span { display: block; margin-top: .2rem; font-size: .68rem; color: #9a9aa4; }

            @media (min-width: 1024px) {
                .lpn-hero { padding: 4.5rem 1.5rem 5rem; }
                .lpn-hero-inner { grid-template-columns: 1fr .95fr; gap: 3.5rem; }
                .lpn-hero-title { font-size: 2.9rem; }
            }

            /* Section shell */
            .lpn-section { padding: 3.5rem 1rem; }
            .lpn-section-alt { background: linear-gradient(180deg, transparent 0%, rgba(255,255,255,.015) 100%); }
            .lpn-section-head { text-align: center; margin-bottom: 2rem; }
            .lpn-section-title { margin: 0; font-size: 1.5rem; font-weight: 800; color: #fff; text-align: center; letter-spacing: -.01em; }
            .lpn-section-title-left { text-align: left; }
            .lpn-section-sub { margin-top: .5rem; font-size: .85rem; color: #9a9aa4; }

            {{-- Đường phân cách phát sáng giữa các section — dải gradient mảnh dùng màu chủ đạo, thay
                 cho khối màu đặc như phong cách "editorial" (khác biệt hữu ý). --}}
            .lpn-section + .lpn-section, .lpn-section + section, section + .lpn-section { position: relative; }
            .lpn-section::before { content: ''; display: block; height: 1px; max-width: 60rem; margin: 0 auto 3.5rem; background: linear-gradient(90deg, transparent 0%, color-mix(in srgb, var(--lpn-neon-color) 55%, transparent) 50%, transparent 100%); box-shadow: 0 0 12px color-mix(in srgb, var(--lpn-neon-color) 45%, transparent); }
            .lpn-hero + .lpn-section::before { display: none; }

            /* Features */
            .lpn-features-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.4rem; }
            .lpn-feature { flex: 1 1 240px; max-width: 270px; padding: 1.9rem 1.5rem; text-align: center; }
            .lpn-feature-icon { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; border-radius: .9rem; background: color-mix(in srgb, var(--lpn-neon-color) 16%, rgba(255,255,255,.03)); color: var(--lpn-neon-color-heavy); filter: drop-shadow(0 0 8px color-mix(in srgb, var(--lpn-neon-color) 55%, transparent)); }
            .lpn-feature h3 { margin-top: 1.1rem; font-size: .95rem; font-weight: 800; color: #fff; }
            .lpn-feature p { margin-top: .5rem; font-size: .82rem; color: #9a9aa4; line-height: 1.6; }

            /* About */
            .lpn-about-text { margin-top: 1.1rem; font-size: .9rem; line-height: 1.8; color: #9a9aa4; white-space: pre-line; }

            /* Services */
            .lpn-services-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.3rem; }
            .lpn-service-card { flex: 1 1 280px; max-width: 340px; overflow: hidden; padding: 0; }
            .lpn-service-media { height: 160px; background: #0d0d10; display: flex; align-items: center; justify-content: center; overflow: hidden; color: rgba(255,255,255,.25); }
            .lpn-service-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lpn-service-card:hover .lpn-service-media img { transform: scale(1.06); }
            .lpn-service-body { padding: 1.3rem 1.4rem; }
            .lpn-service-body h3 { font-size: .94rem; font-weight: 800; color: #fff; }
            .lpn-service-body p { margin-top: .45rem; font-size: .8rem; color: #9a9aa4; line-height: 1.55; }
            .lpn-section-cta { margin-top: 2.25rem; text-align: center; }

            /* Pricing */
            .lpn-pricing-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.5rem; }
            .lpn-pricing-card { flex: 1 1 300px; max-width: 340px; padding: 2.3rem 1.75rem; text-align: left; }
            .lpn-pricing-card.is-highlighted { transform: translateY(-6px); border-color: color-mix(in srgb, var(--lpn-neon-color) 60%, rgba(255,255,255,.09)); box-shadow: 0 0 0 1px rgba(0,0,0,.2), 0 24px 50px -12px rgba(0,0,0,.7), 0 0 40px -8px color-mix(in srgb, var(--lpn-neon-color) 55%, transparent); }
            .lpn-pricing-badge { position: absolute; top: -13px; left: 50%; transform: translateX(-50%); padding: .3rem .9rem; border-radius: 9999px; background: linear-gradient(135deg, var(--lpn-neon-color), var(--lpn-neon-color-heavy)); color: #06060a; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; box-shadow: 0 0 16px -2px color-mix(in srgb, var(--lpn-neon-color) 70%, transparent); }
            .lpn-pricing-card h3 { font-size: 1rem; font-weight: 800; color: #fff; }
            .lpn-pricing-amount { margin-top: .55rem; font-size: 1.7rem; font-weight: 800; color: var(--lpn-neon-color-heavy); text-shadow: 0 0 20px color-mix(in srgb, var(--lpn-neon-color) 45%, transparent); }
            .lpn-pricing-amount span { font-size: .78rem; font-weight: 600; color: #9a9aa4; text-shadow: none; }
            .lpn-pricing-features { margin-top: 1.3rem; display: flex; flex-direction: column; gap: .55rem; }
            .lpn-pricing-features li { display: flex; align-items: flex-start; gap: .5rem; font-size: .8rem; color: #9a9aa4; }
            .lpn-pricing-features li svg { color: var(--lpn-neon-color-heavy); margin-top: .1rem; flex-shrink: 0; }
            .lpn-pricing-btn { display: flex; width: 100%; justify-content: center; margin-top: 1.6rem; }

            /* Stats */
            .lpn-stats-inner { display: flex; flex-direction: column; gap: 2rem; }
            .lpn-stats-text h2 { text-align: left; }
            .lpn-stats-text p { margin-top: .6rem; font-size: .85rem; color: #9a9aa4; max-width: 36rem; }
            .lpn-stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.2rem; }
            .lpn-stat { text-align: center; padding: 1.4rem 1rem; }
            .lpn-stat-num { display: block; font-size: 1.85rem; font-weight: 800; color: var(--lpn-neon-color-heavy); text-shadow: 0 0 18px color-mix(in srgb, var(--lpn-neon-color) 50%, transparent); }
            .lpn-stat-label { display: block; margin-top: .3rem; font-size: .76rem; color: #9a9aa4; }
            @media (min-width: 768px) { .lpn-stats-inner { flex-direction: row; align-items: center; justify-content: space-between; gap: 3rem; } .lpn-stats-text { flex: 1; } .lpn-stats-grid { flex: 1.2; grid-template-columns: repeat(4, 1fr); } }

            /* Testimonials */
            .lpn-testimonial { margin-top: 0; display: flex; flex-direction: column; align-items: center; gap: 1.5rem; padding: 2rem; text-align: center; }
            .lpn-testimonial-media img { width: 88px; height: 88px; border-radius: 9999px; object-fit: cover; border: 2px solid color-mix(in srgb, var(--lpn-neon-color) 50%, transparent); }
            .lpn-testimonial-avatar { width: 88px; height: 88px; border-radius: 9999px; background: linear-gradient(135deg, var(--lpn-neon-color), var(--lpn-neon-color-heavy)); color: #06060a; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; font-weight: 800; }
            .lpn-testimonial-body p { font-size: .92rem; color: #e7e7ea; line-height: 1.7; font-style: italic; }
            .lpn-testimonial-author { margin-top: 1rem; display: flex; flex-direction: column; }
            .lpn-testimonial-author strong { color: #fff; font-size: .88rem; }
            .lpn-testimonial-author span { font-size: .76rem; color: #9a9aa4; }
            .lpn-dots { margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: .5rem; }
            .lpn-dots button { width: 8px; height: 8px; border-radius: 9999px; background: #3a3a42; border: none; padding: 0; cursor: pointer; }
            .lpn-dots button.is-active { background: var(--lpn-neon-color-heavy); width: 20px; box-shadow: 0 0 8px var(--lpn-neon-color-heavy); }
            @media (min-width: 640px) { .lpn-testimonial { flex-direction: row; text-align: left; } }

            /* Venues */
            .lpn-venues-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.1rem; }
            .lpn-venues-grid > * { flex: 1 1 220px; max-width: 280px; }
            {{-- venues.card dùng nền/màu chữ trắng riêng (Tailwind) — bọc trong khung viền gradient
                 phát sáng để hoà vào thẩm mỹ "neon" mà KHÔNG cần sửa component dùng chung. --}}
            .lpn-venue-frame { position: relative; border-radius: 1rem; padding: 2px; background: linear-gradient(150deg, color-mix(in srgb, var(--lpn-neon-color) 55%, transparent), transparent 60%); box-shadow: 0 0 24px -8px color-mix(in srgb, var(--lpn-neon-color) 45%, transparent); transition: box-shadow .25s ease, transform .25s ease; }
            .lpn-venue-frame:hover { transform: translateY(-4px); box-shadow: 0 0 34px -4px color-mix(in srgb, var(--lpn-neon-color) 60%, transparent); }
            .lpn-venue-frame > .venue-card { border-radius: .85rem; overflow: hidden; }
            .lpn-empty { padding: 4rem 0; text-align: center; font-size: .9rem; color: #9a9aa4; }

            /* News */
            .lpn-news-grid { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.25rem; }
            .lpn-news-card { flex: 1 1 240px; max-width: 280px; display: block; overflow: hidden; text-decoration: none; padding: 0; }
            .lpn-news-media { height: 150px; background: #0d0d10; display: flex; align-items: center; justify-content: center; overflow: hidden; color: rgba(255,255,255,.25); }
            .lpn-news-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lpn-news-card:hover .lpn-news-media img { transform: scale(1.06); }
            .lpn-news-body { padding: 1.25rem 1.4rem; }
            .lpn-news-body h3 { font-size: .88rem; font-weight: 800; color: #fff; line-height: 1.4; }
            .lpn-news-body p { margin-top: .4rem; font-size: .78rem; color: #9a9aa4; line-height: 1.55; }

            /* FAQ */
            .lpn-faq-inner { display: grid; grid-template-columns: 1fr; gap: 2rem; align-items: center; background: none; border: none; box-shadow: none; backdrop-filter: none; padding: 0; }
            .lpn-faq-inner::before { display: none; }
            .lpn-faq-media { min-height: 14rem; border-radius: 1.5rem; overflow: hidden; background: #0d0d10; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.09); box-shadow: 0 0 40px -12px color-mix(in srgb, var(--lpn-neon-color) 40%, transparent); }
            .lpn-faq-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpn-faq-list { margin-top: 1.5rem; display: flex; flex-direction: column; gap: .75rem; }
            @media (min-width: 900px) { .lpn-faq-inner { grid-template-columns: .85fr 1.15fr; } .lpn-faq-media { height: 100%; min-height: 26rem; } }
            .lpn-faq-item { padding: 0; overflow: hidden; }
            .lpn-faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.1rem 1.35rem; background: none; border: none; text-align: left; font-size: .88rem; font-weight: 700; color: #fff; cursor: pointer; }
            .lpn-faq-question svg { color: var(--lpn-neon-color-heavy); filter: drop-shadow(0 0 4px color-mix(in srgb, var(--lpn-neon-color) 50%, transparent)); }
            .lpn-faq-answer { padding: 0 1.35rem 1.2rem; font-size: .83rem; color: #9a9aa4; line-height: 1.65; }

            /* CTA */
            .lpn-cta { position: relative; overflow: hidden; padding: 4.5rem 1rem; text-align: center; background: radial-gradient(ellipse 70% 100% at 50% 100%, color-mix(in srgb, var(--lpn-neon-color) 26%, transparent) 0%, transparent 65%), #060608; }
            .lpn-cta-inner { position: relative; max-width: 42rem; margin: 0 auto; }
            .lpn-cta h2 { font-size: 1.7rem; font-weight: 800; color: #fff; letter-spacing: -.01em; text-shadow: 0 0 30px color-mix(in srgb, var(--lpn-neon-color) 40%, transparent); }
            .lpn-cta p { margin-top: .6rem; font-size: .9rem; color: #9a9aa4; }
            .lpn-cta .lpn-btn-primary { margin-top: 1.9rem; }

            /* Footer */
            .lpn-footer { background: #030304; border-top: 1px solid color-mix(in srgb, var(--lpn-neon-color-heavy) 35%, rgba(255,255,255,.09)); box-shadow: 0 -30px 60px -40px color-mix(in srgb, var(--lpn-neon-color) 35%, transparent); padding: 3.5rem 1rem 1.5rem; }
            .lpn-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; text-align: center; }
            .lpn-footer-col { display: flex; flex-direction: column; align-items: center; gap: .65rem; }
            .lpn-footer-col h4 { font-size: .8rem; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .35rem; }
            .lpn-footer-col a { font-size: .82rem; color: #9a9aa4; text-decoration: none; }
            .lpn-footer-col a:hover { color: var(--lpn-neon-color-heavy); }
            .lpn-footer-col-brand { max-width: 24rem; margin: 0 auto; }
            .lpn-footer-brand { display: flex; align-items: center; gap: .6rem; }
            .lpn-footer-col-brand p { margin-top: .3rem; font-size: .82rem; color: #9a9aa4; line-height: 1.6; }
            .lpn-footer-social { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 9999px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.09); color: #d1d1d6; }
            .lpn-footer-social:hover { background: var(--lpn-neon-color); border-color: var(--lpn-neon-color); color: #06060a; box-shadow: 0 0 16px -2px color-mix(in srgb, var(--lpn-neon-color) 70%, transparent); }
            .lpn-footer-item { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; color: #9a9aa4; text-decoration: none; }
            .lpn-footer-item:hover { color: #fff; }
            .lpn-footer-bottom { margin-top: 2.5rem; text-align: center; font-size: .74rem; color: #6a6a72; border-top: 1px solid rgba(255,255,255,.09); padding-top: 1.25rem; }
            @media (min-width: 768px) { .lpn-footer-grid { grid-template-columns: 1.4fr 1fr 1fr; text-align: left; } .lpn-footer-col { align-items: flex-start; } .lpn-footer-col-brand { margin: 0; } }
        </style>
    @endonce
@endsection
