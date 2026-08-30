@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Trang landing page RIÊNG cho 1 đối tác theo subdomain — biến thể "Minimal" của
         partner-landing.blade.php (bản gốc "bento" nền tối). CÙNG hợp đồng biến/CÙNG mức độ chịu
         lỗi (mọi khối tuỳ chọn tự ẩn khi rỗng) nhưng ngôn ngữ thị giác khác hẳn: nền be nhạt, cột
         nội dung căn giữa với max-width vừa phải (không tràn viền), viền mảnh 1px thay vì đổ bóng
         nặng, màu nhấn ($landingColor/...) chỉ xuất hiện ở vài điểm có chủ đích (nút, icon, nhãn
         nhỏ) — không dùng làm mảng màu lớn, kiểu trang sản phẩm B2B/tư vấn tinh gọn.

         Bố cục: header → hero (2 cột) → điểm nổi bật → giới thiệu (tuỳ chọn) → dịch vụ → bảng giá
         (tuỳ chọn) → thành tựu → đánh giá (tuỳ chọn) → chi nhánh/đặt sân → tin tức (tuỳ chọn) →
         FAQ (2 cột) → CTA → footer. --}}
    <div class="partner-landing-minimal-scope" style="
        --lp-minimal-color: {{ $landingColor }};
        --lp-minimal-color-heavy: {{ $landingColorHeavy }};
        --lp-minimal-color-light: {{ $landingColorLight }};
    ">
        {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
        <header class="lpm-header">
            <div class="lpm-header-inner">
                <a href="#" class="lpm-brand">
                    @if ($partnerLogoUrl)
                        <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpm-brand-logo">
                    @else
                        <span class="lpm-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                    @endif
                    <span class="lpm-brand-name">{{ $partner->name }}</span>
                </a>

                <nav class="lpm-header-nav">
                    @if ($description)
                        <a href="#lpm-about" class="lpm-header-link">Giới thiệu</a>
                    @endif
                    <a href="#lpm-services" class="lpm-header-link">Dịch vụ</a>
                    @if ($pricingPlans->isNotEmpty())
                        <a href="#lpm-pricing" class="lpm-header-link">Bảng giá</a>
                    @endif
                    <a href="#lpm-faq" class="lpm-header-link">Hỏi đáp</a>
                    <a href="#lpm-contact" class="lpm-header-link">Liên hệ</a>
                </nav>

                <div class="lpm-header-actions">
                    @if ($partner->phone)
                        <a href="tel:{{ $partner->phone }}" class="lpm-header-contact">
                            <x-heroicon-o-phone class="h-4 w-4" />
                            <span>{{ $partner->phone }}</span>
                        </a>
                    @endif
                    <a href="#lp-venues" class="lpm-header-cta">Đặt sân ngay</a>
                </div>
            </div>
        </header>

        {{-- ── HERO (2 cột: chữ trái, ảnh phải với thẻ ảnh phụ chồng nhẹ) ────────── --}}
        <section class="lpm-hero">
            <div class="lpm-hero-inner">
                <div class="lpm-hero-content lpm-reveal">
                    <span class="lpm-eyebrow">Đặt sân trực tuyến</span>
                    <h1 class="lpm-hero-title">{{ $tagline }}</h1>
                    <p class="lpm-hero-desc">{{ $description ?: ('Đặt sân trực tuyến tại ' . $partner->name . ' — chọn chi nhánh, xem lịch trống theo thời gian thực và đặt lịch chỉ trong vài bước.') }}</p>

                    <div class="lpm-hero-actions">
                        <a href="#lp-venues" class="lpm-btn-primary">
                            <span>Đặt sân ngay</span>
                            <x-heroicon-o-arrow-right class="h-4 w-4" />
                        </a>
                        <a href="#lpm-services" class="lpm-btn-ghost">Tìm hiểu thêm</a>
                    </div>

                    <div class="lpm-hero-meta">
                        <div class="lpm-hero-rating">
                            <div class="lpm-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 {{ $avgRating && $i <= round($avgRating) ? '' : 'is-dim' }}" />
                                @endfor
                            </div>
                            <span>{{ $avgRating ? number_format($avgRating, 1) . ' / 5' : 'Chưa có đánh giá' }}</span>
                        </div>
                        @if (! empty($stats[0]['number']))
                            <div class="lpm-hero-divider"></div>
                            <div class="lpm-hero-stat">
                                <strong>{{ $stats[0]['number'] }}</strong>
                                <span>{{ $stats[0]['label'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="lpm-hero-media lpm-reveal" @if ($heroYoutubeId) x-data="{ playing: false }" @endif>
                    <div class="lpm-hero-media-main">
                        @if ($heroImageUrl)
                            <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="lazy" @if ($heroYoutubeId) x-show="!playing" @endif>
                        @elseif ($heroYoutubeId)
                            <img src="https://i3.ytimg.com/vi/{{ $heroYoutubeId }}/hqdefault.jpg" alt="{{ $partner->name }}" x-show="!playing">
                        @else
                            <div class="lpm-media-placeholder"><x-heroicon-o-photo class="h-9 w-9" /></div>
                        @endif

                        @if ($heroYoutubeId)
                            <div class="lpm-hero-scrim" x-show="!playing"></div>
                            <button type="button" class="lpm-play-btn" x-show="!playing" @click="playing = true" aria-label="Xem video giới thiệu">
                                <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M8 5v14l11-7z" /></svg>
                                <span>Xem video</span>
                            </button>
                            <template x-if="playing">
                                <iframe
                                    class="lpm-hero-video-frame"
                                    src="https://www.youtube-nocookie.com/embed/{{ $heroYoutubeId }}?autoplay=1&rel=0"
                                    title="Video giới thiệu {{ $partner->name }}"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </template>
                        @endif
                    </div>

                    @if ($heroSecondaryImageUrl)
                        <div class="lpm-hero-media-accent">
                            <img src="{{ $heroSecondaryImageUrl }}" alt="{{ $partner->name }}" loading="lazy">
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ── ĐIỂM NỔI BẬT ───────────────────────────────────────────────────── --}}
        <section class="lpm-features">
            <div class="lpm-container lpm-reveal">
                <div class="lpm-section-head">
                    <span class="lpm-eyebrow">Vì sao chọn chúng tôi</span>
                    <h2 class="lpm-section-title">Điều thú vị về {{ $partner->name }}</h2>
                </div>
                <div class="lpm-features-grid">
                    @foreach ($features as $feature)
                        <div class="lpm-feature">
                            <span class="lpm-feature-icon">
                                <x-dynamic-component :component="$feature['icon'] ?? 'heroicon-o-star'" class="h-5 w-5" />
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
            <section id="lpm-about" class="lpm-about">
                <div class="lpm-container-narrow lpm-reveal">
                    <span class="lpm-eyebrow">Câu chuyện của chúng tôi</span>
                    <h2 class="lpm-section-title">Giới thiệu về {{ $partner->name }}</h2>
                    <p class="lpm-about-text">{{ $description }}</p>
                </div>
            </section>
        @endif

        {{-- ── DỊCH VỤ ────────────────────────────────────────────────────────── --}}
        <section id="lpm-services" class="lpm-services">
            <div class="lpm-container lpm-reveal">
                <div class="lpm-section-head">
                    <span class="lpm-eyebrow">Dịch vụ</span>
                    <h2 class="lpm-section-title">Cung cấp dịch vụ thuê sân tốt nhất</h2>
                </div>
                <div class="lpm-services-grid">
                    @foreach ($services as $service)
                        <div class="lpm-service-card">
                            <div class="lpm-service-media">
                                @if (! empty($service['image']))
                                    <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy">
                                @else
                                    <div class="lpm-media-placeholder"><x-heroicon-o-photo class="h-7 w-7" /></div>
                                @endif
                            </div>
                            <div class="lpm-service-body">
                                <h3>{{ $service['title'] }}</h3>
                                @if (! empty($service['description']))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── BẢNG GIÁ (chỉ hiện khi admin có cấu hình landing_pricing_plans) ──── --}}
        @if ($pricingPlans->isNotEmpty())
            <section id="lpm-pricing" class="lpm-pricing">
                <div class="lpm-container lpm-reveal">
                    <div class="lpm-section-head">
                        <span class="lpm-eyebrow">Bảng giá</span>
                        <h2 class="lpm-section-title">Chọn gói phù hợp với bạn</h2>
                    </div>
                    <div class="lpm-pricing-grid">
                        @foreach ($pricingPlans as $plan)
                            <div class="lpm-pricing-card {{ ! empty($plan['highlighted']) ? 'is-highlighted' : '' }}">
                                @if (! empty($plan['highlighted']))
                                    <span class="lpm-pricing-tag">Phổ biến nhất</span>
                                @endif
                                <h3>{{ $plan['name'] }}</h3>
                                <div class="lpm-pricing-amount">
                                    {{ $plan['price'] }}
                                    @if (! empty($plan['unit']))
                                        <span>{{ $plan['unit'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($plan['features']))
                                    <ul class="lpm-pricing-features">
                                        @foreach ($plan['features'] as $line)
                                            <li><x-heroicon-s-check-circle class="h-4 w-4" />{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#lp-venues" class="{{ ! empty($plan['highlighted']) ? 'lpm-btn-primary' : 'lpm-btn-outline' }} lpm-pricing-cta">Đăng ký ngay</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── THÀNH TỰU ──────────────────────────────────────────────────────── --}}
        <section class="lpm-stats">
            <div class="lpm-container lpm-reveal">
                <div class="lpm-stats-row">
                    @foreach ($stats as $stat)
                        <div class="lpm-stat">
                            <span class="lpm-stat-num">{{ $stat['number'] }}</span>
                            <span class="lpm-stat-label">{{ $stat['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── ĐÁNH GIÁ (chỉ hiện khi admin có cấu hình) ─────────────────────────── --}}
        @if (! empty($testimonials))
            <section class="lpm-testimonials" x-data="{ active: 0, total: {{ count($testimonials) }} }">
                <div class="lpm-container-narrow lpm-reveal">
                    <div class="lpm-section-head">
                        <span class="lpm-eyebrow">Khách hàng nói gì</span>
                        <h2 class="lpm-section-title">Đánh giá từ khách hàng</h2>
                    </div>

                    @foreach ($testimonials as $i => $t)
                        <div class="lpm-testimonial" x-show="active === {{ $i }}" x-cloak>
                            <x-heroicon-s-chat-bubble-left-right class="h-6 w-6 lpm-quote-icon" />
                            <p>"{{ $t['quote'] }}"</p>
                            <div class="lpm-testimonial-author">
                                @if (! empty($t['image']))
                                    <img src="{{ $t['image'] }}" alt="{{ $t['name'] }}">
                                @else
                                    <span class="lpm-testimonial-avatar">{{ mb_substr($t['name'], 0, 1) }}</span>
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

                    @if (count($testimonials) > 1)
                        <div class="lpm-testimonial-dots">
                            @foreach ($testimonials as $i => $t)
                                <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'is-active' : ''" aria-label="Đánh giá {{ $i + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- ── DANH SÁCH CHI NHÁNH ───────────────────────────────────────────────── --}}
        <section id="lp-venues" class="lpm-venues">
            <div class="lpm-container lpm-reveal">
                @if ($venues->isNotEmpty())
                    <div class="lpm-section-head">
                        <span class="lpm-eyebrow">Chi nhánh</span>
                        <h2 class="lpm-section-title">Chi nhánh của {{ $partner->name }}</h2>
                        <p class="lpm-section-sub">Chọn 1 chi nhánh để xem lịch trống và đặt sân ngay.</p>
                    </div>

                    <div class="lpm-venues-grid">
                        @foreach ($venues as $venue)
                            <x-bladethemev1::venues.card :venue="$venue" />
                        @endforeach
                    </div>
                @else
                    <p class="lpm-empty">{{ $partner->name }} hiện chưa có chi nhánh nào hiển thị.</p>
                @endif
            </div>
        </section>

        {{-- ── TIN TỨC / ƯU ĐÃI (chỉ hiện khi admin có cấu hình) ─────────────────── --}}
        @if (! empty($news))
            <section class="lpm-news">
                <div class="lpm-container lpm-reveal">
                    <div class="lpm-section-head">
                        <span class="lpm-eyebrow">Cập nhật</span>
                        <h2 class="lpm-section-title">Tin tức &amp; ưu đãi mới nhất</h2>
                    </div>
                    <div class="lpm-news-grid">
                        @foreach ($news as $item)
                            <a href="{{ $item['url'] ?: '#' }}" @if ($item['url']) target="_blank" rel="noopener" @endif class="lpm-news-card">
                                <div class="lpm-news-media">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                                    @else
                                        <div class="lpm-media-placeholder"><x-heroicon-o-photo class="h-7 w-7" /></div>
                                    @endif
                                </div>
                                <div class="lpm-news-body">
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

        {{-- ── FAQ (2 cột: ảnh trái nhỏ gọn + danh sách phải, kiểu tối giản) ─────── --}}
        <section id="lpm-faq" class="lpm-faq">
            <div class="lpm-container lpm-reveal">
                <div class="lpm-faq-inner">
                    <div class="lpm-faq-media">
                        @if ($faqImageUrl)
                            <img src="{{ $faqImageUrl }}" alt="Câu hỏi thường gặp" loading="lazy">
                        @else
                            <div class="lpm-media-placeholder"><x-heroicon-o-chat-bubble-left-right class="h-9 w-9" /></div>
                        @endif
                    </div>
                    <div class="lpm-faq-content">
                        <span class="lpm-eyebrow">Hỗ trợ</span>
                        <h2 class="lpm-section-title lpm-section-title-left">Các câu hỏi thường gặp</h2>
                        <div class="lpm-faq-list">
                            @foreach ($faqs as $i => $faq)
                                <div class="lpm-faq-item" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                                    <button type="button" class="lpm-faq-question" @click="open = !open">
                                        <span>{{ $faq['question'] }}</span>
                                        <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 transition-transform" x-bind:style="open ? 'transform:rotate(180deg)' : ''" />
                                    </button>
                                    <div class="lpm-faq-answer" x-show="open" x-collapse x-cloak>{{ $faq['answer'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── CTA CUỐI TRANG ─────────────────────────────────────────────────── --}}
        <section class="lpm-cta">
            <div class="lpm-container-narrow lpm-reveal">
                <h2>Sẵn sàng đặt sân ngay hôm nay?</h2>
                <p>Chọn chi nhánh phù hợp và đặt lịch chỉ trong vài phút.</p>
                <a href="#lp-venues" class="lpm-btn-primary">Đặt sân ngay</a>
            </div>
        </section>

        {{-- ── FOOTER ─────────────────────────────────────────────────────────── --}}
        <footer id="lpm-contact" class="lpm-footer">
            <div class="lpm-container">
                <div class="lpm-footer-grid">
                    <div class="lpm-footer-col lpm-footer-col-brand">
                        <div class="lpm-footer-brand">
                            @if ($partnerLogoUrl)
                                <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpm-brand-logo">
                            @else
                                <span class="lpm-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                            @endif
                            <span class="lpm-brand-name">{{ $partner->name }}</span>
                        </div>
                        <p>{{ $description ? \Illuminate\Support\Str::limit($description, 140) : ('Đặt sân thể thao trực tuyến tại ' . $partner->name . ' — nhanh chóng, tiện lợi, xác nhận tức thì.') }}</p>
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" class="lpm-footer-social" aria-label="Facebook">
                                <x-heroicon-o-globe-alt class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    <div class="lpm-footer-col">
                        <h4>Liên kết nhanh</h4>
                        @if ($description)
                            <a href="#lpm-about">Giới thiệu</a>
                        @endif
                        <a href="#lpm-services">Dịch vụ</a>
                        <a href="#lp-venues">Chi nhánh &amp; đặt sân</a>
                        @if ($pricingPlans->isNotEmpty())
                            <a href="#lpm-pricing">Bảng giá</a>
                        @endif
                        <a href="#lpm-faq">Hỏi đáp</a>
                    </div>

                    <div class="lpm-footer-col">
                        <h4>Liên hệ</h4>
                        @if ($partner->address)
                            <span class="lpm-footer-item"><x-heroicon-o-map-pin class="h-4 w-4" />{{ $partner->address }}</span>
                        @endif
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lpm-footer-item"><x-heroicon-o-phone class="h-4 w-4" />{{ $partner->phone }}</a>
                        @endif
                        @if ($partner->email)
                            <a href="mailto:{{ $partner->email }}" class="lpm-footer-item"><x-heroicon-o-envelope class="h-4 w-4" />{{ $partner->email }}</a>
                        @endif
                    </div>
                </div>

                <div class="lpm-footer-bottom">
                    © {{ now()->year }} {{ $partner->name }} — Cung cấp bởi {{ config('app.name') }}
                </div>
            </div>
        </footer>
    </div>

    @once
        <script>
            // Bóng viền header khi cuộn xuống (rất nhẹ, đúng tinh thần tối giản) + hiệu ứng fade-up
            // cho các khối .lpm-reveal khi cuộn tới — thuần vanilla JS/IntersectionObserver, không
            // phụ thuộc plugin ngoài (Alpine chỉ dùng cho x-show/x-collapse đã có sẵn toàn site).
            document.addEventListener('DOMContentLoaded', function () {
                var header = document.querySelector('.lpm-header');
                if (header) {
                    window.addEventListener('scroll', function () {
                        header.classList.toggle('is-scrolled', window.scrollY > 8);
                    }, { passive: true });
                }

                var revealTargets = document.querySelectorAll('.lpm-reveal');
                if (revealTargets.length && 'IntersectionObserver' in window) {
                    var observer = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('is-visible');
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
                    revealTargets.forEach(function (el) { observer.observe(el); });
                } else {
                    revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
                }
            });
        </script>

        <style>
            .partner-landing-minimal-scope {
                --color-primary: var(--lp-minimal-color);
                --lpm-bg: #faf9f6;
                --lpm-surface: #ffffff;
                --lpm-border: #e8e4db;
                --lpm-ink: #201f1c;
                --lpm-muted: #75726a;
                --lpm-muted-2: #a6a299;
                font-family: inherit;
                color: var(--lpm-ink);
                background: var(--lpm-bg);
                -webkit-font-smoothing: antialiased;
            }
            .partner-landing-minimal-scope * { scroll-behavior: smooth; }
            .partner-landing-minimal-scope a, .partner-landing-minimal-scope button { transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease, border-color .16s ease, color .16s ease; }

            .lpm-container { max-width: 72rem; margin: 0 auto; padding: 0 1.25rem; }
            .lpm-container-narrow { max-width: 46rem; margin: 0 auto; padding: 0 1.25rem; text-align: center; }

            {{-- Nhãn nhỏ phía trên tiêu đề — chỉ chữ + chấm tròn nhỏ màu nhấn, KHÔNG phải khối màu
                 nền lớn, giữ đúng tinh thần "màu nhấn chỉ xuất hiện ở điểm nhỏ có chủ đích". --}}
            .lpm-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: color-mix(in srgb, var(--lp-minimal-color) 65%, var(--lpm-ink)); margin-bottom: .6rem; }
            .lpm-eyebrow::before { content: ''; width: 5px; height: 5px; border-radius: 9999px; background: var(--lp-minimal-color); }

            .lpm-section-head { text-align: center; margin-bottom: 2.25rem; }
            .lpm-section-title { font-size: 1.5rem; font-weight: 700; letter-spacing: -.01em; color: var(--lpm-ink); line-height: 1.3; }
            .lpm-section-title-left { text-align: left; }
            .lpm-section-sub { margin-top: .5rem; font-size: .88rem; color: var(--lpm-muted); }

            .lpm-reveal { opacity: 0; transform: translateY(18px); transition: opacity .6s ease, transform .6s ease; }
            .lpm-reveal.is-visible { opacity: 1; transform: translateY(0); }
            .no-js .lpm-reveal { opacity: 1; transform: none; }

            .lpm-media-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: var(--lpm-muted-2); background: #f1efe9; }

            /* Buttons */
            .lpm-btn-primary { display: inline-flex; align-items: center; gap: .5rem; padding: .7rem 1.4rem; border-radius: .6rem; background: var(--lp-minimal-color); color: #fff; font-weight: 600; font-size: .85rem; text-decoration: none; border: 1px solid var(--lp-minimal-color); }
            .lpm-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px color-mix(in srgb, var(--lp-minimal-color) 30%, transparent); }
            .lpm-btn-ghost { display: inline-flex; align-items: center; padding: .7rem 1.4rem; border-radius: .6rem; border: 1px solid var(--lpm-border); color: var(--lpm-ink); font-weight: 600; font-size: .85rem; text-decoration: none; background: var(--lpm-surface); }
            .lpm-btn-ghost:hover { border-color: color-mix(in srgb, var(--lp-minimal-color) 45%, var(--lpm-border)); color: var(--lp-minimal-color); }
            .lpm-btn-outline { display: inline-flex; align-items: center; justify-content: center; padding: .65rem 1.25rem; border-radius: .6rem; border: 1px solid var(--lpm-border); color: var(--lpm-ink); font-weight: 600; font-size: .82rem; text-decoration: none; }
            .lpm-btn-outline:hover { border-color: var(--lp-minimal-color); color: var(--lp-minimal-color); }

            /* Header */
            .lpm-header { position: sticky; top: 0; z-index: 40; background: color-mix(in srgb, var(--lpm-surface) 92%, transparent); backdrop-filter: blur(8px); border-bottom: 1px solid var(--lpm-border); }
            .lpm-header.is-scrolled { box-shadow: 0 1px 0 var(--lpm-border), 0 8px 20px rgba(32,31,28,.04); }
            .lpm-header-inner { max-width: 72rem; margin: 0 auto; padding: .9rem 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .lpm-brand { display: inline-flex; align-items: center; gap: .6rem; text-decoration: none; }
            .lpm-brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: .6rem; background: color-mix(in srgb, var(--lp-minimal-color) 14%, #fff); color: var(--lp-minimal-color); font-weight: 700; font-size: .95rem; flex-shrink: 0; }
            .lpm-brand-logo { width: 34px; height: 34px; border-radius: .6rem; object-fit: cover; flex-shrink: 0; }
            .lpm-brand-name { font-weight: 700; font-size: .95rem; color: var(--lpm-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 38vw; letter-spacing: -.01em; }
            .lpm-header-nav { display: none; align-items: center; gap: 1.75rem; }
            .lpm-header-link { position: relative; font-size: .82rem; font-weight: 600; color: var(--lpm-muted); text-decoration: none; padding: .25rem 0; }
            .lpm-header-link:hover { color: var(--lpm-ink); }
            .lpm-header-actions { display: flex; align-items: center; gap: 1.25rem; }
            .lpm-header-contact { display: none; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 600; color: var(--lpm-muted); text-decoration: none; }
            .lpm-header-contact:hover { color: var(--lp-minimal-color); }
            .lpm-header-cta { display: inline-flex; align-items: center; padding: .55rem 1.15rem; border-radius: .55rem; background: var(--lp-minimal-color); color: #fff; font-size: .8rem; font-weight: 600; text-decoration: none; white-space: nowrap; }
            .lpm-header-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 16px color-mix(in srgb, var(--lp-minimal-color) 30%, transparent); }
            @media (min-width: 640px) { .lpm-header-contact { display: inline-flex; } }
            @media (min-width: 900px) { .lpm-header-nav { display: inline-flex; } }

            /* Hero */
            .lpm-hero { padding: 3rem 1.25rem 2.5rem; }
            .lpm-hero-inner { max-width: 72rem; margin: 0 auto; display: grid; grid-template-columns: 1fr; gap: 2.5rem; align-items: center; }
            .lpm-hero-title { margin-top: .25rem; font-size: 2rem; font-weight: 700; letter-spacing: -.02em; line-height: 1.2; color: var(--lpm-ink); }
            .lpm-hero-desc { margin-top: 1rem; font-size: .92rem; line-height: 1.75; color: var(--lpm-muted); max-width: 34rem; }
            .lpm-hero-actions { margin-top: 1.75rem; display: flex; flex-wrap: wrap; gap: .85rem; }
            .lpm-hero-meta { margin-top: 2rem; display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
            .lpm-hero-rating { display: flex; align-items: center; gap: .55rem; font-size: .82rem; font-weight: 600; color: var(--lpm-muted); }
            .lpm-stars { display: flex; gap: .1rem; color: var(--lp-minimal-color); }
            .lpm-stars svg.is-dim { color: #ddd8cc; }
            .lpm-hero-divider { width: 1px; height: 28px; background: var(--lpm-border); }
            .lpm-hero-stat { display: flex; flex-direction: column; }
            .lpm-hero-stat strong { font-size: 1.1rem; font-weight: 700; color: var(--lpm-ink); }
            .lpm-hero-stat span { font-size: .74rem; color: var(--lpm-muted-2); }

            .lpm-hero-media { position: relative; }
            .lpm-hero-media-main { position: relative; aspect-ratio: 4 / 3; border-radius: 1rem; overflow: hidden; border: 1px solid var(--lpm-border); background: #f1efe9; }
            .lpm-hero-media-main img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lpm-hero-scrim { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,.45) 0%, transparent 45%); pointer-events: none; }
            .lpm-play-btn { position: absolute; left: 1.1rem; bottom: 1.1rem; display: inline-flex; align-items: center; gap: .5rem; padding: .55rem 1rem .55rem .6rem; border-radius: 9999px; background: rgba(255,255,255,.95); border: none; cursor: pointer; color: var(--lpm-ink); font-size: .78rem; font-weight: 700; }
            .lpm-play-btn svg { color: var(--lp-minimal-color); }
            .lpm-play-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(0,0,0,.18); }
            .lpm-hero-video-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; z-index: 2; }
            .lpm-hero-media-accent { position: absolute; left: -1rem; bottom: -1.25rem; width: 40%; aspect-ratio: 1 / 1; border-radius: .85rem; overflow: hidden; border: 4px solid var(--lpm-bg); box-shadow: 0 10px 26px rgba(32,31,28,.12); display: none; }
            .lpm-hero-media-accent img { width: 100%; height: 100%; object-fit: cover; }
            @media (min-width: 480px) { .lpm-hero-media-accent { display: block; } }

            @media (min-width: 900px) {
                .lpm-hero { padding: 4.5rem 1.5rem; }
                .lpm-hero-inner { grid-template-columns: 1.05fr .95fr; gap: 3.5rem; }
                .lpm-hero-title { font-size: 2.4rem; }
            }

            /* Features */
            .lpm-features { padding: 3.5rem 0; border-top: 1px solid var(--lpm-border); }
            .lpm-features-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
            .lpm-feature { padding: 1.5rem; border: 1px solid var(--lpm-border); border-radius: .85rem; background: var(--lpm-surface); }
            .lpm-feature:hover { transform: translateY(-3px); border-color: color-mix(in srgb, var(--lp-minimal-color) 35%, var(--lpm-border)); box-shadow: 0 10px 24px rgba(32,31,28,.05); }
            .lpm-feature-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: .6rem; background: color-mix(in srgb, var(--lp-minimal-color) 10%, #fff); color: var(--lp-minimal-color); }
            .lpm-feature h3 { margin-top: .9rem; font-size: .92rem; font-weight: 700; color: var(--lpm-ink); }
            .lpm-feature p { margin-top: .4rem; font-size: .8rem; color: var(--lpm-muted); line-height: 1.6; }
            @media (min-width: 640px) { .lpm-features-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { .lpm-features-grid { grid-template-columns: repeat(4, 1fr); } }

            /* About */
            .lpm-about { padding: 3rem 0; }
            .lpm-about-text { margin-top: 1rem; font-size: .9rem; line-height: 1.8; color: var(--lpm-muted); white-space: pre-line; }

            /* Services */
            .lpm-services { padding: 3.5rem 0; border-top: 1px solid var(--lpm-border); }
            .lpm-services-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
            .lpm-service-card { border: 1px solid var(--lpm-border); border-radius: .9rem; overflow: hidden; background: var(--lpm-surface); }
            .lpm-service-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(32,31,28,.06); }
            .lpm-service-media { height: 150px; overflow: hidden; }
            .lpm-service-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lpm-service-card:hover .lpm-service-media img { transform: scale(1.04); }
            .lpm-service-body { padding: 1.15rem 1.25rem; }
            .lpm-service-body h3 { font-size: .9rem; font-weight: 700; color: var(--lpm-ink); }
            .lpm-service-body p { margin-top: .4rem; font-size: .8rem; color: var(--lpm-muted); line-height: 1.55; }
            @media (min-width: 640px) { .lpm-services-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { .lpm-services-grid { grid-template-columns: repeat(3, 1fr); } }

            /* Pricing */
            .lpm-pricing { padding: 3.5rem 0; border-top: 1px solid var(--lpm-border); }
            .lpm-pricing-grid { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
            .lpm-pricing-card { position: relative; padding: 2rem 1.6rem; border: 1px solid var(--lpm-border); border-radius: 1rem; background: var(--lpm-surface); }
            .lpm-pricing-card.is-highlighted { border: 1.5px solid var(--lp-minimal-color); }
            .lpm-pricing-tag { display: inline-block; margin-bottom: .6rem; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--lp-minimal-color); }
            .lpm-pricing-card h3 { font-size: .95rem; font-weight: 700; color: var(--lpm-ink); }
            .lpm-pricing-amount { margin-top: .5rem; font-size: 1.5rem; font-weight: 700; color: var(--lpm-ink); }
            .lpm-pricing-amount span { font-size: .76rem; font-weight: 500; color: var(--lpm-muted-2); }
            .lpm-pricing-features { margin-top: 1.15rem; display: flex; flex-direction: column; gap: .55rem; }
            .lpm-pricing-features li { display: flex; align-items: flex-start; gap: .5rem; font-size: .8rem; color: var(--lpm-muted); }
            .lpm-pricing-features li svg { color: var(--lp-minimal-color); margin-top: .1rem; flex-shrink: 0; }
            .lpm-pricing-cta { display: flex; width: 100%; justify-content: center; margin-top: 1.5rem; }
            @media (min-width: 768px) { .lpm-pricing-grid { grid-template-columns: repeat(3, 1fr); } }

            /* Stats */
            .lpm-stats { padding: 2.5rem 0; border-top: 1px solid var(--lpm-border); border-bottom: 1px solid var(--lpm-border); background: var(--lpm-surface); }
            .lpm-stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; text-align: center; }
            .lpm-stat { padding: 0 .5rem; }
            .lpm-stat-num { display: block; font-size: 1.6rem; font-weight: 700; color: var(--lp-minimal-color); }
            .lpm-stat-label { display: block; margin-top: .3rem; font-size: .76rem; color: var(--lpm-muted); }
            @media (min-width: 640px) { .lpm-stats-row { grid-template-columns: repeat(4, 1fr); } }

            /* Testimonials */
            .lpm-testimonials { padding: 4rem 0; }
            .lpm-testimonial { text-align: center; }
            .lpm-quote-icon { margin: 0 auto; color: color-mix(in srgb, var(--lp-minimal-color) 55%, var(--lpm-muted-2)); }
            .lpm-testimonial p { margin-top: 1.25rem; font-size: 1.05rem; line-height: 1.7; color: var(--lpm-ink); font-style: italic; }
            .lpm-testimonial-author { margin-top: 1.5rem; display: flex; align-items: center; justify-content: center; gap: .65rem; }
            .lpm-testimonial-author img { width: 40px; height: 40px; border-radius: 9999px; object-fit: cover; }
            .lpm-testimonial-avatar { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 9999px; background: color-mix(in srgb, var(--lp-minimal-color) 14%, #fff); color: var(--lp-minimal-color); font-weight: 700; font-size: .85rem; }
            .lpm-testimonial-author div { text-align: left; display: flex; flex-direction: column; }
            .lpm-testimonial-author strong { font-size: .84rem; color: var(--lpm-ink); }
            .lpm-testimonial-author span { font-size: .74rem; color: var(--lpm-muted-2); }
            .lpm-testimonial-dots { margin-top: 1.75rem; display: flex; align-items: center; justify-content: center; gap: .5rem; }
            .lpm-testimonial-dots button { width: 7px; height: 7px; border-radius: 9999px; background: var(--lpm-border); border: none; padding: 0; cursor: pointer; }
            .lpm-testimonial-dots button.is-active { background: var(--lp-minimal-color); width: 18px; }

            /* Venues */
            .lpm-venues { padding: 3.5rem 0 4rem; border-top: 1px solid var(--lpm-border); }
            .lpm-venues-grid { margin-top: 1.5rem; display: grid; grid-template-columns: 1fr; gap: 1.15rem; }
            .lpm-empty { padding: 3.5rem 0; text-align: center; font-size: .88rem; color: var(--lpm-muted-2); }
            @media (min-width: 640px) { .lpm-venues-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { .lpm-venues-grid { grid-template-columns: repeat(3, 1fr); } }

            /* News */
            .lpm-news { padding: 3.5rem 0; border-top: 1px solid var(--lpm-border); }
            .lpm-news-grid { display: grid; grid-template-columns: 1fr; gap: 1.15rem; }
            .lpm-news-card { display: block; border: 1px solid var(--lpm-border); border-radius: .9rem; overflow: hidden; text-decoration: none; background: var(--lpm-surface); }
            .lpm-news-card:hover { transform: translateY(-3px); border-color: color-mix(in srgb, var(--lp-minimal-color) 35%, var(--lpm-border)); }
            .lpm-news-media { height: 140px; overflow: hidden; }
            .lpm-news-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s ease; }
            .lpm-news-card:hover .lpm-news-media img { transform: scale(1.04); }
            .lpm-news-body { padding: 1.1rem 1.25rem; }
            .lpm-news-body h3 { font-size: .86rem; font-weight: 700; color: var(--lpm-ink); line-height: 1.4; }
            .lpm-news-body p { margin-top: .4rem; font-size: .78rem; color: var(--lpm-muted); line-height: 1.55; }
            @media (min-width: 640px) { .lpm-news-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { .lpm-news-grid { grid-template-columns: repeat(3, 1fr); } }

            /* FAQ */
            .lpm-faq { padding: 4rem 0; border-top: 1px solid var(--lpm-border); }
            .lpm-faq-inner { display: grid; grid-template-columns: 1fr; gap: 2rem; align-items: center; }
            .lpm-faq-media { min-height: 12rem; border-radius: 1rem; overflow: hidden; border: 1px solid var(--lpm-border); }
            .lpm-faq-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpm-faq-list { margin-top: 1.5rem; display: flex; flex-direction: column; }
            .lpm-faq-item { border-bottom: 1px solid var(--lpm-border); }
            .lpm-faq-item:first-child { border-top: 1px solid var(--lpm-border); }
            .lpm-faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.05rem 0; background: none; border: none; text-align: left; font-size: .87rem; font-weight: 600; color: var(--lpm-ink); cursor: pointer; }
            .lpm-faq-question svg { color: var(--lp-minimal-color); }
            .lpm-faq-answer { padding: 0 0 1.1rem; font-size: .83rem; color: var(--lpm-muted); line-height: 1.65; }
            @media (min-width: 900px) { .lpm-faq-inner { grid-template-columns: .8fr 1.2fr; } .lpm-faq-media { height: 100%; min-height: 22rem; } }

            /* CTA */
            .lpm-cta { padding: 4rem 0; border-top: 1px solid var(--lpm-border); text-align: center; }
            .lpm-cta h2 { font-size: 1.5rem; font-weight: 700; color: var(--lpm-ink); letter-spacing: -.01em; }
            .lpm-cta p { margin-top: .55rem; font-size: .88rem; color: var(--lpm-muted); }
            .lpm-cta .lpm-btn-primary { margin-top: 1.5rem; }

            /* Footer */
            .lpm-footer { background: var(--lpm-surface); border-top: 1px solid var(--lpm-border); padding: 3rem 0 1.5rem; }
            .lpm-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.25rem; }
            .lpm-footer-col { display: flex; flex-direction: column; gap: .6rem; }
            .lpm-footer-col h4 { font-size: .76rem; font-weight: 700; color: var(--lpm-ink); text-transform: uppercase; letter-spacing: .05em; margin-bottom: .3rem; }
            .lpm-footer-col a { font-size: .82rem; color: var(--lpm-muted); text-decoration: none; }
            .lpm-footer-col a:hover { color: var(--lp-minimal-color); }
            .lpm-footer-col-brand { max-width: 24rem; }
            .lpm-footer-brand { display: flex; align-items: center; gap: .6rem; }
            .lpm-footer-col-brand p { font-size: .82rem; color: var(--lpm-muted); line-height: 1.6; }
            .lpm-footer-social { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 9999px; border: 1px solid var(--lpm-border); color: var(--lpm-muted); }
            .lpm-footer-social:hover { border-color: var(--lp-minimal-color); color: var(--lp-minimal-color); }
            .lpm-footer-item { display: inline-flex; align-items: center; gap: .4rem; font-size: .82rem; color: var(--lpm-muted); text-decoration: none; }
            .lpm-footer-bottom { margin-top: 2.25rem; text-align: center; font-size: .72rem; color: var(--lpm-muted-2); border-top: 1px solid var(--lpm-border); padding-top: 1.25rem; }
            @media (min-width: 768px) { .lpm-footer-grid { grid-template-columns: 1.4fr 1fr 1fr; } }
        </style>
    @endonce
@endsection
