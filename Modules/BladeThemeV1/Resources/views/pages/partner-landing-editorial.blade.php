@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    {{-- Landing page RIÊNG cho 1 đối tác theo subdomain — biến thể "Editorial" (kiểu campaign
         thể thao/thời trang: typography khổng lồ, khối màu lớn, lưới bất đối xứng, cạnh chéo giữa
         các section) — CÙNG hợp đồng biến số và CÙNG độ "chịu lỗi" (mọi khối tuỳ chọn tự ẩn khi
         rỗng) với bản "bento" gốc (partner-landing.blade.php), nhưng ngôn ngữ thị giác hoàn toàn
         khác — không dùng chung 1 class CSS nào với bản gốc để không bao giờ đụng độ nếu cả 2 cùng
         tồn tại trong dự án. Đây là template thứ 2 cho tính năng landing_page, admin chọn template
         nào dùng template đó cho subdomain của mình. --}}
    <div class="partner-landing-editorial-scope" style="
        --lp-editorial-color: {{ $landingColor }};
        --lp-editorial-color-heavy: {{ $landingColorHeavy }};
        --lp-editorial-color-light: {{ $landingColorLight }};
    ">
        {{-- ── HEADER ─────────────────────────────────────────────────────────── --}}
        <header class="lpe-header">
            <div class="lpe-header-inner">
                <a href="#" class="lpe-brand">
                    @if ($partnerLogoUrl)
                        <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpe-brand-logo">
                    @else
                        <span class="lpe-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                    @endif
                    <span class="lpe-brand-name">{{ $partner->name }}</span>
                </a>

                <nav class="lpe-header-nav">
                    @if ($description)
                        <a href="#lpe-about" class="lpe-header-link">Giới thiệu</a>
                    @endif
                    <a href="#lpe-services" class="lpe-header-link">Dịch vụ</a>
                    @if ($pricingPlans->isNotEmpty())
                        <a href="#lpe-pricing" class="lpe-header-link">Bảng giá</a>
                    @endif
                    <a href="#lpe-faq" class="lpe-header-link">Hỏi đáp</a>
                    <a href="#lpe-contact" class="lpe-header-link">Liên hệ</a>
                    @if ($partner->phone)
                        <a href="tel:{{ $partner->phone }}" class="lpe-header-contact">
                            <x-heroicon-o-phone class="h-4 w-4" />
                            <span>{{ $partner->phone }}</span>
                        </a>
                    @endif
                    <a href="#lp-venues" class="lpe-header-cta">Đặt sân ngay</a>
                </nav>
            </div>
        </header>

        {{-- ── HERO — chữ khổng lồ tràn lên ảnh nền, khối màu lệch góc trên cùng ────────
             Ảnh chính ($heroImageUrl) làm nền full-bleed, headline cực lớn đặt đè lên trên bằng
             mix-blend/scrim; ảnh phụ ($heroSecondaryImageUrl) làm thẻ ảnh lệch góc phía dưới bên
             phải, chồng lên rìa dưới của khối hero (offset card) — dùng CẢ 2 ảnh, không bỏ ảnh nào. --}}
        <section class="lpe-hero">
            <div class="lpe-hero-media">
                @if ($heroImageUrl)
                    <img src="{{ $heroImageUrl }}" alt="{{ $partner->name }}" loading="eager">
                @else
                    <div class="lpe-hero-media-fallback"><x-heroicon-o-photo class="h-14 w-14" /></div>
                @endif
                <div class="lpe-hero-scrim"></div>
            </div>

            <div class="lpe-hero-inner">
                <span class="lpe-eyebrow lpe-eyebrow-light">{{ $partner->name }}</span>
                <h1 class="lpe-hero-title">{{ $tagline }}</h1>
                <p class="lpe-hero-desc">{{ $description ?: ('Đặt sân trực tuyến tại ' . $partner->name . ' — chọn chi nhánh, xem lịch trống theo thời gian thực và đặt lịch chỉ trong vài bước.') }}</p>
                <div class="lpe-hero-actions">
                    <a href="#lp-venues" class="lpe-btn-block">
                        <span>Đặt sân ngay</span>
                        <x-heroicon-o-arrow-right class="h-5 w-5" />
                    </a>
                    @if ($avgRating)
                        <div class="lpe-hero-rating">
                            <div class="lpe-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 {{ $i <= round($avgRating) ? '' : 'is-dim' }}" />
                                @endfor
                            </div>
                            <span>{{ number_format($avgRating, 1) }}/5 đánh giá</span>
                        </div>
                    @else
                        <div class="lpe-hero-rating">
                            <div class="lpe-hero-stars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <x-heroicon-s-star class="h-4 w-4 is-dim" />
                                @endfor
                            </div>
                            <span>Chưa có đánh giá</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Thẻ ảnh phụ lệch góc — chồng lên rìa dưới của hero, có nút Play video nếu có
                 landing_hero_youtube_id (dùng đúng pattern "lite youtube embed" của bản gốc: chỉ
                 tạo iframe SAU khi bấm, autoplay=1, không tự tải sẵn video nặng). --}}
            <div class="lpe-hero-card" @if ($heroYoutubeId) x-data="{ playing: false }" @endif>
                @if ($heroSecondaryImageUrl)
                    <img src="{{ $heroSecondaryImageUrl }}" alt="{{ $partner->name }}" loading="lazy" @if ($heroYoutubeId) x-show="!playing" @endif>
                @elseif ($heroYoutubeId)
                    <img src="https://i3.ytimg.com/vi/{{ $heroYoutubeId }}/hqdefault.jpg" alt="{{ $partner->name }}" x-show="!playing">
                @else
                    <div class="lpe-hero-card-fallback"><x-heroicon-o-photo class="h-8 w-8" /></div>
                @endif

                @if ($heroYoutubeId)
                    <div class="lpe-hero-card-scrim" x-show="!playing"></div>
                    <button type="button" class="lpe-hero-play-btn" x-show="!playing" @click="playing = true" aria-label="Xem video giới thiệu">
                        <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5"><path d="M8 5v14l11-7z" /></svg>
                    </button>
                    <template x-if="playing">
                        <iframe
                            class="lpe-hero-card-frame"
                            src="https://www.youtube-nocookie.com/embed/{{ $heroYoutubeId }}?autoplay=1&rel=0"
                            title="Video giới thiệu {{ $partner->name }}"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </template>
                @endif
            </div>
        </section>

        {{-- ── ĐIỂM NỔI BẬT — hàng danh sách đánh số lớn kiểu editorial, không dùng thẻ card ──── --}}
        <section id="lpe-features" class="lpe-features lp-reveal">
            <div class="lpe-features-inner">
                <div class="lpe-section-head">
                    <span class="lpe-eyebrow">Vì sao chọn chúng tôi</span>
                    <h2 class="lpe-section-title">Điều thú vị<br>về {{ $partner->name }}</h2>
                </div>
                <div class="lpe-features-list">
                    @foreach ($features as $i => $feature)
                        <div class="lpe-feature-row">
                            <span class="lpe-feature-index">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="lpe-feature-icon">
                                <x-dynamic-component :component="$feature['icon'] ?? 'heroicon-o-star'" class="h-7 w-7" />
                            </span>
                            <div class="lpe-feature-body">
                                <h3>{{ $feature['title'] }}</h3>
                                @if (! empty($feature['description']))
                                    <p>{{ $feature['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── GIỚI THIỆU (chỉ hiện khi có landing_description) — trích dẫn khổ lớn ─────────── --}}
        @if ($description)
            <section id="lpe-about" class="lpe-about lp-diagonal lp-reveal">
                <div class="lpe-about-inner">
                    <span class="lpe-eyebrow lpe-eyebrow-light">Câu chuyện của chúng tôi</span>
                    <p class="lpe-about-quote">&ldquo;{{ $description }}&rdquo;</p>
                    <span class="lpe-about-signature">— {{ $partner->name }}</span>
                </div>
            </section>
        @endif

        {{-- ── DỊCH VỤ — lưới bất đối xứng, thẻ đầu tiên lớn gấp đôi ─────────────────────────── --}}
        <section id="lpe-services" class="lpe-services lp-reveal">
            <div class="lpe-services-inner">
                <div class="lpe-section-head">
                    <span class="lpe-eyebrow">Dịch vụ</span>
                    <h2 class="lpe-section-title">Cung cấp dịch vụ<br>thuê sân tốt nhất</h2>
                </div>
                <div class="lpe-services-grid">
                    @foreach ($services as $i => $service)
                        <div class="lpe-service-card {{ $i === 0 ? 'is-feature' : '' }}">
                            <div class="lpe-service-media">
                                @if (! empty($service['image']))
                                    <img src="{{ $service['image'] }}" alt="{{ $service['title'] }}" loading="lazy">
                                @else
                                    <x-heroicon-o-photo class="h-9 w-9" />
                                @endif
                            </div>
                            <div class="lpe-service-body">
                                <h3>{{ $service['title'] }}</h3>
                                @if (! empty($service['description']))
                                    <p>{{ $service['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="lpe-section-cta">
                    <a href="#lp-venues" class="lpe-btn-outline">Đặt sân ngay</a>
                </div>
            </div>
        </section>

        {{-- ── BẢNG GIÁ (chỉ hiện khi có landing_pricing_plans) — khối màu chủ đạo, thẻ nổi bật lớn hơn hẳn ── --}}
        @if ($pricingPlans->isNotEmpty())
            <section id="lpe-pricing" class="lpe-pricing lp-diagonal lp-reveal">
                <div class="lpe-pricing-inner">
                    <div class="lpe-section-head">
                        <span class="lpe-eyebrow lpe-eyebrow-light">Bảng giá</span>
                        <h2 class="lpe-section-title lpe-section-title-light">Chọn gói<br>phù hợp với bạn</h2>
                    </div>
                    <div class="lpe-pricing-grid">
                        @foreach ($pricingPlans as $plan)
                            <div class="lpe-pricing-card {{ ! empty($plan['highlighted']) ? 'is-highlighted' : '' }}">
                                @if (! empty($plan['highlighted']))
                                    <span class="lpe-pricing-badge">Phổ biến nhất</span>
                                @endif
                                <h3>{{ $plan['name'] }}</h3>
                                <div class="lpe-pricing-amount">
                                    {{ $plan['price'] }}
                                    @if (! empty($plan['unit']))
                                        <span>{{ $plan['unit'] }}</span>
                                    @endif
                                </div>
                                @if (! empty($plan['features']))
                                    <ul class="lpe-pricing-features">
                                        @foreach ($plan['features'] as $line)
                                            <li><x-heroicon-s-check-circle class="h-4 w-4" />{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                <a href="#lp-venues" class="{{ ! empty($plan['highlighted']) ? 'lpe-btn-block' : 'lpe-btn-outline-light' }}">Đăng ký ngay</a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ── THÀNH TỰU — con số cực lớn trải hàng ngang, nền đen tương phản cao ─────────────── --}}
        <section class="lpe-stats lp-reveal">
            <div class="lpe-stats-inner">
                <div class="lpe-stats-head">
                    <span class="lpe-eyebrow lpe-eyebrow-light">Thành tựu</span>
                    <h2 class="lpe-section-title lpe-section-title-light">Con số biết nói</h2>
                    <p>Đồng hành cùng hàng nghìn khách hàng yêu thể thao, {{ $partner->name }} không ngừng nâng cao chất lượng dịch vụ.</p>
                </div>
                <div class="lpe-stats-grid">
                    @forelse ($stats as $stat)
                        <div class="lpe-stat">
                            <span class="lpe-stat-num">{{ $stat['number'] }}</span>
                            <span class="lpe-stat-label">{{ $stat['label'] }}</span>
                        </div>
                    @empty
                        <div class="lpe-stat">
                            <span class="lpe-stat-num">{{ $venues->count() }}</span>
                            <span class="lpe-stat-label">Chi nhánh</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        {{-- ── ĐÁNH GIÁ (chỉ hiện khi có landing_testimonials) — trích dẫn khổ lớn, chuyển ngang ─── --}}
        @if (! empty($testimonials))
            <section class="lpe-testimonials lp-reveal" x-data="{ active: 0, total: {{ count($testimonials) }} }">
                <div class="lpe-testimonials-inner">
                    <span class="lpe-eyebrow">Khách hàng nói gì</span>
                    <h2 class="lpe-section-title">Đánh giá từ khách hàng</h2>

                    @foreach ($testimonials as $i => $t)
                        <div class="lpe-testimonial" x-show="active === {{ $i }}" x-cloak>
                            <p class="lpe-testimonial-quote">&ldquo;{{ $t['quote'] }}&rdquo;</p>
                            <div class="lpe-testimonial-author">
                                @if (! empty($t['image']))
                                    <img src="{{ $t['image'] }}" alt="{{ $t['name'] }}" class="lpe-testimonial-avatar-img">
                                @else
                                    <span class="lpe-testimonial-avatar">{{ mb_substr($t['name'], 0, 1) }}</span>
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
                        <div class="lpe-testimonial-dots">
                            @foreach ($testimonials as $i => $t)
                                <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'is-active' : ''" aria-label="Đánh giá {{ $i + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- ── DANH SÁCH CHI NHÁNH — dùng chung component venues.card, id lp-venues (mục tiêu neo
             của mọi nút "Đặt sân ngay" trong trang) ─────────────────────────────────────────── --}}
        <section id="lp-venues" class="lpe-venues lp-reveal">
            <div class="lpe-venues-inner">
                @if ($venues->isNotEmpty())
                    <div class="lpe-section-head">
                        <span class="lpe-eyebrow">Chi nhánh</span>
                        <h2 class="lpe-section-title">Chi nhánh<br>của {{ $partner->name }}</h2>
                        <p class="lpe-section-sub">Chọn 1 chi nhánh để xem lịch trống và đặt sân ngay.</p>
                    </div>
                    <div class="lpe-venues-grid">
                        @foreach ($venues as $venue)
                            <x-bladethemev1::venues.card :venue="$venue" />
                        @endforeach
                    </div>
                @else
                    <div class="lpe-section-head">
                        <span class="lpe-eyebrow">Chi nhánh</span>
                        <h2 class="lpe-section-title">Chi nhánh<br>của {{ $partner->name }}</h2>
                    </div>
                    <p class="lpe-empty">{{ $partner->name }} hiện chưa có chi nhánh nào hiển thị.</p>
                @endif
            </div>
        </section>

        {{-- ── TIN TỨC / ƯU ĐÃI (chỉ hiện khi có landing_news) — thẻ lớn, nền tối ─────────────── --}}
        @if (! empty($news))
            <section class="lpe-news lp-reveal">
                <div class="lpe-news-inner">
                    <div class="lpe-section-head">
                        <span class="lpe-eyebrow lpe-eyebrow-light">Cập nhật</span>
                        <h2 class="lpe-section-title lpe-section-title-light">Tin tức &amp; ưu đãi<br>mới nhất</h2>
                    </div>
                    <div class="lpe-news-grid">
                        @foreach ($news as $item)
                            <a href="{{ $item['url'] ?: '#' }}" @if ($item['url']) target="_blank" rel="noopener" @endif class="lpe-news-card">
                                <div class="lpe-news-media">
                                    @if (! empty($item['image']))
                                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" loading="lazy">
                                    @else
                                        <x-heroicon-o-photo class="h-9 w-9" />
                                    @endif
                                </div>
                                <div class="lpe-news-body">
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

        {{-- ── FAQ — ảnh full-bleed bên trái + accordion đậm bên phải ────────────────────────── --}}
        <section id="lpe-faq" class="lpe-faq lp-reveal">
            <div class="lpe-faq-media">
                @if ($faqImageUrl)
                    <img src="{{ $faqImageUrl }}" alt="Câu hỏi thường gặp" loading="lazy">
                @else
                    <div class="lpe-faq-media-fallback"><x-heroicon-o-chat-bubble-left-right class="h-14 w-14" /></div>
                @endif
            </div>
            <div class="lpe-faq-content">
                <span class="lpe-eyebrow">Hỗ trợ</span>
                <h2 class="lpe-section-title">Các câu hỏi<br>thường gặp</h2>
                <div class="lpe-faq-list">
                    @foreach ($faqs as $i => $faq)
                        <div class="lpe-faq-item" x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }">
                            <button type="button" class="lpe-faq-question" @click="open = !open">
                                <span>{{ $faq['question'] }}</span>
                                <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 transition-transform" x-bind:style="open ? 'transform:rotate(180deg)' : ''" />
                            </button>
                            <div class="lpe-faq-answer" x-show="open" x-collapse x-cloak>{{ $faq['answer'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ── CTA CUỐI TRANG — chữ cực lớn tràn khổ, khối màu chủ đạo ────────────────────────── --}}
        <section class="lpe-cta">
            <div class="lpe-cta-inner">
                <h2>Sẵn sàng đặt sân<br>ngay hôm nay?</h2>
                <p>Chọn chi nhánh phù hợp và đặt lịch chỉ trong vài phút.</p>
                <a href="#lp-venues" class="lpe-btn-dark">
                    <span>Đặt sân ngay</span>
                    <x-heroicon-o-arrow-right class="h-5 w-5" />
                </a>
            </div>
        </section>

        {{-- ── FOOTER ──────────────────────────────────────────────────────────────────────── --}}
        <footer id="lpe-contact" class="lpe-footer">
            <div class="lpe-footer-inner">
                <div class="lpe-footer-grid">
                    <div class="lpe-footer-col lpe-footer-col-brand">
                        <div class="lpe-footer-brand">
                            @if ($partnerLogoUrl)
                                <img src="{{ $partnerLogoUrl }}" alt="{{ $partner->name }}" class="lpe-brand-logo">
                            @else
                                <span class="lpe-brand-badge">{{ mb_substr($partner->name, 0, 1) }}</span>
                            @endif
                            <span class="lpe-brand-name">{{ $partner->name }}</span>
                        </div>
                        <p>{{ $description ? \Illuminate\Support\Str::limit($description, 140) : ('Đặt sân thể thao trực tuyến tại ' . $partner->name . ' — nhanh chóng, tiện lợi, xác nhận tức thì.') }}</p>
                        @if ($facebookUrl)
                            <a href="{{ $facebookUrl }}" target="_blank" rel="noopener" class="lpe-footer-social" aria-label="Facebook">
                                <x-heroicon-o-globe-alt class="h-4 w-4" />
                            </a>
                        @endif
                    </div>

                    <div class="lpe-footer-col">
                        <h4>Liên kết nhanh</h4>
                        @if ($description)
                            <a href="#lpe-about">Giới thiệu</a>
                        @endif
                        <a href="#lpe-services">Dịch vụ</a>
                        <a href="#lp-venues">Chi nhánh &amp; đặt sân</a>
                        @if ($pricingPlans->isNotEmpty())
                            <a href="#lpe-pricing">Bảng giá</a>
                        @endif
                        <a href="#lpe-faq">Hỏi đáp</a>
                    </div>

                    <div class="lpe-footer-col">
                        <h4>Liên hệ</h4>
                        @if ($partner->address)
                            <span class="lpe-footer-item"><x-heroicon-o-map-pin class="h-4 w-4" />{{ $partner->address }}</span>
                        @endif
                        @if ($partner->phone)
                            <a href="tel:{{ $partner->phone }}" class="lpe-footer-item"><x-heroicon-o-phone class="h-4 w-4" />{{ $partner->phone }}</a>
                        @endif
                        @if ($partner->email)
                            <a href="mailto:{{ $partner->email }}" class="lpe-footer-item"><x-heroicon-o-envelope class="h-4 w-4" />{{ $partner->email }}</a>
                        @endif
                    </div>
                </div>

                <div class="lpe-footer-bottom">
                    © {{ now()->year }} {{ $partner->name }} — Cung cấp bởi {{ config('app.name') }}
                </div>
            </div>
        </footer>
    </div>

    @once
        <script>
            // Bóng đổ header khi cuộn xuống + hiệu ứng "fade lên khi cuộn tới" cho .lp-reveal —
            // thuần vanilla JS/IntersectionObserver (cùng pattern với bản landing "bento" gốc, an
            // toàn nếu JS bị chặn: phần tử vẫn có nội dung trong DOM, chỉ mất hiệu ứng).
            document.addEventListener('DOMContentLoaded', function () {
                var header = document.querySelector('.lpe-header');
                if (header) {
                    window.addEventListener('scroll', function () {
                        header.classList.toggle('is-scrolled', window.scrollY > 8);
                    }, { passive: true });
                }

                var revealTargets = document.querySelectorAll('.partner-landing-editorial-scope .lp-reveal');
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
            .partner-landing-editorial-scope { --color-primary: var(--lp-editorial-color); font-family: inherit; color: #0a0a0a; -webkit-font-smoothing: antialiased; overflow-x: hidden; }
            .partner-landing-editorial-scope * { scroll-behavior: smooth; }
            .partner-landing-editorial-scope a, .partner-landing-editorial-scope button { transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, opacity .18s ease, border-color .18s ease; }
            .partner-landing-editorial-scope img { max-width: 100%; display: block; }

            /* Eyebrow — nhãn nhỏ in hoa, viền trên gạch chân đậm kiểu tạp chí thay vì pill mềm. */
            {{-- QUAN TRỌNG: "--lp-editorial-color-heavy" thực chất là bản ĐÃ LÀM SÁNG 60% của màu
                 gốc (xem BladeThemeV1Controller::partnerLandingPage(), $landingColorHeavy =
                 lightenColor(...)), còn "-light" thực chất là bản ĐÃ LÀM TỐI 20% — tên biến NGƯỢC
                 với cảm giác thông thường ("heavy" nghe như đậm/tối hơn nhưng lại SÁNG hơn). Dùng
                 nhầm "-heavy" (sáng) làm màu CHỮ trên nền TRẮNG và màu gốc (tối) làm màu chữ trên
                 nền ĐEN là bug thật đã gặp — chữ gần cùng tông với nền, gần như không đọc được (báo
                 lỗi thật từ người dùng: "chữ bị chìm"). Sửa lại đúng chiều: nền trắng dùng "-light"
                 (tối hơn, tương phản tốt), nền đen dùng trắng thật (không phụ thuộc màu đối tác chọn
                 sáng/tối thế nào, luôn tương phản đảm bảo). --}}
            .lpe-eyebrow { display: inline-flex; align-items: center; gap: .5rem; font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .14em; color: var(--lp-editorial-color-light); padding-top: .5rem; border-top: 3px solid var(--lp-editorial-color); margin-bottom: 1rem; }
            .lpe-eyebrow-light { color: #fff; border-top-color: var(--lp-editorial-color-heavy); }

            .lpe-section-head { max-width: 40rem; }
            .lpe-section-title { font-size: clamp(1.9rem, 5vw, 3.2rem); font-weight: 900; line-height: 1.02; letter-spacing: -.02em; color: #0a0a0a; text-transform: uppercase; }
            .lpe-section-title-light { color: #fff; }
            .lpe-section-sub { margin-top: .85rem; font-size: .95rem; color: #52525b; max-width: 34rem; }

            .lp-reveal { opacity: 0; transform: translateY(30px); transition: opacity .7s ease, transform .7s ease; }
            .lp-reveal.is-visible { opacity: 1; transform: translateY(0); }
            .no-js .lp-reveal { opacity: 1; transform: none; }

            /* Cạnh chéo trang trí — dùng ::before phủ nền có clip-path, nội dung thật nằm trong lớp
               z-index cao hơn nên không bao giờ bị clip mất chữ (chỉ dải viền mỏng trên/dưới bị cắt
               chéo, section có đủ padding dọc để chữ luôn nằm an toàn bên trong). */
            .lp-diagonal { position: relative; }
            .lp-diagonal::before { content: ''; position: absolute; inset: 0; z-index: 0; clip-path: polygon(0 3.5vw, 100% 0, 100% 100%, 0 calc(100% - 3.5vw)); }
            .lp-diagonal > * { position: relative; z-index: 1; }

            /* Buttons */
            .lpe-btn-block { display: inline-flex; align-items: center; gap: .75rem; padding: 1rem 1.75rem; background: #0a0a0a; color: #fff; font-weight: 800; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; text-decoration: none; }
            .lpe-btn-block:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0 var(--lp-editorial-color); }
            .lpe-btn-dark { display: inline-flex; align-items: center; gap: .75rem; padding: 1rem 1.75rem; background: #fff; color: #0a0a0a; font-weight: 800; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; text-decoration: none; }
            .lpe-btn-dark:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0 rgba(0,0,0,.35); }
            .lpe-btn-outline { display: inline-flex; align-items: center; padding: .9rem 1.75rem; border: 2px solid #0a0a0a; color: #0a0a0a; font-weight: 800; font-size: .82rem; text-transform: uppercase; letter-spacing: .04em; text-decoration: none; }
            .lpe-btn-outline:hover { background: #0a0a0a; color: #fff; }
            .lpe-btn-outline-light { display: flex; justify-content: center; margin-top: 1.5rem; padding: .9rem 1.75rem; border: 2px solid rgba(255,255,255,.5); color: #fff; font-weight: 800; font-size: .82rem; text-transform: uppercase; letter-spacing: .04em; text-decoration: none; }
            .lpe-btn-outline-light:hover { border-color: #fff; background: rgba(255,255,255,.1); }

            /* Header */
            .lpe-header { position: sticky; top: 0; z-index: 40; background: #fff; border-bottom: 3px solid #0a0a0a; }
            .lpe-header.is-scrolled { box-shadow: 0 10px 24px rgba(0,0,0,.08); }
            .lpe-header-inner { max-width: 110rem; margin: 0 auto; padding: .9rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
            .lpe-brand { display: inline-flex; align-items: center; gap: .65rem; text-decoration: none; }
            .lpe-brand-badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--lp-editorial-color); color: #0a0a0a; font-weight: 900; font-size: 1.1rem; flex-shrink: 0; }
            .lpe-brand-logo { width: 40px; height: 40px; object-fit: cover; flex-shrink: 0; }
            .lpe-brand-name { font-weight: 900; font-size: 1.05rem; text-transform: uppercase; letter-spacing: -.01em; color: #0a0a0a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 40vw; }
            .lpe-header-nav { display: flex; align-items: center; gap: 1.6rem; }
            .lpe-header-link { display: none; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: #27272a; text-decoration: none; }
            .lpe-header-link:hover { color: var(--lp-editorial-color-light); }
            .lpe-header-contact { display: none; align-items: center; gap: .35rem; font-size: .82rem; font-weight: 700; color: #27272a; text-decoration: none; }
            .lpe-header-cta { display: inline-flex; align-items: center; padding: .6rem 1.25rem; background: var(--lp-editorial-color); color: #0a0a0a; font-size: .78rem; font-weight: 900; text-transform: uppercase; letter-spacing: .03em; text-decoration: none; white-space: nowrap; }
            .lpe-header-cta:hover { transform: translate(-2px, -2px); box-shadow: 4px 4px 0 #0a0a0a; }
            @media (min-width: 640px) { .lpe-header-contact { display: inline-flex; } }
            @media (min-width: 900px) { .lpe-header-link { display: inline-flex; } }

            /* Hero — ảnh full-bleed + headline khổng lồ đè lên, thẻ ảnh phụ lệch góc chồng đáy. */
            .lpe-hero { position: relative; min-height: 34rem; display: flex; align-items: flex-end; padding: 4rem 1rem 6rem; overflow: visible; }
            .lpe-hero-media { position: absolute; inset: 0; overflow: hidden; background: #0a0a0a; }
            .lpe-hero-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lpe-hero-media-fallback { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.25); }
            .lpe-hero-scrim { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.35) 0%, rgba(0,0,0,.35) 30%, rgba(0,0,0,.85) 100%); }
            .lpe-hero-inner { position: relative; z-index: 1; max-width: 110rem; margin: 0 auto; width: 100%; }
            .lpe-hero-title { font-size: clamp(2.6rem, 9vw, 5.75rem); font-weight: 900; line-height: .96; letter-spacing: -.03em; text-transform: uppercase; color: #fff; max-width: 52rem; text-wrap: balance; }
            .lpe-hero-desc { margin-top: 1.25rem; max-width: 34rem; font-size: 1rem; line-height: 1.7; color: rgba(255,255,255,.85); }
            .lpe-hero-actions { margin-top: 2rem; display: flex; flex-wrap: wrap; align-items: center; gap: 1.5rem; }
            .lpe-hero-rating { display: flex; align-items: center; gap: .6rem; color: #fff; font-size: .85rem; font-weight: 700; }
            .lpe-hero-stars { display: flex; gap: .15rem; color: var(--lp-editorial-color); }
            .lpe-hero-stars svg.is-dim { color: rgba(255,255,255,.3); }

            .lpe-hero-card { position: absolute; right: 1rem; bottom: -4.5rem; z-index: 2; width: min(60vw, 15rem); aspect-ratio: 4/5; overflow: hidden; background: #111; border: 6px solid #fff; box-shadow: 0 20px 45px rgba(0,0,0,.35); }
            .lpe-hero-card img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
            .lpe-hero-card-fallback { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.3); }
            .lpe-hero-card-scrim { position: absolute; inset: 0; background: rgba(0,0,0,.25); }
            .lpe-hero-card-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; z-index: 2; }
            .lpe-hero-play-btn { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 54px; height: 54px; border-radius: 9999px; background: var(--lp-editorial-color); color: #0a0a0a; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 8px 22px rgba(0,0,0,.4); }
            .lpe-hero-play-btn:hover { transform: translate(-50%, -50%) scale(1.08); }

            @media (min-width: 768px) {
                .lpe-hero { min-height: 40rem; padding: 6rem 2rem 8rem; }
                .lpe-hero-card { right: 3rem; bottom: -5rem; width: 18rem; }
            }
            @media (min-width: 1024px) {
                .lpe-hero-inner { padding-right: 22rem; }
            }

            /* Spacer để đẩy nội dung dưới hero xuống, tránh bị thẻ ảnh lệch góc (position:absolute,
               tràn ra ngoài .lpe-hero) đè lên phần đầu section kế tiếp. */
            .lpe-hero + section, .lpe-hero + #lpe-about { margin-top: 5.5rem; }
            @media (min-width: 768px) { .lpe-hero + section, .lpe-hero + #lpe-about { margin-top: 6.5rem; } }

            /* Features — danh sách hàng đánh số lớn */
            .lpe-features { padding: 5rem 1rem; background: #fff; }
            .lpe-features-inner { max-width: 78rem; margin: 0 auto; }
            .lpe-features-list { margin-top: 3rem; display: flex; flex-direction: column; }
            .lpe-feature-row { display: grid; grid-template-columns: auto auto 1fr; align-items: center; gap: 1.25rem; padding: 1.75rem 0; border-top: 1px solid #e4e4e7; }
            .lpe-feature-row:last-child { border-bottom: 1px solid #e4e4e7; }
            .lpe-feature-index { font-size: 1.6rem; font-weight: 900; color: color-mix(in srgb, var(--lp-editorial-color) 60%, #a1a1aa); letter-spacing: -.02em; }
            .lpe-feature-icon { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; background: var(--lp-editorial-color-light); color: var(--lp-editorial-color-heavy); }
            .lpe-feature-body h3 { font-size: 1.05rem; font-weight: 800; color: #0a0a0a; }
            .lpe-feature-body p { margin-top: .35rem; font-size: .85rem; color: #52525b; line-height: 1.65; }
            @media (max-width: 639px) { .lpe-feature-row { grid-template-columns: auto 1fr; } .lpe-feature-index { display: none; } }

            /* About — trích dẫn lớn, khối màu chéo */
            .lpe-about { padding: 6rem 1rem; background: #0a0a0a; }
            .lpe-about::before { background: linear-gradient(135deg, var(--lp-editorial-color-heavy) 0%, var(--lp-editorial-color) 100%); }
            .lpe-about-inner { max-width: 52rem; margin: 0 auto; text-align: center; }
            {{-- BUG NẶNG NHẤT đã sửa: chữ trích dẫn trước đây màu GẦN ĐEN (#0a0a0a) trên nền
                 CŨNG GẦN ĐEN (.lpe-about có background:#0a0a0a) — chữ hoàn toàn vô hình. Đổi hẳn
                 sang trắng, đảm bảo đọc được bất kể góc nào của gradient màu (::before phía trên)
                 đang hiện ở vị trí chữ. --}}
            .lpe-about-quote { font-size: clamp(1.5rem, 3.6vw, 2.4rem); font-weight: 800; line-height: 1.3; color: #fff; letter-spacing: -.01em; white-space: pre-line; text-shadow: 0 2px 18px rgba(0,0,0,.25); }
            .lpe-about-signature { display: inline-block; margin-top: 1.5rem; font-size: .85rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,.75); }

            /* Services — lưới bất đối xứng, thẻ đầu lớn hơn */
            .lpe-services { padding: 5.5rem 1rem; background: #f4f4f5; }
            .lpe-services-inner { max-width: 96rem; margin: 0 auto; }
            .lpe-services-grid { margin-top: 3rem; display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
            .lpe-service-card { position: relative; background: #0a0a0a; overflow: hidden; min-height: 16rem; display: flex; align-items: flex-end; }
            .lpe-service-card.is-feature { min-height: 22rem; }
            .lpe-service-media { position: absolute; inset: 0; }
            .lpe-service-media img { width: 100%; height: 100%; object-fit: cover; transition: transform .5s ease; }
            .lpe-service-card:hover .lpe-service-media img { transform: scale(1.06); }
            .lpe-service-media svg { position: absolute; inset: 0; margin: auto; color: rgba(255,255,255,.25); }
            .lpe-service-card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(0,0,0,.88) 100%); }
            .lpe-service-body { position: relative; z-index: 1; padding: 1.5rem; }
            .lpe-service-body h3 { font-size: 1.15rem; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: -.01em; }
            .lpe-service-body p { margin-top: .5rem; font-size: .82rem; color: rgba(255,255,255,.8); line-height: 1.6; }
            .lpe-section-cta { margin-top: 2.5rem; }
            @media (min-width: 768px) { .lpe-services-grid { grid-template-columns: repeat(2, 1fr); } .lpe-service-card.is-feature { grid-column: span 2; } }
            @media (min-width: 1024px) { .lpe-services-grid { grid-template-columns: repeat(3, 1fr); } .lpe-service-card.is-feature { grid-column: span 3; } }

            /* Pricing — khối màu chủ đạo, thẻ nổi bật lớn hơn hẳn */
            .lpe-pricing { padding: 6.5rem 1rem; background: #0a0a0a; }
            .lpe-pricing::before { background: linear-gradient(120deg, var(--lp-editorial-color-heavy) 0%, #0a0a0a 65%); }
            .lpe-pricing-inner { max-width: 96rem; margin: 0 auto; }
            .lpe-pricing-grid { margin-top: 3rem; display: grid; grid-template-columns: 1fr; gap: 1.5rem; }
            .lpe-pricing-card { position: relative; background: #17171a; border: 1px solid #2a2a2e; padding: 2.25rem 1.85rem; color: #fff; }
            .lpe-pricing-card.is-highlighted { background: var(--lp-editorial-color); color: #0a0a0a; transform: scale(1.02); border-color: transparent; }
            .lpe-pricing-badge { position: absolute; top: -13px; left: 1.85rem; padding: .3rem .9rem; background: #0a0a0a; color: var(--lp-editorial-color-heavy); font-size: .66rem; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; }
            .lpe-pricing-card h3 { font-size: 1.05rem; font-weight: 800; text-transform: uppercase; }
            .lpe-pricing-amount { margin-top: .6rem; font-size: 2rem; font-weight: 900; letter-spacing: -.02em; }
            .lpe-pricing-amount span { font-size: .8rem; font-weight: 600; opacity: .65; }
            .lpe-pricing-features { margin-top: 1.5rem; display: flex; flex-direction: column; gap: .6rem; }
            .lpe-pricing-features li { display: flex; align-items: flex-start; gap: .55rem; font-size: .82rem; line-height: 1.5; }
            .lpe-pricing-card:not(.is-highlighted) .lpe-pricing-features li svg { color: var(--lp-editorial-color-heavy); margin-top: .1rem; }
            .lpe-pricing-card.is-highlighted .lpe-pricing-features li svg { color: #0a0a0a; margin-top: .1rem; }
            .lpe-pricing-card .lpe-btn-block { display: flex; width: 100%; justify-content: center; margin-top: 1.75rem; }
            @media (min-width: 768px) { .lpe-pricing-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { .lpe-pricing-grid { grid-template-columns: repeat(3, 1fr); } }

            /* Stats — con số khổng lồ, nền đen */
            .lpe-stats { padding: 5.5rem 1rem; background: #0a0a0a; }
            .lpe-stats-inner { max-width: 96rem; margin: 0 auto; }
            .lpe-stats-head p { margin-top: .85rem; font-size: .9rem; color: rgba(255,255,255,.65); max-width: 34rem; }
            .lpe-stats-grid { margin-top: 3rem; display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem 1.5rem; }
            .lpe-stat { border-top: 3px solid var(--lp-editorial-color-heavy); padding-top: 1rem; }
            .lpe-stat-num { display: block; font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 900; letter-spacing: -.02em; color: #fff; line-height: 1; }
            .lpe-stat-label { display: block; margin-top: .5rem; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: rgba(255,255,255,.55); }
            @media (min-width: 768px) { .lpe-stats-grid { grid-template-columns: repeat(4, 1fr); } }

            /* Testimonials — trích dẫn khổ lớn, nền sáng tương phản với stats/pricing tối liền kề */
            .lpe-testimonials { padding: 6rem 1rem; background: #fff; }
            .lpe-testimonials-inner { max-width: 60rem; margin: 0 auto; text-align: center; }
            .lpe-testimonial-quote { margin-top: 1.5rem; font-size: clamp(1.3rem, 3vw, 1.85rem); font-weight: 700; line-height: 1.45; color: #0a0a0a; letter-spacing: -.01em; }
            .lpe-testimonial-author { margin-top: 2rem; display: flex; align-items: center; justify-content: center; gap: .85rem; }
            .lpe-testimonial-avatar-img { width: 52px; height: 52px; border-radius: 9999px; object-fit: cover; }
            .lpe-testimonial-avatar { display: inline-flex; align-items: center; justify-content: center; width: 52px; height: 52px; border-radius: 9999px; background: var(--lp-editorial-color); color: #0a0a0a; font-weight: 800; font-size: 1.2rem; }
            .lpe-testimonial-author div { text-align: left; }
            .lpe-testimonial-author strong { display: block; font-size: .88rem; color: #0a0a0a; }
            .lpe-testimonial-author span { display: block; font-size: .76rem; color: #71717a; }
            .lpe-testimonial-dots { margin-top: 2rem; display: flex; align-items: center; justify-content: center; gap: .5rem; }
            .lpe-testimonial-dots button { width: 10px; height: 10px; background: #d4d4d8; border: none; padding: 0; cursor: pointer; }
            .lpe-testimonial-dots button.is-active { background: var(--lp-editorial-color); width: 26px; }

            /* Venues */
            .lpe-venues { padding: 5.5rem 1rem 6rem; background: #f4f4f5; }
            .lpe-venues-inner { max-width: 110rem; margin: 0 auto; }
            .lpe-venues-grid { margin-top: 2.5rem; display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
            .lpe-empty { margin-top: 2.5rem; padding: 3.5rem 1.5rem; text-align: center; font-size: .9rem; color: #71717a; border: 2px dashed #d4d4d8; }
            @media (min-width: 640px) { .lpe-venues-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { .lpe-venues-grid { grid-template-columns: repeat(3, 1fr); } }

            /* News */
            .lpe-news { padding: 5.5rem 1rem; background: #0a0a0a; }
            .lpe-news-inner { max-width: 96rem; margin: 0 auto; }
            .lpe-news-grid { margin-top: 2.5rem; display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
            .lpe-news-card { display: block; background: #17171a; border: 1px solid #2a2a2e; overflow: hidden; text-decoration: none; transition: transform .25s ease, border-color .25s ease; }
            .lpe-news-card:hover { transform: translateY(-4px); border-color: var(--lp-editorial-color); }
            .lpe-news-media { height: 11rem; background: #202024; display: flex; align-items: center; justify-content: center; overflow: hidden; color: rgba(255,255,255,.3); }
            .lpe-news-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpe-news-body { padding: 1.35rem 1.5rem; }
            .lpe-news-body h3 { font-size: .95rem; font-weight: 800; color: #fff; line-height: 1.4; text-transform: uppercase; }
            .lpe-news-body p { margin-top: .5rem; font-size: .8rem; color: rgba(255,255,255,.6); line-height: 1.6; }
            @media (min-width: 640px) { .lpe-news-grid { grid-template-columns: repeat(2, 1fr); } }
            @media (min-width: 1024px) { .lpe-news-grid { grid-template-columns: repeat(3, 1fr); } }

            /* FAQ — ảnh full-bleed trái, accordion đậm phải */
            .lpe-faq { display: grid; grid-template-columns: 1fr; }
            .lpe-faq-media { min-height: 18rem; background: #0a0a0a; display: flex; align-items: center; justify-content: center; overflow: hidden; color: rgba(255,255,255,.25); }
            .lpe-faq-media img { width: 100%; height: 100%; object-fit: cover; }
            .lpe-faq-content { padding: 4rem 1rem; background: #fff; }
            .lpe-faq-list { margin-top: 2.25rem; display: flex; flex-direction: column; gap: 0; }
            .lpe-faq-item { border-top: 1px solid #e4e4e7; }
            .lpe-faq-item:last-child { border-bottom: 1px solid #e4e4e7; }
            .lpe-faq-question { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.35rem 0; background: none; border: none; text-align: left; font-size: .95rem; font-weight: 800; color: #0a0a0a; cursor: pointer; }
            .lpe-faq-question svg { color: var(--lp-editorial-color-light); }
            .lpe-faq-answer { padding: 0 0 1.5rem; font-size: .85rem; color: #52525b; line-height: 1.7; }
            @media (min-width: 900px) { .lpe-faq { grid-template-columns: 1fr 1fr; } .lpe-faq-media { min-height: 100%; } .lpe-faq-content { padding: 5rem 4rem 5rem 3.5rem; } }

            /* CTA cuối trang — khối màu chủ đạo tràn khổ */
            .lpe-cta { padding: 6rem 1rem; text-align: center; background: linear-gradient(135deg, var(--lp-editorial-color) 0%, var(--lp-editorial-color-heavy) 100%); }
            .lpe-cta-inner { max-width: 46rem; margin: 0 auto; }
            .lpe-cta h2 { font-size: clamp(2.2rem, 6vw, 3.6rem); font-weight: 900; line-height: 1.02; letter-spacing: -.02em; text-transform: uppercase; color: #0a0a0a; }
            .lpe-cta p { margin-top: 1rem; font-size: .95rem; color: rgba(10,10,10,.75); }
            .lpe-cta .lpe-btn-dark { margin-top: 2rem; }

            /* Footer */
            .lpe-footer { background: #0a0a0a; color: #d4d4d8; padding: 4rem 1rem 1.75rem; }
            .lpe-footer-inner { max-width: 110rem; margin: 0 auto; }
            .lpe-footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; }
            .lpe-footer-col { display: flex; flex-direction: column; align-items: flex-start; gap: .65rem; }
            .lpe-footer-col h4 { font-size: .78rem; font-weight: 900; color: #fff; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .35rem; }
            .lpe-footer-col a { font-size: .85rem; color: #a1a1aa; text-decoration: none; }
            .lpe-footer-col a:hover { color: var(--lp-editorial-color-heavy); }
            .lpe-footer-col-brand { max-width: 24rem; }
            .lpe-footer-brand { display: flex; align-items: center; gap: .65rem; }
            .lpe-footer-brand .lpe-brand-name { color: #fff; }
            .lpe-footer-col-brand p { font-size: .85rem; color: #a1a1aa; line-height: 1.65; }
            .lpe-footer-social { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; background: #17171a; color: #d4d4d8; }
            .lpe-footer-social:hover { background: var(--lp-editorial-color); color: #0a0a0a; }
            .lpe-footer-item { display: inline-flex; align-items: center; gap: .45rem; font-size: .85rem; color: #a1a1aa; text-decoration: none; }
            .lpe-footer-bottom { margin-top: 3rem; font-size: .74rem; color: #71717a; border-top: 1px solid #26262a; padding-top: 1.5rem; }
            @media (min-width: 768px) { .lpe-footer-grid { grid-template-columns: 1.4fr 1fr 1fr; } }
        </style>
    @endonce
@endsection
