<div class="bg-white" x-data="{ showModal: false, slotPickerOpen: true }" data-product-id="{{ $product->id ?? '' }}">
    @if (session('booking_conflict_error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 12000)"
            x-transition:leave="transition ease-in duration-500" x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-4"
            class="fixed top-4 left-1/2 -translate-x-1/2 z-[9999] w-full max-w-xl px-4">
            <div class="flex items-start gap-3 bg-red-600 text-white rounded-xl shadow-2xl px-5 py-4">
                <svg class="w-6 h-6 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                    stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <div class="flex-1 text-sm font-medium leading-snug">
                    {{ session('booking_conflict_error') }}
                </div>
                <button @click="show = false" class="text-white/70 hover:text-white ml-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    @endif

    <div class="max-w-7xl md:px-8 px-4 mx-auto booking-confirmation-modal">
        <div id="seo-data" data-seo-title="{{ $product->name ?? 'Sân' }}"
            data-seo-description="{{ $short_description ?? 'Sân' }}"
            data-seo-keyword="{{ $product->seo_keywords ?? 'Sân' }}">
        </div>
        <div class="pt-6 md:pt-10 pb-6">
            <div>
                <div class="p-3 lg:px-0 overflow-hidden rounded-none bg-white">

                    {{-- Tiêu đề + hành động (desktop) --}}
                    <div class="hidden md:flex items-start justify-between gap-4 mb-4">
                        <h1 class="text-2xl font-semibold text-[#222222] leading-snug">
                            {{ $product->name ?? 'Tên sản phẩm không có' }}
                        </h1>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" onclick="pdShareRoom()"
                                class="inline-flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-sm font-semibold text-[#222222] underline underline-offset-2 hover:bg-[#F7F7F7] transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                    <polyline points="16 6 12 2 8 6"></polyline>
                                    <line x1="12" x2="12" y1="2" y2="15"></line>
                                </svg>
                                Chia sẻ
                            </button>
                            <button type="button" id="pd-wishlist-btn" data-product-id="{{ $product->id }}"
                                data-active="0" aria-label="Yêu thích"
                                class="inline-flex h-7 items-center gap-1.5 rounded-lg px-2.5 text-sm font-semibold text-[#222222] underline underline-offset-2 hover:bg-[#F7F7F7] transition-colors">
                                <svg id="pd-wishlist-icon" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                                </svg>
                                Lưu
                            </button>
                        </div>
                    </div>

                    {{-- Thanh trên cùng (mobile): quay lại + chia sẻ + yêu thích --}}
                    <div class="md:hidden flex items-center justify-between px-0 py-3 bg-white -mt-3 mb-0">
                        <button type="button" onclick="history.back()"
                            class="h-8 w-8 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[#222222]" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="m15 18-6-6 6-6"></path>
                            </svg>
                        </button>
                        <div class="flex gap-3">
                            <button type="button" onclick="pdShareRoom()"
                                class="h-8 w-8 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#222222]" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                                    <polyline points="16 6 12 2 8 6"></polyline>
                                    <line x1="12" x2="12" y1="2" y2="15"></line>
                                </svg>
                            </button>
                            <button type="button" id="pd-wishlist-btn-mobile" data-product-id="{{ $product->id }}"
                                data-active="0" aria-label="Yêu thích"
                                class="h-8 w-8 flex items-center justify-center">
                                <svg id="pd-wishlist-icon-mobile" xmlns="http://www.w3.org/2000/svg"
                                    class="h-5 w-5 text-[#222222]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Ảnh sân --}}
                    @php
                        $mainImg = $product->hasMedia('Ảnh bìa')
                            ? $product->getFirstMedia('Ảnh bìa')->getUrl()
                            : 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=900&q=80';
                        $galleryImgs = $mediaSecond ?? collect([]);
                        $allImgs = collect([$mainImg])->merge($galleryImgs->map(fn($m) => $m->getUrl()));
                        $totalImgCount = $allImgs->count();
                        $videoSettingPd = is_array($product->setting_video_room) ? $product->setting_video_room : [];
                        $hasVideoPd = !empty(trim($videoSettingPd['url'] ?? ''));
                    @endphp

                    <style>
                        /* --- single image --- */
                        .pd-single-wrap {
                            position: relative;
                            border-radius: 16px;
                            overflow: hidden;
                            margin-bottom: 1.5rem;
                            height: 420px;
                        }

                        .pd-single-wrap img {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            display: block;
                        }

                        /* --- gallery grid (multiple) --- */
                        .pd-gallery-grid {
                            display: grid;
                            grid-template-columns: 3fr 2fr;
                            grid-template-rows: 200px 200px;
                            gap: 6px;
                            border-radius: 16px;
                            overflow: hidden;
                            margin-bottom: 1.5rem;
                        }

                        .pd-gallery-grid .pd-main {
                            grid-row: 1 / 3;
                            position: relative;
                            overflow: hidden;
                        }

                        .pd-gallery-grid .pd-sub {
                            overflow: hidden;
                            position: relative;
                        }

                        .pd-gallery-grid img {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            display: block;
                            transition: transform .35s;
                            cursor: pointer;
                        }

                        .pd-gallery-grid img:hover {
                            transform: scale(1.04);
                        }

                        /* --- view / action btns row --- */
                        .pd-view-btn {
                            position: relative;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            background: rgba(255, 255, 255, .92);
                            backdrop-filter: blur(4px);
                            border: 1px solid rgba(0, 0, 0, .12);
                            border-radius: 10px;
                            padding: 7px 14px;
                            font-size: 13px;
                            font-weight: 600;
                            color: #222;
                            cursor: pointer;
                            transition: all .2s;
                            box-shadow: 0 2px 8px rgba(0, 0, 0, .12);
                        }

                        .pd-view-btn:hover {
                            background: #fff;
                            box-shadow: 0 4px 16px rgba(0, 0, 0, .18);
                        }

                        /* --- lightbox overlay --- */
                        .pd-lightbox-overlay {
                            position: fixed;
                            inset: 0;
                            z-index: 9999;
                            background: rgba(0, 0, 0, .92);
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            justify-content: center;
                        }

                        .pd-lightbox-main {
                            flex: 1;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            width: 100%;
                            padding: 0 60px;
                            box-sizing: border-box;
                            min-height: 0;
                        }

                        .pd-lightbox-main img {
                            max-width: 100%;
                            max-height: calc(100vh - 180px);
                            object-fit: contain;
                            border-radius: 8px;
                            user-select: none;
                            -webkit-user-drag: none;
                        }

                        .pd-lightbox-arrow {
                            position: absolute;
                            top: 50%;
                            transform: translateY(-50%);
                            background: rgba(255, 255, 255, .15);
                            border: none;
                            color: #fff;
                            border-radius: 50%;
                            width: 46px;
                            height: 46px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            cursor: pointer;
                            transition: background .2s;
                            z-index: 2;
                        }

                        .pd-lightbox-arrow:hover {
                            background: rgba(255, 255, 255, .3);
                        }

                        .pd-lightbox-arrow.left {
                            left: 10px;
                        }

                        .pd-lightbox-arrow.right {
                            right: 10px;
                        }

                        .pd-lightbox-close {
                            position: absolute;
                            top: 14px;
                            right: 18px;
                            background: none;
                            border: none;
                            color: #fff;
                            font-size: 30px;
                            line-height: 1;
                            cursor: pointer;
                            opacity: .8;
                        }

                        .pd-lightbox-close:hover {
                            opacity: 1;
                        }

                        .pd-lightbox-counter {
                            color: rgba(255, 255, 255, .7);
                            font-size: 13px;
                            position: absolute;
                            top: 20px;
                            left: 50%;
                            transform: translateX(-50%);
                        }

                        .pd-lightbox-thumbs {
                            display: flex;
                            gap: 8px;
                            padding: 12px 16px;
                            overflow-x: auto;
                            max-width: 100%;
                        }

                        .pd-lightbox-thumbs::-webkit-scrollbar {
                            height: 4px;
                        }

                        .pd-lightbox-thumbs::-webkit-scrollbar-thumb {
                            background: rgba(255, 255, 255, .3);
                            border-radius: 4px;
                        }

                        .pd-thumb {
                            flex-shrink: 0;
                            width: 72px;
                            height: 54px;
                            border-radius: 6px;
                            overflow: hidden;
                            border: 2px solid transparent;
                            cursor: pointer;
                            transition: border-color .2s;
                        }

                        .pd-thumb.active {
                            border-color: #fff;
                        }

                        .pd-thumb img {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            display: block;
                        }

                        @media (max-width: 640px) {
                            .pd-gallery-grid {
                                grid-template-columns: 3fr 2fr;
                                grid-template-rows: 150px 150px;
                            }

                            .pd-single-wrap {
                                height: 260px;
                            }

                            .pd-lightbox-main {
                                padding: 0 44px;
                            }
                        }

                        @media (min-width: 768px) {
                            .pd-mobile-only {
                                display: none !important;
                            }
                        }

                        @media (max-width: 767px) {
                            .pd-from-book-hide {
                                display: none !important;
                            }
                        }

                        /* --- mobile slider: video (nếu có) + ảnh, vuốt ngang bằng scroll-snap
                             gốc trình duyệt (không cần JS kéo tay) --- */
                        .pd-mobile-slider {
                            display: flex;
                            width: 100%;
                            height: 100%;
                            overflow-x: auto;
                            scroll-snap-type: x mandatory;
                            -webkit-overflow-scrolling: touch;
                            scrollbar-width: none;
                            -ms-overflow-style: none;
                        }

                        .pd-mobile-slider::-webkit-scrollbar {
                            display: none;
                        }

                        .pd-mobile-slide {
                            flex: 0 0 100%;
                            width: 100%;
                            height: 100%;
                            scroll-snap-align: start;
                            position: relative;
                        }

                        .pd-mobile-slide img {
                            width: 100%;
                            height: 100%;
                            object-fit: cover;
                            display: block;
                        }

                        .pd-mobile-play-btn {
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            width: 60px;
                            height: 60px;
                            border-radius: 50%;
                            background: rgba(255, 255, 255, .94);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            box-shadow: 0 4px 20px rgba(0, 0, 0, .35);
                        }

                        .pd-mobile-play-btn svg {
                            width: 24px;
                            height: 24px;
                            /* Lệch phải nhẹ để icon tam giác trông cân giữa hình tròn (điểm nặng
                               thị giác của tam giác lệch trái so với tâm hình học của nó). */
                            margin-left: 3px;
                        }
                    </style>

                    {{-- Alpine gallery component --}}
                    <div x-data="{
                        open: false,
                        current: 0,
                        images: {{ json_encode($allImgs->values()->all()) }},
                        show(idx) {
                            this.current = idx;
                            this.open = true;
                            document.body.style.overflow = 'hidden';
                        },
                        close() {
                            this.open = false;
                            document.body.style.overflow = '';
                        },
                        prev() { this.current = (this.current - 1 + this.images.length) % this.images.length; },
                        next() { this.current = (this.current + 1) % this.images.length; },
                        onKey(e) {
                            if (!this.open) return;
                            if (e.key === 'ArrowLeft') this.prev();
                            if (e.key === 'ArrowRight') this.next();
                            if (e.key === 'Escape') this.close();
                        }
                    }" @keydown.window="onKey($event)">

                        {{-- Ảnh + video chính — mobile: slider vuốt ngang (scroll-snap gốc trình
                             duyệt), video (nếu có) là slide đầu tiên với nút play ở giữa, vuốt
                             sang phải thì thấy ảnh. Thay cho ảnh tĩnh + nút "Xem video" riêng
                             trước đây (đã bỏ 2 nút "Xem ảnh"/"Xem video" ở mobile). --}}
                        @php $pdMobileSlideCount = $totalImgCount + ($hasVideoPd ? 1 : 0); @endphp
                        <div class="pd-single-wrap md:hidden" x-data="{ slideIdx: 0 }">
                            <div class="pd-mobile-slider"
                                @scroll="slideIdx = Math.round($event.target.scrollLeft / $event.target.clientWidth)">
                                @if ($hasVideoPd)
                                    <div class="pd-mobile-slide" style="cursor:pointer;"
                                        @click="$dispatch('pd-open-video')">
                                        <img src="{{ $mainImg }}" alt="{{ $product->name ?? 'Video sân' }}">
                                        <span class="pd-mobile-play-btn">
                                            <svg viewBox="0 0 24 24" fill="#111827">
                                                <polygon points="6,4 20,12 6,20" />
                                            </svg>
                                        </span>
                                    </div>
                                @endif
                                @foreach ($allImgs->values() as $i => $img)
                                    <div class="pd-mobile-slide"
                                        @if ($totalImgCount > 1) style="cursor:pointer;" @click="show({{ $i }})" @endif>
                                        <img src="{{ $img }}" alt="{{ $product->name ?? 'Ảnh' }} {{ $i + 1 }}">
                                    </div>
                                @endforeach
                            </div>
                            @if ($pdMobileSlideCount > 1)
                                <div
                                    class="absolute bottom-5 right-3 z-10 rounded-full bg-black/50 px-2.5 py-0.5 text-xs text-white font-medium"
                                    x-text="(slideIdx + 1) + '/' + {{ $pdMobileSlideCount }}"></div>
                            @endif
                        </div>

                        {{-- Ảnh sân — desktop: lưới ảnh --}}
                        <div class="hidden md:grid relative grid-cols-4 grid-rows-2 gap-2 rounded-xl overflow-hidden mb-6"
                            style="height:420px;">
                            <div class="col-span-2 row-span-2 relative">
                                <img src="{{ $mainImg }}" alt="{{ $product->name ?? 'Ảnh chính' }}"
                                    class="absolute inset-0 w-full h-full object-cover"
                                    @if ($totalImgCount > 1) @click="show(0)" style="cursor:pointer;" @endif>
                            </div>
                            @for ($i = 1; $i <= 4; $i++)
                                <div class="relative">
                                    @if ($allImgs->has($i))
                                        <img src="{{ $allImgs->values()->get($i) }}" alt="Ảnh {{ $i + 1 }}"
                                            class="absolute inset-0 w-full h-full object-cover cursor-pointer"
                                            @click="show({{ $i }})">
                                    @else
                                        <div class="w-full h-full bg-gray-100"></div>
                                    @endif
                                </div>
                            @endfor
                            {{-- "Xem video" kế bên "Xem tất cả ảnh" — gộp về góc dưới-phải của
                                 lưới ảnh. Cỡ chữ/padding thu gọn + rút ngắn nhãn nút so với bản
                                 đứng riêng trước đây — 2 nút đủ dài cùng lúc sẽ tràn khỏi bề rộng
                                 1 ô ảnh (~330px), đè lên viền ô ảnh bên cạnh. --}}
                            @if ($totalImgCount > 1 || $hasVideoPd)
                                <div class="absolute bottom-3 right-3 flex items-center gap-1.5">
                                    @if ($hasVideoPd)
                                        <button type="button"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-opacity hover:opacity-90"
                                            style="background:var(--color-primary);box-shadow:0 3px 12px rgba(var(--color-primary-rgb),.35);"
                                            @click="$dispatch('pd-open-video')">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" class="shrink-0">
                                                <circle cx="12" cy="12" r="10" fill="rgba(255,255,255,.25)" />
                                                <polygon points="10,8 10,16 17,12" fill="white" />
                                            </svg>
                                            Xem video
                                        </button>
                                    @endif
                                    @if ($totalImgCount > 1)
                                        <button type="button" @click="show(0)"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-900 bg-white px-3 py-1.5 text-xs font-semibold text-gray-900 shadow-sm hover:bg-gray-50 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 3v18"></path>
                                                <path d="M3 12h18"></path>
                                                <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                            </svg>
                                            Xem tất cả ảnh
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- === Lightbox modal (chỉ dùng khi nhiều ảnh) === --}}
                        @if ($totalImgCount > 1)
                            <div x-show="open" x-cloak
                                style="position:fixed;inset:0;z-index:10000!important;background:rgba(0,0,0,.92);">

                                {{-- Backdrop: click vùng tối để đóng --}}
                                <div style="position:absolute;inset:0;z-index:0;" @click="close()"></div>

                                {{-- Nút đóng --}}
                                <button @click="close()" aria-label="Đóng"
                                    style="position:absolute;top:14px;right:18px;z-index:3;background:none;border:none;color:#fff;font-size:32px;line-height:1;cursor:pointer;opacity:.85;">&times;</button>

                                {{-- Bộ đếm --}}
                                <div x-text="(current + 1) + ' / ' + images.length"
                                    style="position:absolute;top:18px;left:50%;transform:translateX(-50%);z-index:3;color:rgba(255,255,255,.7);font-size:13px;white-space:nowrap;">
                                </div>

                                {{-- Vùng ảnh: top=50px (dưới header), bottom=90px (trên thumbs) --}}
                                <div
                                    style="position:absolute;top:50px;bottom:90px;left:0;right:0;z-index:1;display:flex;align-items:center;justify-content:center;padding:0 60px;box-sizing:border-box;">

                                    <button @click.stop="prev()" aria-label="Trước"
                                        style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:50%;width:46px;height:46px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:2;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2.5">
                                            <path d="M15 18l-6-6 6-6" />
                                        </svg>
                                    </button>

                                    <img :src="images[current]" :alt="'Ảnh ' + (current + 1)" @click.stop
                                        style="max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;user-select:none;display:block;">

                                    <button @click.stop="next()" aria-label="Tiếp"
                                        style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:50%;width:46px;height:46px;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:2;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2.5">
                                            <path d="M9 18l6-6-6-6" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Thumbnails: ghim dưới cùng --}}
                                <div style="position:absolute;bottom:0;left:0;right:0;height:90px;z-index:2;display:flex;align-items:center;gap:8px;padding:0 16px;overflow-x:auto;"
                                    @click.stop>
                                    <template x-for="(img, idx) in images" :key="idx">
                                        <div @click.stop="current = idx"
                                            :style="current === idx ? 'border:2px solid #fff;' : 'border:2px solid transparent;'"
                                            style="flex-shrink:0;width:72px;height:54px;border-radius:6px;overflow:hidden;cursor:pointer;">
                                            <img :src="img" :alt="'Thumb ' + (idx + 1)"
                                                style="width:100%;height:100%;object-fit:cover;display:block;">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        @endif

                    </div>{{-- end gallery x-data --}}

                </div>{{-- /p-3 header+gallery wrapper --}}

                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-0 lg:gap-8 items-start">
                    <div class="w-full">
                        <div class="p-3 overflow-hidden rounded-none lg:rounded-lg bg-white">

                            @php
                                $pdBranchLine = trim(
                                    ($categories['c3'] ?? '') .
                                        (isset($categories['c2']) ? ', ' . $categories['c2'] : ''),
                                );
                                $pdCapacityLine = $guests . ' khách';
                                // Không đọc trực tiếp $product->ratings_count/ratings_avg_star —
                                // 2 cột này chỉ tồn tại trên bản ghi model của LẦN QUERY ban đầu
                                // (withCount/withAvg trong fetchProduct()); Livewire hydrate lại
                                // $product bằng Model::find() ở mọi request sau nên sẽ mất, làm
                                // header hiện sai "Chưa có đánh giá". $ratingsCount/$ratingsAvg
                                // là property Livewire riêng, sống sót qua hydrate (xem mount()).
                                $pdRatingCount = $ratingsCount;
                                $pdRatingAvg = $ratingsAvg;
                            @endphp

                            <div class="space-y-6">
                                {{-- Tiêu đề + mô tả (mobile, căn trái): Tên → shortDescription → mô
                                     tả dài (clamp 4 dòng, "Hiển thị thêm"/"Rút gọn" nếu tràn). Chi
                                     nhánh/số khách/đánh giá/host không hiện ở block mobile này nữa. --}}
                                <div class="space-y-3 text-left md:hidden">
                                    <h2 class="text-[22px] font-semibold text-[#222222] leading-snug">
                                        {{ $product->name ?? '' }}</h2>

                                    @if (!empty($shortDescription))
                                        <p class="text-base text-[#222222] leading-relaxed">{{ $shortDescription }}</p>
                                    @endif

                                    @php $pdLongDescription = trim($product->description ?? ''); @endphp
                                    @if (!empty(strip_tags($pdLongDescription)))
                                        <div x-data="{ expanded: false, needsToggle: false }"
                                            x-init="const checkPdLongDescOverflow = () => {
                                                needsToggle = $refs.pdLongDescBody.scrollHeight > $refs.pdLongDescBody.clientHeight + 4;
                                            };
                                            $nextTick(checkPdLongDescOverflow);
                                            if (document.fonts && document.fonts.ready) {
                                                document.fonts.ready.then(() => $nextTick(checkPdLongDescOverflow));
                                            }">
                                            <div x-ref="pdLongDescBody"
                                                :style="expanded ? 'max-height:2000px' : 'max-height:6.5rem'"
                                                class="product-content text-base text-[#222222] leading-relaxed overflow-hidden transition-[max-height] duration-300 ease-in-out">
                                                {!! $pdLongDescription !!}
                                            </div>
                                            <button type="button" x-show="needsToggle" x-cloak
                                                @click="expanded = !expanded"
                                                class="mt-3 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-primary text-primary font-semibold text-sm hover:bg-primary hover:text-white transition-colors">
                                                <span x-text="expanded ? 'Rút gọn' : 'Hiển thị thêm'"></span>
                                                <svg :class="expanded ? 'rotate-180' : ''"
                                                    class="w-4 h-4 transition-transform duration-300"
                                                    viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M19 9l-7 7-7-7" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                {{-- Tiêu đề + thông tin (desktop) --}}
                                <div class="hidden md:flex items-center justify-between gap-4">
                                    <div>
                                        <h2 class="text-[22px] font-semibold text-[#222222] leading-snug">
                                            {{ $product->name ?? '' }}</h2>
                                        <p class="text-base text-[#222222] mt-1">{{ $pdCapacityLine }}</p>
                                    </div>
                                    <div
                                        class="flex items-center gap-1.5 shrink-0 text-sm font-semibold text-[#222222] whitespace-nowrap">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="currentColor" stroke="currentColor"
                                            stroke-width="1" class="h-4 w-4">
                                            <path
                                                d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z">
                                            </path>
                                        </svg>
                                        @if ($pdRatingCount > 0)
                                            <span>{{ number_format($pdRatingAvg, 1) }}</span>
                                            <span class="text-[#717171] font-normal">&middot;</span>
                                            <span
                                                class="underline underline-offset-2 cursor-pointer"
                                                onclick="document.getElementById('pd-ratings-section')?.scrollIntoView({behavior:'smooth', block:'start'})">{{ $pdRatingCount }}
                                                đánh giá</span>
                                        @else
                                            <span class="font-normal text-[#717171]">Chưa có đánh giá</span>
                                        @endif
                                    </div>
                                </div>

                                <hr class="border-gray-200">

                                {{-- Desktop: shortDescription vẫn ở đây như cũ (mobile đã chuyển
                                     lên ngay dưới tên, xem block .md:hidden phía trên), theo sau
                                     là mô tả dài — cùng hành vi clamp + "Hiển thị thêm"/"Rút gọn"
                                     như mobile ($pdLongDescription đã tính ở block mobile trên). --}}
                                @if (!empty($shortDescription))
                                    <p class="hidden md:block text-base text-[#222222] leading-relaxed">
                                        {{ $shortDescription }}</p>
                                @endif

                                @if (!empty(strip_tags($pdLongDescription)))
                                    <div class="hidden md:block" x-data="{ expanded: false, needsToggle: false }"
                                        x-init="const checkPdLongDescOverflowDesktop = () => {
                                            needsToggle = $refs.pdLongDescBodyDesktop.scrollHeight > $refs.pdLongDescBodyDesktop.clientHeight + 4;
                                        };
                                        $nextTick(checkPdLongDescOverflowDesktop);
                                        if (document.fonts && document.fonts.ready) {
                                            document.fonts.ready.then(() => $nextTick(checkPdLongDescOverflowDesktop));
                                        }">
                                        <div x-ref="pdLongDescBodyDesktop"
                                            :style="expanded ? 'max-height:2000px' : 'max-height:6.5rem'"
                                            class="product-content text-base text-[#222222] leading-relaxed overflow-hidden transition-[max-height] duration-300 ease-in-out">
                                            {!! $pdLongDescription !!}
                                        </div>
                                        <button type="button" x-show="needsToggle" x-cloak
                                            @click="expanded = !expanded"
                                            class="mt-3 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-primary text-primary font-semibold text-sm hover:bg-primary hover:text-white transition-colors">
                                            <span x-text="expanded ? 'Rút gọn' : 'Hiển thị thêm'"></span>
                                            <svg :class="expanded ? 'rotate-180' : ''"
                                                class="w-4 h-4 transition-transform duration-300"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            <hr class="border-gray-200 my-8">

                            {{-- Video sân (nút đã được render trong gallery, chỉ cần modal) --}}
                            @php $videoButtonInGallery = true; @endphp
                            @include('bladethemev1::components.product-detail.video-room')

                            {{-- Tiện nghi sân --}}
                            @include('bladethemev1::components.product-detail.amenities')
                            <hr class="border-gray-200 my-8">
                            {{-- Dịch vụ sân --}}
                            @include('bladethemev1::components.product-detail.additional-services')

                            <hr class="border-gray-200 my-8">

                            @if ($bookingStyle == 1)
                                {{-- Bảng giá sân (ẩn trên mobile khi đến từ trang booking, luôn hiện trên desktop) --}}
                                {{-- @if (!$fromBookingPage)
                                    @include('bladethemev1::components.product-detail.pricing-room')
                                @else
                                    <div class="hidden md:block">
                                        @include('bladethemev1::components.product-detail.pricing-room')
                                    </div>
                                @endif --}}

                                <!-- Chọn khung giờ -->
                                @php
                                    // Màu sân (color_product) — dùng làm điểm nhấn (accent) cho khu vực
                                    // lịch đặt sân: viền cạnh tiêu đề, viền ô "Còn trống" trong legend,
                                    // viền ô lịch (selectable) mặc định. KHÔNG đổi nền ô lịch — giữ trung
                                    // tính để không lẫn với màu trạng thái đã đặt/pending/khuyến mãi.
                                    $roomColor = $productColors[$product->id]['color'] ?? null;
                                @endphp
                                <div class="space-y-5" id="pd-timeslots-section"
                                    style="{{ $roomColor ? "--room-accent:{$roomColor};" : '' }}">
                                    <div class="md:flex md:items-center md:justify-between md:gap-6">
                                        <div>
                                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                                                @if($roomColor)
                                                    <span class="inline-block w-2.5 h-2.5 rounded-full" style="background-color: {{ $roomColor }};"></span>
                                                @endif
                                                Lịch đặt sân
                                            </h2>
                                            <p class="text-sm text-gray-500 mt-0.5">Chọn khung giờ phù hợp với bạn</p>
                                        </div>

                                        {{-- ── Legend ── --}}
                                        <div class="md:flex gap-4 text-xs text-gray-500 mb-4 md:mb-0 mt-3 md:mt-0 grid grid-cols-2 gap-3">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="inline-flex w-4 h-4 rounded bg-white"
                                                style="border: 1px solid var(--room-accent, #DDDDDD);"></span>
                                            Còn trống
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex w-4 h-4 rounded"
                                                style="background: var(--color-primary); border: 1px solid var(--color-primary);"></span>
                                            Đã đặt
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="inline-flex w-4 h-4 rounded bg-gray-900 border border-gray-900"></span>
                                            Đang chọn
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="selectable-mini promo-mini inline-flex items-center justify-center w-4 h-4 rounded"></span>
                                            Khuyến mãi
                                        </div>
                                        </div>
                                    </div>

                                    {{-- ── CSS cho lịch đặt sân (selectable + mobile card) ── --}}
                                    <style id="pd-booking-redesign">
                                        /* ── selectable box: nền trung tính (không theo màu sân, để
                                           không lẫn với màu trạng thái đã đặt/pending/khuyến mãi), chỉ
                                           viền mặc định (còn trống) theo --room-accent nếu sân có
                                           cấu hình màu (color_product) — xem $roomColor phía trên. ── */
                                        .selectable {
                                            position: relative;
                                            z-index: 10;
                                            height: 32px;
                                            padding: 0 .5rem;
                                            font-weight: 700;
                                            border-radius: 8px !important;
                                            background: #fff !important;
                                            border: 1.5px solid var(--room-accent, #d1d5db) !important;
                                            color: #374151 !important;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            overflow: visible;
                                            transition: all .18s ease;
                                            box-shadow: inset 0 1px 3px rgba(0, 0, 0, .08);
                                        }

                                        .selectable:hover:not([style*="pointer-events"]) {
                                            background: #f3f4f6 !important;
                                            border-color: #9ca3af !important;
                                            box-shadow: inset 0 1px 3px rgba(0, 0, 0, .08), 0 4px 12px rgba(0, 0, 0, .08);
                                            transform: translateY(-1px);
                                        }

                                        .selectable.booked {
                                            background: var(--color-primary) !important;
                                            border-color: var(--color-primary) !important;
                                            cursor: not-allowed !important;
                                            pointer-events: none;
                                        }

                                        .selectable.pending {
                                            background: #CFDC74 !important;
                                            border-color: #CFDC74 !important;
                                            cursor: not-allowed !important;
                                            pointer-events: none;
                                        }

                                        .selectable.blocked {
                                            background: #e5e7eb !important;
                                            border-color: #d1d5db !important;
                                            cursor: not-allowed !important;
                                            pointer-events: none;
                                        }

                                        /* Đang bị ADMIN giữ chỗ real-time (xem TimeslotHoldService) — nền cam
                                           riêng, phân biệt với "đã đặt" (primary)/"bị khoá ngày" (xám). */
                                        .selectable.held {
                                            background: #f59e0b !important;
                                            border-color: #f59e0b !important;
                                            cursor: not-allowed !important;
                                            pointer-events: none;
                                        }

                                        .selectable.held .lock-icon {
                                            color: #fff;
                                        }

                                        /* Đặt sau cùng để "đang chọn" luôn thắng dù ô đó cũng đang booked/pending
                                           (trường hợp reselect lại slot thuộc chính đơn đang chờ của khách).
                                           opacity:1 !important để thắng luôn cả style inline opacity:0.6 gắn
                                           theo !$isSelectable — nếu không màu đen sẽ bị pha loãng thành xám. */
                                        .selectable.active {
                                            background: #111827 !important;
                                            border-color: #111827 !important;
                                            opacity: 1 !important;
                                        }

                                        /* Ô vừa ĐANG chọn (active, đen) vừa bị ADMIN giữ chỗ (held, cam+khoá) —
                                           2 trạng thái này KHÔNG được để cùng lộ ra như "đang chọn của khách"
                                           (echo-client.js đã cố tự click() bỏ chọn khi admin giữ, nhưng vẫn có
                                           khe hở thời điểm 2 class tồn tại cùng lúc). .active định nghĩa SAU
                                           .held nên cùng độ đặc thù (2 class, !important) thì .active thắng —
                                           thêm rule 3-class này để held LUÔN thắng bất kể thứ tự khai báo, tránh
                                           hiện nhầm màu đen "đã chọn" thay vì cam+khoá khi 2 trạng thái đụng nhau. */
                                        .selectable.active.held {
                                            background: #f59e0b !important;
                                            border-color: #f59e0b !important;
                                            opacity: 1 !important;
                                        }

                                        .selectable.past-time {
                                            background: #f3f4f6 !important;
                                            border: 1px solid #e5e7eb !important;
                                        }

                                        .lock-icon {
                                            width: 14px;
                                            height: 14px;
                                            color: #9ca3af;
                                            position: relative;
                                            z-index: 15;
                                        }

                                        .selectable-mini {
                                            position: relative;
                                            z-index: 1;
                                            background: #fff !important;
                                            border: 1.5px solid #dddddd !important;
                                            border-radius: 6px !important;
                                            overflow: visible;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                        }

                                        .promotion-corner-image {
                                            position: absolute;
                                            top: -7px;
                                            right: -7px;
                                            z-index: 30;
                                            width: 18px;
                                            height: 18px;
                                            overflow: hidden;
                                            animation: pdBounce 2s ease-in-out infinite;
                                        }

                                        .corner-img {
                                            width: 100%;
                                            height: 100%;
                                            object-fit: contain;
                                            transition: transform .3s;
                                        }

                                        .selectable:hover .corner-img {
                                            transform: scale(1.15);
                                        }

                                        .promotion-center-label {
                                            position: absolute;
                                            top: 50%;
                                            left: 50%;
                                            transform: translate(-50%, -50%);
                                            z-index: 25;
                                            font-size: 10px;
                                            font-weight: 700;
                                            color: #4c1d95;
                                            white-space: nowrap;
                                            overflow: hidden;
                                            max-width: 90%;
                                            text-shadow: 0 1px 2px rgba(255, 255, 255, .8);
                                            animation: pdPulse 2.5s ease-in-out infinite;
                                        }

                                        @keyframes pdBounce {

                                            0%,
                                            100% {
                                                transform: translateY(0) scale(1)
                                            }

                                            50% {
                                                transform: translateY(-3px) scale(1.05)
                                            }
                                        }

                                        @keyframes pdPulse {

                                            0%,
                                            100% {
                                                opacity: 1;
                                                transform: translate(-50%, -50%) scale(1)
                                            }

                                            50% {
                                                opacity: .9;
                                                transform: translate(-50%, -50%) scale(1.05)
                                            }
                                        }

                                        /* ── Desktop: bảng 2 khối tách rời (giống cấu trúc mobile) — cột
                                             "Thời gian" và khối khung giờ là 2 card riêng, có khoảng cách
                                             thật ở giữa, không dùng <table> nữa (table không tạo được
                                             khoảng trống thật giữa các cột). ── */
                                        .pd-dt-header {
                                            display: flex;
                                            gap: 14px;
                                            margin-bottom: 14px;
                                        }

                                        .pd-dt-col-header {
                                            width: 130px;
                                            flex-shrink: 0;
                                            min-height: 50px;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            font-size: 10px;
                                            font-weight: 700;
                                            letter-spacing: .06em;
                                            text-transform: uppercase;
                                            color: #111827;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 14px;
                                        }

                                        .pd-dt-slots-header-row {
                                            /* Chỉ hiện đúng 5 cột NGÀY/lần (100px x 5 + gap 8px x4 + padding
                                               10px x2) — nhiều hơn phải cuộn ngang, không đẩy rộng trang.
                                               Bề rộng thật do .pd-dt-slots-header-wrap (cha) quyết định. */
                                            width: 100%;
                                            display: flex;
                                            gap: 8px;
                                            padding: 6px 10px;
                                            min-height: 50px;
                                            align-items: center;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 14px;
                                            overflow-x: hidden;
                                            scrollbar-width: none;
                                            -ms-overflow-style: none;
                                        }

                                        .pd-dt-slot-th {
                                            width: 100px;
                                            flex-shrink: 0;
                                            text-align: center;
                                            font-size: 11px;
                                            font-weight: 700;
                                            color: #111827;
                                        }

                                        .pd-dt-date-th.is-today {
                                            color: var(--room-accent, #111827);
                                        }

                                        /* Khung (border/bo góc/nền) đứng yên cố định — chỉ phần NỘI DUNG bên
                                           trong (.pd-dt-dates-scroll / .pd-dt-slots-scroll) cuộn dọc, đồng bộ
                                           2 chiều bằng Alpine (@scroll) để 2 cột luôn khớp hàng với nhau. */
                                        .pd-dt-grid-outer {
                                            display: flex;
                                            gap: 14px;
                                            align-items: stretch;
                                        }

                                        .pd-dt-dates-card {
                                            width: 130px;
                                            flex-shrink: 0;
                                            height: 460px;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 14px;
                                            overflow: hidden;
                                        }

                                        .pd-dt-dates-scroll,
                                        .pd-dt-slots-scroll {
                                            height: 100%;
                                            overflow-y: auto;
                                            scrollbar-width: none;
                                            -ms-overflow-style: none;
                                        }

                                        /* Khối khung giờ cuộn NGANG riêng bên trong card của nó (nhiều
                                           khung giờ không còn đẩy rộng cả trang) — header ở trên
                                           (.pd-dt-slots-header-row) không tự cuộn được (overflow-x:
                                           hidden), scrollLeft của nó được đồng bộ theo scroll ngang của
                                           khối này qua Alpine (@scroll bên dưới), y hệt cách cuộn dọc của
                                           cột Ngày/khung giờ đã đồng bộ với nhau. */
                                        .pd-dt-slots-scroll {
                                            overflow-x: auto;
                                            /* Thanh cuộn NGANG hiện rõ (mảnh) để khách biết còn ngày khác
                                               xem được, không chỉ trông cậy vào nút mũi tên — thanh cuộn
                                               DỌC vẫn ẩn (chỉ là bản phản chiếu của cột Khung giờ bên
                                               trái, không phải nơi thao tác chính). */
                                            scrollbar-width: thin;
                                        }

                                        .pd-dt-dates-scroll::-webkit-scrollbar {
                                            display: none;
                                            width: 0;
                                            height: 0;
                                        }

                                        .pd-dt-slots-scroll::-webkit-scrollbar {
                                            height: 6px;
                                            width: 0;
                                        }

                                        .pd-dt-slots-scroll::-webkit-scrollbar-thumb {
                                            background: #d1d5db;
                                            border-radius: 3px;
                                        }

                                        .pd-dt-slots-scroll::-webkit-scrollbar-track {
                                            background: transparent;
                                        }

                                        /* Wrapper cùng max-width với thân bảng (.pd-dt-slots-outer/.pd-dt-slots-card)
                                           để 2 khối luôn khớp bề rộng — nút Trước/Sau nổi ĐÈ LÊN rìa wrapper này
                                           bằng position:absolute (không chiếm chỗ trong flex layout), xem ghi chú
                                           tại chỗ dùng trong HTML. */
                                        .pd-dt-slots-header-wrap {
                                            position: relative;
                                            flex: 1;
                                            min-width: 0;
                                            max-width: 560px;
                                        }

                                        .pd-dt-date-nav-btn {
                                            position: absolute;
                                            top: 50%;
                                            transform: translateY(-50%);
                                            z-index: 5;
                                            width: 22px;
                                            height: 22px;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 999px;
                                            box-shadow: 0 1px 3px rgba(0, 0, 0, .12);
                                            color: #374151;
                                            cursor: pointer;
                                        }

                                        .pd-dt-date-nav-prev { left: -9px; }
                                        .pd-dt-date-nav-next { right: -9px; }

                                        .pd-dt-date-nav-btn:hover {
                                            background: #f3f4f6;
                                        }

                                        .pd-dt-date-nav-btn svg {
                                            width: 11px;
                                            height: 11px;
                                        }

                                        .pd-dt-date-row {
                                            height: 48px;
                                            display: flex;
                                            flex-direction: column;
                                            align-items: center;
                                            justify-content: center;
                                            border-bottom: 1px solid #f3f4f6;
                                            text-align: center;
                                        }

                                        .pd-dt-date-row:last-child {
                                            border-bottom: none;
                                        }

                                        .pd-dt-date-row.is-today {
                                            background: #f9fafb;
                                        }

                                        .pd-dt-slots-outer {
                                            flex: 1;
                                            min-width: 0;
                                            max-width: 560px;
                                        }

                                        .pd-dt-slots-card {
                                            height: 460px;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 14px;
                                            overflow: hidden;
                                        }

                                        .pd-dt-slots-row {
                                            display: flex;
                                            gap: 8px;
                                            padding: 6px 10px;
                                            height: 48px;
                                            align-items: center;
                                            border-bottom: 1px solid #f3f4f6;
                                        }

                                        .pd-dt-slots-row:last-child {
                                            border-bottom: none;
                                        }

                                        .pd-dt-slots-row:hover {
                                            background-color: #f9fafb;
                                        }

                                        .pd-dt-slot-cell {
                                            width: 100px;
                                            flex-shrink: 0;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                        }

                                        .pd-dt-time-row {
                                            gap: 2px;
                                        }

                                        /* ── Mobile two-panel card ── */
                                        .pd-room-header {
                                            display: flex;
                                            flex-direction: column;
                                            align-items: flex-start;
                                            padding: 18px 16px 14px;
                                            background: #fff;
                                            border-bottom: 1px solid #e5e7eb;
                                        }

                                        .pd-room-name {
                                            font-family: 'Georgia', 'Times New Roman', serif;
                                            font-style: italic;
                                            font-size: 1.4rem;
                                            font-weight: 700;
                                            color: #111827;
                                            line-height: 1.1;
                                            margin: 0;
                                        }

                                        .pd-room-sub {
                                            color: #6b7280;
                                            font-size: .8rem;
                                            font-weight: 600;
                                            margin-top: 5px;
                                            letter-spacing: .03em;
                                        }

                                        .pd-grid-header {
                                            display: flex;
                                            gap: 10px;
                                            padding: 0 14px;
                                            margin-top: 14px;
                                        }

                                        .pd-col-header {
                                            min-width: 80px;
                                            width: 80px;
                                            flex-shrink: 0;
                                            height: 46px;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            font-size: .7rem;
                                            font-weight: 700;
                                            letter-spacing: .06em;
                                            text-transform: uppercase;
                                            color: #111827;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 14px;
                                        }

                                        /* Wrapper mang border/bg/bo góc (KHÔNG tự cuộn). Nút Trước/Sau KHÔNG còn
                                           nổi đè lên rìa wrapper (từng đè lên chữ ngày ở màn hình hẹp) — nay nằm
                                           trong 1 hàng điều hướng RIÊNG (.pd-date-nav-row) phía trên hàng ngày,
                                           chiếm layout bình thường (không position:absolute) nên không thể che chữ. */
                                        .pd-slots-headers-wrap {
                                            flex: 1;
                                            min-width: 0;
                                        }

                                        .pd-date-nav-row {
                                            display: flex;
                                            align-items: center;
                                            justify-content: space-between;
                                            gap: 6px;
                                            margin-bottom: 4px;
                                        }

                                        .pd-date-nav-hint {
                                            font-size: 9px;
                                            color: #9ca3af;
                                            text-align: center;
                                            flex: 1;
                                            min-width: 0;
                                            white-space: nowrap;
                                            overflow: hidden;
                                            text-overflow: ellipsis;
                                        }

                                        /* padding PHẢI khớp CHÍNH XÁC .pd-slots-scroll (thân bảng) bên dưới — 2 nơi
                                           lệch padding/gap từng gây lỗi "cuộn tới cuối bị lệch dần" đã gặp và sửa ở
                                           nơi khác (dashboard admin/tsgrid). */
                                        .pd-slots-header-row {
                                            display: flex;
                                            gap: 3px;
                                            padding: 0 4px;
                                            height: 46px;
                                            align-items: center;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 14px;
                                            overflow-x: hidden;
                                            scrollbar-width: none;
                                            -ms-overflow-style: none;
                                        }

                                        .pd-slots-header-row::-webkit-scrollbar {
                                            display: none;
                                            width: 0;
                                            height: 0;
                                        }

                                        /* Ghi chú: các thuộc tính kích thước quan trọng của nút (width/height/vị trí)
                                           được đặt INLINE trực tiếp trên thẻ <button> trong Blade thay vì chỉ dựa vào
                                           class này — đã phát hiện rule class ở đây có lúc KHÔNG được áp dụng (cùng
                                           lỗi lạ đã gặp với .tsgrid-slots-label), khiến nút to mặc định ~44px và đè
                                           lên chữ ngày. Giữ class để style thị giác (màu/border/shadow) còn phần
                                           kích thước/flex-shrink luôn có inline làm lưới an toàn. */
                                        .pd-date-nav-btn {
                                            flex-shrink: 0;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            background: #fff;
                                            border: 1px solid #e5e7eb;
                                            border-radius: 999px;
                                            box-shadow: 0 1px 3px rgba(0, 0, 0, .15);
                                            color: #374151;
                                            padding: 0;
                                        }

                                        .pd-date-nav-btn svg {
                                            width: 10px;
                                            height: 10px;
                                        }

                                        .pd-slot-th {
                                            flex: 0 0 calc((100% - 12px) / 5);
                                            text-align: center;
                                            font-size: .55rem;
                                            font-weight: 700;
                                            letter-spacing: -.03em;
                                            line-height: 1.15;
                                            color: #111827;
                                        }

                                        .pd-slot-th.is-today {
                                            color: var(--color-primary, #0f766e);
                                        }

                                        .pd-overnight-tag {
                                            display: block;
                                            font-size: .48rem;
                                            font-weight: 600;
                                            background: #e5e7eb;
                                            color: #374151;
                                            border-radius: 3px;
                                            padding: 1px 2px;
                                            margin-top: 1px;
                                        }

                                        .pd-mobile-scroll {
                                            max-height: 380px;
                                            overflow-y: auto;
                                            overflow-x: hidden;
                                            border-radius: 0 0 20px 20px;
                                            scrollbar-width: none;
                                            -ms-overflow-style: none;
                                            background: #fff;
                                        }

                                        .pd-mobile-scroll::-webkit-scrollbar {
                                            display: none;
                                            width: 0;
                                            height: 0;
                                        }

                                        .pd-grid-outer {
                                            display: flex;
                                            gap: 10px;
                                            padding: 12px 14px 20px;
                                            align-items: flex-start;
                                            background: #fff;
                                        }

                                        /* Cột KHUNG GIỜ (dọc) — thay cho cột Ngày cũ. */
                                        .pd-dates-card {
                                            border-radius: 14px;
                                            min-width: 80px;
                                            width: 80px;
                                            flex-shrink: 0;
                                            border: 1px solid #e5e7eb;
                                            overflow: clip;
                                            background: #fff;
                                        }

                                        .pd-date-row {
                                            height: 38px;
                                            display: flex;
                                            flex-direction: column;
                                            align-items: center;
                                            justify-content: center;
                                            border-bottom: 1px solid #f3f4f6;
                                            gap: 1px;
                                            padding: 0 4px;
                                        }

                                        .pd-date-row:last-child {
                                            border-bottom: none;
                                        }

                                        .pd-date-day {
                                            font-size: .58rem;
                                            font-weight: 700;
                                            color: #111827;
                                            line-height: 1.1;
                                            white-space: nowrap;
                                        }

                                        .pd-slots-outer {
                                            flex: 1;
                                            min-width: 0;
                                        }

                                        .pd-slots-card {
                                            border-radius: 14px;
                                            border: 1px solid #e5e7eb;
                                            overflow: clip;
                                            max-width: 100%;
                                            background: #fff;
                                        }

                                        /* Khối cuộn NGANG (ngày) riêng bên trong — padding PHẢI khớp CHÍNH XÁC
                                           .pd-slots-header-row ở trên (đều 0 4px) để 2 khối luôn có cùng 1 chiều
                                           rộng cuộn tối đa, không lệch dần khi cuộn tới cuối (đúng lỗi đã gặp và
                                           sửa ở dashboard admin/tsgrid — xem ghi chú ở đó). Thanh cuộn hiện rõ
                                           (mảnh) để khách biết còn ngày khác xem được. */
                                        .pd-slots-scroll {
                                            overflow-x: auto;
                                            padding: 0 4px;
                                            scrollbar-width: thin;
                                        }

                                        .pd-slots-scroll::-webkit-scrollbar {
                                            height: 4px;
                                        }

                                        .pd-slots-scroll::-webkit-scrollbar-thumb {
                                            background: #d1d5db;
                                            border-radius: 2px;
                                        }

                                        .pd-slots-row {
                                            display: flex;
                                            gap: 3px;
                                            height: 38px;
                                            align-items: center;
                                            border-bottom: 1px solid #f3f4f6;
                                        }

                                        .pd-slots-row:last-child {
                                            border-bottom: none;
                                        }

                                        /* flex-basis tính theo % chiều rộng khung cuộn (không phải px cố định) để
                                           LUÔN hiện đúng 5 cột/khung hình bất kể màn hình rộng bao nhiêu — 4 khoảng
                                           gap 3px giữa 5 cột = 12px trừ đi trước khi chia 5. */
                                        .pd-slot-cell {
                                            flex: 0 0 calc((100% - 12px) / 5);
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                        }

                                        .pd-slot-cell .selectable {
                                            width: 100%;
                                            height: 22px !important;
                                            border-radius: 6px !important;
                                        }

                                        .pd-slot-cell .lock-icon {
                                            width: 10px;
                                            height: 10px;
                                        }
                                    </style>

                                    {{-- ── Shared Alpine state (mobile + desktop) ── --}}
                                    <div x-data="{
                                        selectedSlots: @entangle('selectedSlots'),

                                        toggleSlot(date, timeslotId, price, originalPrice, basePrice, increaseAmount, promoDiscount, startTime, endTime, status, roomId, roomName, timeslotLabel, overNight) {
                                            const key = `${date}-${timeslotId}`;
                                            const index = this.selectedSlots.findIndex(slot => slot.key === key);
                                            if (index > -1) {
                                                this.selectedSlots.splice(index, 1);
                                            } else {
                                                this.selectedSlots.push({ key, date, startTime, endTime, price, originalPrice, basePrice, increaseAmount, promoDiscount, timeslotId, roomId, roomName, timeslotLabel, overNight });
                                            }
                                            @this.set('selectedSlots', this.selectedSlots);
                                        }
                                    }">

                                        {{-- ═════ MOBILE: Two-panel card (md:hidden) ═════ --}}
                                        <div class="md:hidden mb-4 rounded-[20px] overflow-hidden shadow-lg" x-data="{}">

                                            {{-- Header NGÀY (ngang) — đổi trục giống bản desktop: Khung giờ đứng dọc bên
                                                 trái, Ngày nằm ngang ở đây (cuộn ngang xem thêm, kéo/trượt HOẶC bấm nút
                                                 Trước/Sau). Không còn hệ thống phân trang "slotPage" cũ (dựng cho bản
                                                 khung-giờ-ngang trước đây) — cuộn ngang tự nhiên đã thay thế. Nút
                                                 position:absolute đè lên rìa wrapper (không phải flex-sibling của
                                                 header-row) để không làm header rộng hơn thân bảng — CÙNG lý do/kỹ
                                                 thuật đã áp dụng cho bản desktop (pd-dt-date-nav-btn). --}}
                                            <div class="pd-grid-header">
                                                <div class="pd-col-header">Khung giờ</div>
                                                <div class="pd-slots-headers-wrap">
                                                    <div class="pd-date-nav-row">
                                                        <button type="button" class="pd-date-nav-btn pd-date-nav-prev"
                                                            style="width:22px !important;height:22px !important;min-width:22px !important;min-height:22px !important;"
                                                            aria-label="Xem ngày trước"
                                                            @click="$refs.pdmSlotsScroll.scrollBy({ left: -160, behavior: 'smooth' })">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                                        </button>
                                                        <span class="pd-date-nav-hint">Kéo hoặc bấm mũi tên để xem ngày khác</span>
                                                        <button type="button" class="pd-date-nav-btn pd-date-nav-next"
                                                            style="width:22px !important;height:22px !important;min-width:22px !important;min-height:22px !important;"
                                                            aria-label="Xem thêm ngày"
                                                            @click="$refs.pdmSlotsScroll.scrollBy({ left: 160, behavior: 'smooth' })">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                                        </button>
                                                    </div>
                                                    <div class="pd-slots-header-row" x-ref="pdmHeaderScroll">
                                                        @foreach ($dates as $date)
                                                            @php $mhds = $date['carbon_date']->format('d/m'); @endphp
                                                            <div class="pd-slot-th{{ $date['is_today'] ? ' is-today' : '' }}"
                                                                style="flex:0 0 calc((100% - 12px) / 5);">
                                                                {{ $date['day'] }}<br>{{ $mhds }}
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Two-panel cuộn dọc (khung giờ) + ngang (ngày, đồng bộ với header trên) --}}
                                            <div class="pd-mobile-scroll">
                                                <div class="pd-grid-outer">

                                                    {{-- Cột Khung giờ (dọc) --}}
                                                    <div class="pd-dates-card">
                                                        @foreach ($timeSlots as $timeSlot)
                                                            @php
                                                                $mhSt = \Carbon\Carbon::parse($timeSlot['start_time']);
                                                                $mhEt = \Carbon\Carbon::parse($timeSlot['end_time']);
                                                                $mhOv =
                                                                    $mhEt->isNextDay() ||
                                                                    $mhEt->lt($mhSt) ||
                                                                    ($timeSlot['over_night'] ?? 0) == 1;
                                                            @endphp
                                                            <div class="pd-date-row">
                                                                <span class="pd-date-day">{{ $mhSt->format('H:i') }}-{{ $mhEt->format('H:i') }}</span>
                                                                @if ($mhOv)
                                                                    <span class="pd-overnight-tag">Qua đêm</span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    {{-- Lưới ô (ngang theo Ngày, khớp header trên) --}}
                                                    <div class="pd-slots-outer">
                                                        <div class="pd-slots-card">
                                                            <div class="pd-slots-scroll" x-ref="pdmSlotsScroll"
                                                                @scroll="$refs.pdmHeaderScroll.scrollLeft = $event.target.scrollLeft">
                                                            @foreach ($timeSlots as $timeSlot)
                                                                <div class="pd-slots-row">
                                                                    @foreach ($dates as $date)
                                                                        @php
                                                                            $mPrice = $timeSlot['timeslot_price'] ?? 0;
                                                                            $mClasses = '';
                                                                            $mIsSelectable = true;
                                                                            $mCurrentDT = \Carbon\Carbon::createFromFormat(
                                                                                'd-m-Y H:i:s',
                                                                                $date['date'] .
                                                                                    ' ' .
                                                                                    $timeSlot['start_time'],
                                                                            );
                                                                            $mStatus = 'available';
                                                                            foreach ($product->orderItems as $oItem) {
                                                                                $oIn = \Carbon\Carbon::parse(
                                                                                    $oItem->checkin_date,
                                                                                );
                                                                                $oOut = \Carbon\Carbon::parse(
                                                                                    $oItem->checkout_date,
                                                                                );
                                                                                if ($mCurrentDT->between($oIn, $oOut)) {
                                                                                    // cancelled_at = khách đã tự huỷ đơn (xem
                                                                                    // OrderCancellationService) — KHÔNG tính là chiếm
                                                                                    // chỗ nữa dù status DB vẫn 'paid'/'deposit', tiếp
                                                                                    // tục tìm order item KHÁC (có thể đã đặt lại sau đó).
                                                                                    if ($oItem->order && is_null($oItem->order->cancelled_at)) {
                                                                                        $mStatus =
                                                                                            $oItem->order->status;
                                                                                        break;
                                                                                    }
                                                                                    continue;
                                                                                }
                                                                            }
                                                                            if ($mStatus === 'pending') {
                                                                                $mClasses .= ' pending';
                                                                                $mIsSelectable = false;
                                                                            } elseif (
                                                                                in_array($mStatus, ['paid', 'shipped'])
                                                                            ) {
                                                                                $mClasses .= ' booked';
                                                                                $mIsSelectable = false;
                                                                            }
                                                                            $mOrderColor = null;
                                                                            if ($mStatus !== 'available') {
                                                                                if (
                                                                                    in_array($mStatus, [
                                                                                        'paid',
                                                                                        'shipped',
                                                                                        'confirmed',
                                                                                    ])
                                                                                ) {
                                                                                    $mOrderColor = '#4e6b4c';
                                                                                } elseif ($mStatus === 'deposit') {
                                                                                    $mOrderColor = '#3b82f6';
                                                                                } elseif ($mStatus === 'pending') {
                                                                                    $mOrderColor = '#f97316';
                                                                                } else {
                                                                                    $mOrderColor = '#94a3b8';
                                                                                }
                                                                            }
                                                                            if ($mIsSelectable && $date['is_today']) {
                                                                                $mNow = \Carbon\Carbon::now();
                                                                                $mSS = \Carbon\Carbon::createFromFormat(
                                                                                    'd-m-Y H:i:s',
                                                                                    $date['date'] .
                                                                                        ' ' .
                                                                                        $timeSlot['start_time'],
                                                                                );
                                                                                $mSE = \Carbon\Carbon::createFromFormat(
                                                                                    'd-m-Y H:i:s',
                                                                                    $date['date'] .
                                                                                        ' ' .
                                                                                        $timeSlot['end_time'],
                                                                                );
                                                                                if ($mSE->lt($mSS)) {
                                                                                    $mSE->addDay();
                                                                                }
                                                                                if ($mNow->gte($mSE)) {
                                                                                    $mIsSelectable = false;
                                                                                    $mClasses .= ' past-time';
                                                                                }
                                                                            }
                                                                            $mRts = null;
                                                                            foreach ($product->roomTimeSlots as $rts) {
                                                                                if (
                                                                                    $rts->timeslot_id ==
                                                                                    $timeSlot['timeslot_id']
                                                                                ) {
                                                                                    $mRts = $rts;
                                                                                    break;
                                                                                }
                                                                            }
                                                                            if (!$mRts) {
                                                                                $mRts = (object) [
                                                                                    'price' => $mPrice,
                                                                                    'promotions' => collect(
                                                                                        $timeSlot['promotions'] ?? [],
                                                                                    ),
                                                                                    'settings' => null,
                                                                                    'timeSlot' => (object) [
                                                                                        'start_time' =>
                                                                                            $timeSlot['start_time'],
                                                                                        'end_time' =>
                                                                                            $timeSlot['end_time'],
                                                                                    ],
                                                                                ];
                                                                            }
                                                                            $mSt = is_array($mRts->settings)
                                                                                ? $mRts->settings
                                                                                : json_decode($mRts->settings, true) ??
                                                                                    [];
                                                                            if (
                                                                                in_array(
                                                                                    $date[
                                                                                        'carbon_date'
                                                                                    ]->toDateString(),
                                                                                    $mSt['blocked_dates'] ?? [],
                                                                                )
                                                                            ) {
                                                                                $mIsSelectable = false;
                                                                                $mClasses .= ' blocked';
                                                                            }

                                                                            // --- Đang bị ADMIN giữ chỗ real-time (xem TimeslotHoldService) ---
                                                                            // giống hệt bản desktop bên trên — hiển thị MỜ, không ẩn hoàn toàn.
                                                                            $mHeldByName = null;
                                                                            if ($mIsSelectable && isset($mRts->id)) {
                                                                                $mActiveHold = app(
                                                                                    \App\Services\TimeslotHoldService::class,
                                                                                )->isHeldByAdmin(
                                                                                    $mRts->id,
                                                                                    $date['carbon_date']->toDateString(),
                                                                                );
                                                                                if ($mActiveHold) {
                                                                                    $mIsSelectable = false;
                                                                                    $mClasses .= ' held';
                                                                                    $mHeldByName =
                                                                                        $mActiveHold->user->fullname ??
                                                                                        $mActiveHold->user->email ??
                                                                                        'nhân viên';
                                                                                }
                                                                            }

                                                                            $mPD = $this->calculateSlotPrice(
                                                                                $mRts,
                                                                                $date['carbon_date']->format('Y-m-d'),
                                                                                $timeSlot['start_time'],
                                                                            );
                                                                            $mFinal = $mPD['final_price'];
                                                                            $mPAI = $mPD['price_after_increase'];
                                                                            $mBase = $mPD['original_price'];
                                                                            $mInc = $mPD['increase_amount'] ?? 0;
                                                                            $mPromo = $mPD['total_discount'];
                                                                            $mHasD = false;
                                                                            $mHasI = false;
                                                                            foreach ($mPD['promotions'] ?? [] as $p) {
                                                                                if (
                                                                                    in_array($p->type, [
                                                                                        'percentage',
                                                                                        'fixed',
                                                                                    ])
                                                                                ) {
                                                                                    $mHasD = true;
                                                                                }
                                                                                if (
                                                                                    in_array($p->type, [
                                                                                        'increase_percentage',
                                                                                        'increase_fixed',
                                                                                    ])
                                                                                ) {
                                                                                    $mHasI = true;
                                                                                }
                                                                            }
                                                                            if ($mHasD) {
                                                                                $mClasses .= ' promo';
                                                                            }
                                                                            if ($mHasI && !$mHasD) {
                                                                                $mClasses .= ' promo-increase';
                                                                            }
                                                                        @endphp
                                                                        <div class="pd-slot-cell" style="flex:0 0 calc((100% - 12px) / 5);">
                                                                            <div class="selectable {{ $mClasses }}"
                                                                                :class="selectedSlots.some(s => s
                                                                                    .key === '{{ $date['carbon_date']->format('Y-m-d') }}-{{ $timeSlot['timeslot_id'] }}'
                                                                                ) ? 'active' : ''"
                                                                                style="{{ !$mIsSelectable ? 'pointer-events:none;opacity:0.6;' : 'cursor:pointer;' }}"
                                                                                data-room-id="{{ $product['id'] }}"
                                                                                data-timeslot-id="{{ $timeSlot['timeslot_id'] }}"
                                                                                data-iso-date="{{ $date['carbon_date']->format('Y-m-d') }}"
                                                                                @click="toggleSlot('{{ $date['carbon_date']->format('Y-m-d') }}','{{ $timeSlot['timeslot_id'] }}','{{ $mFinal }}','{{ $mPAI }}','{{ $mBase }}','{{ $mInc }}','{{ $mPromo }}','{{ \Carbon\Carbon::parse($timeSlot['start_time'])->format('H:i') }}','{{ \Carbon\Carbon::parse($timeSlot['end_time'])->format('H:i') }}','{{ $mStatus }}','{{ $product['id'] }}','{{ $product['name'] }}','{{ $timeSlot['timeslot_label'] }}','{{ $timeSlot['over_night'] ?? 0 }}')">
                                                                                @if (str_contains($mClasses, 'blocked'))
                                                                                    <svg class="lock-icon" viewBox="0 0 24 24"
                                                                                        fill="none" stroke="currentColor"
                                                                                        stroke-width="2" stroke-linecap="round"
                                                                                        stroke-linejoin="round">
                                                                                        <rect x="4" y="11" width="16"
                                                                                            height="9" rx="2" />
                                                                                        <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                                                                                    </svg>
                                                                                @endif
                                                                                @if (str_contains($mClasses, 'held'))
                                                                                    <div class="lock-icon" title="Đang được {{ $mHeldByName }} xử lý cho 1 đơn khác">
                                                                                        <svg viewBox="0 0 24 24" fill="none"
                                                                                            stroke="currentColor" stroke-width="2"
                                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                                            <rect x="4" y="11" width="16" height="9" rx="2" />
                                                                                            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                                                                                        </svg>
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endforeach
                                                            </div>{{-- /pd-slots-scroll --}}
                                                        </div>{{-- /pd-slots-card --}}
                                                    </div>{{-- /pd-slots-outer --}}
                                                </div>{{-- /pd-grid-outer --}}
                                            </div>{{-- /pd-mobile-scroll --}}
                                        </div>{{-- /mobile --}}

                                        {{-- ═════ DESKTOP: 2 khối tách rời, có khoảng cách (hidden md:block) ═════ --}}
                                        <div class="hidden md:block">
                                            {{-- Header (ngoài scroll) --}}
                                            <div class="pd-dt-header">
                                                <div class="pd-dt-col-header">Khung giờ</div>
                                                {{-- Header NGÀY (ngang) — chỉ hiện 5 ngày/lần (xem .pd-dt-slots-header-row
                                                     max-width), cuộn ngang xem thêm (kéo/trượt HOẶC bấm nút Trước/Sau).
                                                     Nút đặt position:absolute ĐÈ LÊN rìa wrapper (không phải flex-sibling
                                                     của header-row) — nếu để nút là 1 flex item CẠNH header-row như bản
                                                     cũ, nút sẽ cộng thêm bề rộng vào HÀNG NÀY khiến nó rộng hơn thân bảng
                                                     bên dưới (không có nút tương ứng) → 2 khối lệch cột (đúng lỗi đã gặp).
                                                     Bọc trong 1 wrapper cùng max-width với thân bảng, nút nổi đè lên rìa
                                                     wrapper đó thì bề rộng thật của header-row không đổi, luôn khớp thân
                                                     bảng. Đồng bộ với body qua x-ref có sẵn (KHÔNG đổi tên ref — giữ
                                                     nguyên dây Alpine cũ, chỉ đổi nội dung). --}}
                                                <div class="pd-dt-slots-header-wrap">
                                                    <button type="button" class="pd-dt-date-nav-btn pd-dt-date-nav-prev"
                                                        aria-label="Xem ngày trước"
                                                        @click="$refs.pdSlotsScroll.scrollBy({ left: -560, behavior: 'smooth' })">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                                    </button>
                                                    <div class="pd-dt-slots-header-row" x-ref="pdSlotsHeaderScroll">
                                                        @foreach ($dates as $date)
                                                            <div class="pd-dt-slot-th pd-dt-date-th {{ $date['is_today'] ? 'is-today' : '' }}">
                                                                <div class="font-extrabold">{{ $date['day'] }}</div>
                                                                <div class="text-[10px] text-gray-500 font-normal">
                                                                    {{ $date['date'] }}</div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <button type="button" class="pd-dt-date-nav-btn pd-dt-date-nav-next"
                                                        aria-label="Xem thêm ngày"
                                                        @click="$refs.pdSlotsScroll.scrollBy({ left: 560, behavior: 'smooth' })">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <!-- <p class="text-[11px] text-gray-400 mt-1 mb-2">Kéo ngang hoặc bấm mũi tên để xem thêm ngày khác</p> -->

                                            {{-- Thân: 2 card tách rời — khung (viền/bo góc) đứng yên, chỉ nội
                                                 dung bên trong mỗi card cuộn dọc, đồng bộ 2 chiều qua @scroll --}}
                                            <div class="pd-dt-grid-outer">
                                                {{-- Cột Khung giờ (dọc) — ref pdDatesScroll giữ nguyên tên (chỉ đổi
                                                     nội dung), vẫn đồng bộ cuộn DỌC với body như cũ. --}}
                                                <div class="pd-dt-dates-card">
                                                    <div class="pd-dt-dates-scroll" x-ref="pdDatesScroll"
                                                        @scroll="$refs.pdSlotsScroll.scrollTop = $event.target.scrollTop">
                                                        @foreach ($timeSlots as $timeSlot)
                                                            @php
                                                                $rowStartTime = \Carbon\Carbon::parse($timeSlot['start_time']);
                                                                $rowEndTime = \Carbon\Carbon::parse($timeSlot['end_time']);
                                                                $rowIsOvernight =
                                                                    $rowEndTime->isNextDay() ||
                                                                    $rowEndTime->lt($rowStartTime) ||
                                                                    $timeSlot['over_night'] == 1;
                                                            @endphp
                                                            <div class="pd-dt-date-row pd-dt-time-row">
                                                                <div class="font-extrabold text-xs">
                                                                    {{ $rowStartTime->format('H:i') }} - {{ $rowEndTime->format('H:i') }}
                                                                </div>
                                                                @if ($rowIsOvernight)
                                                                    <div class="text-[10px] font-normal flex items-center gap-1" style="color:#1e3a8a;">
                                                                        <svg class="w-3 h-3 inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                                            <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.7-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162Z" clip-rule="evenodd" />
                                                                        </svg>
                                                                        Qua đêm
                                                                    </div>
                                                                @else
                                                                    <div class="text-[10px] text-gray-500 font-normal">Trong ngày</div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>

                                                {{-- Khối khung giờ --}}
                                                <div class="pd-dt-slots-outer">
                                                    <div class="pd-dt-slots-card">
                                                        <div class="pd-dt-slots-scroll" x-ref="pdSlotsScroll"
                                                            @scroll="$refs.pdDatesScroll.scrollTop = $event.target.scrollTop; $refs.pdSlotsHeaderScroll.scrollLeft = $event.target.scrollLeft">
                                                            {{-- Đổi trục: hàng = khung giờ, cột = ngày (yêu cầu người
                                                                 dùng) — vòng lặp đổi chỗ cho nhau, KHÔNG đụng logic
                                                                 tính giá/trạng thái từng ô bên trong (vẫn dùng đúng
                                                                 $date + $timeSlot của chính nó, không phụ thuộc thứ
                                                                 tự lồng). --}}
                                                            @foreach ($timeSlots as $timeSlot)
                                                                <div class="pd-dt-slots-row">
                                                                    @foreach ($dates as $date)
                                                                        @php
                                                                            $price = $timeSlot['timeslot_price'] ?? 0;
                                                                            $classes = '';
                                                                            $isSelectable = true;
                                                                            $finalPrice = $price;
                                                                            $currentDateTime = \Carbon\Carbon::createFromFormat(
                                                                                'd-m-Y H:i:s',
                                                                                $date['date'] .
                                                                                    ' ' .
                                                                                    $timeSlot['start_time'],
                                                                            );
                                                                            $status = 'available';
                                                                            $matchedItem = null;
                                                                            foreach (
                                                                                $product->orderItems
                                                                                as $orderItem
                                                                            ) {
                                                                                $checkin = \Carbon\Carbon::parse(
                                                                                    $orderItem->checkin_date,
                                                                                );
                                                                                $checkout = \Carbon\Carbon::parse(
                                                                                    $orderItem->checkout_date,
                                                                                );

                                                                                if (
                                                                                    $currentDateTime->between(
                                                                                        $checkin,
                                                                                        $checkout,
                                                                                    )
                                                                                ) {
                                                                                    // cancelled_at = khách đã tự huỷ đơn — không tính
                                                                                    // là chiếm chỗ nữa, tiếp tục tìm order item khác.
                                                                                    if ($orderItem->order && is_null($orderItem->order->cancelled_at)) {
                                                                                        $status =
                                                                                            $orderItem->order->status;
                                                                                        $matchedItem = $orderItem;
                                                                                        break;
                                                                                    }
                                                                                    continue;
                                                                                }
                                                                            }
                                                                            if ($status === 'pending') {
                                                                                $classes .= ' pending';
                                                                                $isSelectable = false;
                                                                            } elseif (
                                                                                $status === 'paid' ||
                                                                                $status === 'shipped'
                                                                            ) {
                                                                                $classes .= ' booked';
                                                                                $isSelectable = false;
                                                                            } else {
                                                                            }
                                                                            $orderColor = null;
                                                                            if ($matchedItem) {
                                                                                if (
                                                                                    in_array($status, [
                                                                                        'paid',
                                                                                        'shipped',
                                                                                        'confirmed',
                                                                                    ])
                                                                                ) {
                                                                                    $orderColor = '#4e6b4c';
                                                                                } elseif ($status === 'deposit') {
                                                                                    $orderColor = '#3b82f6';
                                                                                } elseif ($status === 'pending') {
                                                                                    $orderColor = '#f97316';
                                                                                } else {
                                                                                    $orderColor = '#94a3b8';
                                                                                }
                                                                            }

                                                                            if (
                                                                                $isSelectable &&
                                                                                $date['day'] === 'Hôm nay'
                                                                            ) {
                                                                                $slot_start_time =
                                                                                    $timeSlot['start_time'];
                                                                                $slot_end_time = $timeSlot['end_time'];

                                                                                $now = \Carbon\Carbon::now();

                                                                                $slotStartDateTime = \Carbon\Carbon::createFromFormat(
                                                                                    'd-m-Y H:i:s',
                                                                                    $date['date'] .
                                                                                        ' ' .
                                                                                        $slot_start_time,
                                                                                );
                                                                                $slotEndDateTime = \Carbon\Carbon::createFromFormat(
                                                                                    'd-m-Y H:i:s',
                                                                                    $date['date'] .
                                                                                        ' ' .
                                                                                        $slot_end_time,
                                                                                );

                                                                                if (
                                                                                    $slotEndDateTime->lt(
                                                                                        $slotStartDateTime,
                                                                                    )
                                                                                ) {
                                                                                    $slotEndDateTime->addDay();
                                                                                }

                                                                                if ($now->gte($slotEndDateTime)) {
                                                                                    $isSelectable = false;
                                                                                    $classes .= ' past-time';
                                                                                }
                                                                            }
                                                                            $realRoomTimeSlot = null;
                                                                            foreach ($product->roomTimeSlots as $rts) {
                                                                                if (
                                                                                    $rts->timeslot_id ==
                                                                                    $timeSlot['timeslot_id']
                                                                                ) {
                                                                                    $realRoomTimeSlot = $rts;
                                                                                    break;
                                                                                }
                                                                            }

                                                                            if (!$realRoomTimeSlot) {
                                                                                $realRoomTimeSlot = (object) [
                                                                                    'price' => $price,
                                                                                    'promotions' => collect(
                                                                                        $timeSlot['promotions'] ?? [],
                                                                                    ),
                                                                                    'settings' => null,
                                                                                    'timeSlot' => (object) [
                                                                                        'start_time' =>
                                                                                            $timeSlot['start_time'],
                                                                                        'end_time' =>
                                                                                            $timeSlot['end_time'],
                                                                                    ],
                                                                                ];
                                                                            }

                                                                            // --- Kiểm tra blocked dates ---
                                                                            $rtsSettings = is_array(
                                                                                $realRoomTimeSlot->settings,
                                                                            )
                                                                                ? $realRoomTimeSlot->settings
                                                                                : json_decode(
                                                                                        $realRoomTimeSlot->settings,
                                                                                        true,
                                                                                    ) ?? [];
                                                                            $blockedDates =
                                                                                $rtsSettings['blocked_dates'] ?? [];
                                                                            $slotDateYmd = $date[
                                                                                'carbon_date'
                                                                            ]->toDateString();
                                                                            if (in_array($slotDateYmd, $blockedDates)) {
                                                                                $isSelectable = false;
                                                                                $classes .= ' blocked';
                                                                            }

                                                                            // --- Đang bị ADMIN giữ chỗ real-time (xem TimeslotHoldService) ---
                                                                            // hiển thị MỜ (giống hệt cách 'blocked'/'booked' đã làm ở trên,
                                                                            // dùng chung style opacity:0.6 + pointer-events:none bên dưới),
                                                                            // KHÔNG phải hoàn toàn ẩn — khách vẫn thấy khung giờ tồn tại,
                                                                            // chỉ tạm thời không bấm được.
                                                                            $heldByName = null;
                                                                            if ($isSelectable && isset($realRoomTimeSlot->id)) {
                                                                                $activeHold = app(
                                                                                    \App\Services\TimeslotHoldService::class,
                                                                                )->isHeldByAdmin($realRoomTimeSlot->id, $slotDateYmd);
                                                                                if ($activeHold) {
                                                                                    $isSelectable = false;
                                                                                    $classes .= ' held';
                                                                                    $heldByName =
                                                                                        $activeHold->user->fullname ??
                                                                                        $activeHold->user->email ??
                                                                                        'nhân viên';
                                                                                }
                                                                            }

                                                                            $priceData = $this->calculateSlotPrice(
                                                                                $realRoomTimeSlot,
                                                                                $date['carbon_date']->format('Y-m-d'),
                                                                                $timeSlot['start_time'],
                                                                            );
                                                                            $finalPrice = $priceData['final_price'];
                                                                            $originalPrice =
                                                                                $priceData['original_price'];
                                                                            $priceAfterIncrease =
                                                                                $priceData['price_after_increase'];
                                                                            $basePrice = $priceData['original_price']; // ⭐ CẦN CÓ
                                                                            $slotIncreaseAmount =
                                                                                $priceData['increase_amount'] ?? 0; // ⭐ CẦN CÓ
                                                                            $promoDiscount =
                                                                                $priceData['total_discount'];
                                                                            $hasPromotion = $priceData['has_promotion'];
                                                                            $isIncrease = $priceData['is_increase'];
                                                                            $activePromotions =
                                                                                $priceData['promotions'] ?? [];

                                                                            $hasDiscountPromotion = false;
                                                                            $hasIncreasePromotion = false;
                                                                            $discountPromotions = [];
                                                                            $increasePromotions = [];
                                                                            foreach ($activePromotions as $promo) {
                                                                                if (
                                                                                    in_array($promo->type, [
                                                                                        'percentage',
                                                                                        'fixed',
                                                                                    ])
                                                                                ) {
                                                                                    $hasDiscountPromotion = true;
                                                                                    $discountPromotions[] = $promo;
                                                                                }
                                                                                if (
                                                                                    in_array($promo->type, [
                                                                                        'increase_percentage',
                                                                                        'increase_fixed',
                                                                                    ])
                                                                                ) {
                                                                                    $hasIncreasePromotion = true;
                                                                                    $increasePromotions[] = $promo;
                                                                                }
                                                                            }
                                                                            $showPromotion = $hasDiscountPromotion;
                                                                            if ($showPromotion) {
                                                                                $classes .= ' promo';
                                                                            }

                                                                            if (
                                                                                $hasIncreasePromotion &&
                                                                                !$hasDiscountPromotion
                                                                            ) {
                                                                                $classes .= ' promo-increase';
                                                                            }

                                                                            $displayPromotion =
                                                                                $increasePromotions[0] ?? null;

                                                                            $timeslotStatus = $status;
                                                                        @endphp

                                                                        <div class="pd-dt-slot-cell">
                                                                            <div class="w-full selectable {{ $classes }}"
                                                                                :class="selectedSlots.some(slot => slot
                                                                                    .key === '{{ $date['carbon_date']->format('Y-m-d') }}-{{ $timeSlot['timeslot_id'] }}'
                                                                                ) ? 'active' : ''"
                                                                                style="{{ !$isSelectable ? 'pointer-events: none; opacity: 0.6;' : 'cursor: pointer;' }}"
                                                                                data-room-id="{{ $product['id'] }}"
                                                                                data-timeslot-id="{{ $timeSlot['timeslot_id'] }}"
                                                                                data-iso-date="{{ $date['carbon_date']->format('Y-m-d') }}"
                                                                                x-on:click="toggleSlot(
                                                                '{{ $date['carbon_date']->format('Y-m-d') }}',
                                                                '{{ $timeSlot['timeslot_id'] }}',
                                                                '{{ $finalPrice }}',
                                                                '{{ $priceAfterIncrease }}',
                                                                '{{ $basePrice }}',
                                                                '{{ $slotIncreaseAmount }}',
                                                                '{{ $promoDiscount }}',
                                                                '{{ \Carbon\Carbon::parse($timeSlot['start_time'])->format('H:i') }}',
                                                                '{{ \Carbon\Carbon::parse($timeSlot['end_time'])->format('H:i') }}',
                                                                '{{ $timeslotStatus }}',
                                                                '{{ $product['id'] }}',
                                                                '{{ $product['name'] }}',
                                                                '{{ $timeSlot['timeslot_label'] }}',
                                                                '{{ $timeSlot['over_night'] ?? 0 }}'
                                                             )">

                                                                                @if (str_contains($classes, 'blocked'))
                                                                                    <svg class="lock-icon" viewBox="0 0 24 24"
                                                                                        fill="none" stroke="currentColor"
                                                                                        stroke-width="2" stroke-linecap="round"
                                                                                        stroke-linejoin="round">
                                                                                        <rect x="4" y="11" width="16"
                                                                                            height="9" rx="2" />
                                                                                        <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                                                                                    </svg>
                                                                                @endif

                                                                                @if (str_contains($classes, 'held'))
                                                                                    <div class="lock-icon" title="Đang được {{ $heldByName }} xử lý cho 1 đơn khác">
                                                                                        <svg viewBox="0 0 24 24" fill="none"
                                                                                            stroke="currentColor" stroke-width="2"
                                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                                            <rect x="4" y="11" width="16" height="9" rx="2" />
                                                                                            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                                                                                        </svg>
                                                                                    </div>
                                                                                @endif

                                                                                @if ($hasIncreasePromotion && $displayPromotion && $displayPromotion->image)
                                                                                    <div
                                                                                        class="promotion-corner-image">
                                                                                        <img src="{{ asset('storage/' . $displayPromotion->image) }}"
                                                                                            alt="{{ $displayPromotion->name }}"
                                                                                            class="corner-img">
                                                                                    </div>
                                                                                @endif

                                                                                @if ($hasIncreasePromotion && $displayPromotion && $displayPromotion->lable_client)
                                                                                    <div
                                                                                        class="promotion-center-label">
                                                                                        {{ $displayPromotion->lable_client }}
                                                                                    </div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            @endforeach
                                                        </div>{{-- /pd-dt-slots-scroll --}}
                                                    </div>{{-- /pd-dt-slots-card --}}
                                                </div>{{-- /pd-dt-slots-outer --}}
                                            </div>{{-- /pd-dt-grid-outer --}}
                                        </div>{{-- /desktop --}}
                                    </div>{{-- /shared x-data --}}

                                </div>
            @elseif($bookingStyle == 2)
                {{-- Style 2: Daterange picker hiển thị ngay dưới ảnh/tiện ích --}}
                @php
                    $product->loadMissing(['roomTimeSlots.timeSlot', 'roomTimeSlots.promotions']);
                    $pricePerNight = $product->price ?? 0;
                    $productDiscount = (int) ($product->discount ?? 0);
                    $bookedRanges = \Modules\Payment\Entities\OrderItem::where('product_id', $product->id)
                        ->whereNotNull('checkin_date')
                        ->whereNotNull('checkout_date')
                        ->whereHas('order', function ($q) {
                            $q->where(function ($inner) {
                                $inner
                                    ->whereIn('status', ['paid', 'deposit', 'shipping', 'completed', 'confirmed'])
                                    ->orWhere(function ($pq) {
                                        $pq->where('status', 'pending')->where(function ($eq) {
                                            $eq->whereNull('expired_at')->orWhere('expired_at', '>', now());
                                        });
                                    });
                            });
                        })
                        ->get()
                        ->map(
                            fn($it) => [
                                'start' => \Carbon\Carbon::parse($it->checkin_date)->format('Y-m-d'),
                                'end' => \Carbon\Carbon::parse($it->checkout_date)->format('Y-m-d'),
                            ],
                        )
                        ->values()
                        ->toArray();
                    $dayPriceMap = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 0];
                    $dayPrices = [];
                    foreach ($product->day_prices ?? [] as $k => $v) {
                        if (isset($dayPriceMap[$k]) && (int) $v > 0) {
                            $dayPrices[$dayPriceMap[$k]] = (int) $v;
                        }
                    }
                    $datePrices = [];
                    $promoLabels = [];
                    $promoDiscounts = [];
                    $surchargeData = [];
                    $adminBlockedRanges = array_values(
                        array_filter(
                            $product->room_config['blocked_ranges'] ?? [],
                            fn($r) => !empty($r['start']) && !empty($r['end']),
                        ),
                    );
                    $nowDt = now();
                    foreach ($product->roomTimeSlots ?? [] as $rts) {
                        if (
                            $rts->timeSlot &&
                            ($rts->timeSlot->type ?? '') === 'date' &&
                            !empty($rts->timeSlot->label)
                        ) {
                            $dk = $rts->timeSlot->label;
                            if ($rts->price !== null) {
                                $datePrices[$dk] = (int) $rts->price;
                            }
                            $maxDisc = 0;
                            $bestLbl = '';
                            $surcharges = [];
                            foreach ($rts->promotions as $promo) {
                                if (!$promo->is_active) {
                                    continue;
                                }
                                $inPeriod =
                                    (!$promo->start_at || $promo->start_at <= $nowDt) &&
                                    (!$promo->end_at || $promo->end_at >= $nowDt);
                                if (!$inPeriod) {
                                    continue;
                                }
                                if ($promo->type === 'percentage') {
                                    if ((float) $promo->value > $maxDisc) {
                                        $maxDisc = (float) $promo->value;
                                        $bestLbl = $promo->lable_client ?: $promo->name;
                                    }
                                } elseif (in_array($promo->type, ['increase_percentage', 'increase_fixed'])) {
                                    $surcharges[] = ['type' => $promo->type, 'value' => (float) $promo->value];
                                }
                            }
                            if ($maxDisc > 0) {
                                $promoDiscounts[$dk] = $maxDisc;
                                $promoLabels[$dk] = $bestLbl;
                            }
                            if (!empty($surcharges)) {
                                $surchargeData[$dk] = $surcharges;
                            }
                        }
                    }
                @endphp
                <div wire:ignore id="pd-timeslots-section">
                    @include('bladethemev1::components.product-detail.daterange-picker', [
                        'pricePerNight' => $pricePerNight,
                        'productDiscount' => $productDiscount,
                        'bookedRanges' => $bookedRanges,
                        'adminBlockedRanges' => $adminBlockedRanges,
                        'datePrices' => $datePrices,
                        'dayPrices' => $dayPrices,
                        'promoLabels' => $promoLabels,
                        'promoDiscounts' => $promoDiscounts,
                        'surchargeData' => $surchargeData,
                    ])
                </div>
                @endif

                {{-- Section 2 cột dưới lịch đặt sân: cột 1 = Chi tiết thanh toán, cột 2 = Thời
                     gian đã chọn (nhận sân/trả sân/tổng khung giờ). --}}
                @if ($bookingStyle == 1)
                    <div class="w-full mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Cột 1: Chi tiết thanh toán --}}
                        <div class="w-full bg-white rounded-2xl p-4 border border-[#DDDDDD]">
                            <h3 class="text-base font-semibold text-[#222222] mb-3">Chi tiết thanh toán</h3>

                            <div wire:loading wire:target="selectedSlots" class="animate-pulse space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <div class="h-3 w-20 bg-gray-200 rounded"></div>
                                    <div class="h-3 w-16 bg-gray-200 rounded"></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="h-3 w-28 bg-gray-200 rounded"></div>
                                    <div class="h-3 w-14 bg-gray-200 rounded"></div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div class="h-3 w-24 bg-gray-200 rounded"></div>
                                    <div class="h-3 w-16 bg-gray-200 rounded"></div>
                                </div>
                                <div class="flex items-center justify-between pt-3 mt-2 border-t border-[#DDDDDD]">
                                    <div class="h-4 w-28 bg-gray-200 rounded"></div>
                                    <div class="h-5 w-20 bg-gray-200 rounded"></div>
                                </div>
                            </div>

                            <div wire:loading.remove wire:target="selectedSlots"
                                class="w-full text-left text-sm space-y-1.5">

                                {{-- Giá cơ bản --}}
                                <div class="flex items-center justify-between">
                                    <span class="text-[#717171]">Giá cơ bản</span>
                                    <span class="font-semibold text-[#222222]">{{ number_format($originalTotalAmount, 0, ',', '.') }}đ</span>
                                </div>

                                {{-- Chi tiết phụ thu --}}
                                @if ($increaseAmount > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-[#717171]">Phụ thu</span>
                                        <span class="font-semibold text-[#222222]">+{{ number_format($increaseAmount, 0, ',', '.') }}đ</span>
                                    </div>
                                @endif

                                {{-- Phụ phí khách --}}
                                @if ($extraFee > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-[#717171]">Phụ phí ({{ $guests - 2 }} khách)</span>
                                        <span class="font-semibold text-[#222222]">+{{ number_format($extraFee, 0, ',', '.') }}đ</span>
                                    </div>
                                @endif

                                {{-- Chi tiết khuyến mãi (CHỈ hiển thị nếu KHÔNG phải full booking) --}}
                                @if ($promoDiscountAmount > 0 && !$hasFullDayBooking)
                                    <div class="flex items-center justify-between">
                                        <span class="text-[#717171]">Khuyến mãi</span>
                                        <span class="font-semibold text-primary">-{{ number_format($promoDiscountAmount, 0, ',', '.') }}đ</span>
                                    </div>
                                @endif

                                {{-- Giảm giá từ coupon (có thể nhiều mã cùng lúc) --}}
                                @if (count($appliedCoupons) > 0 && $couponDiscountAmount > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-[#717171]">Mã giảm giá ({{ collect($appliedCoupons)->pluck('code')->implode(', ') }})</span>
                                        <span class="font-semibold text-primary">-{{ number_format($couponDiscountAmount, 0, ',', '.') }}đ</span>
                                    </div>
                                @endif

                                {{-- Giảm giá book nhiều giờ (CHỈ khi KHÔNG phải full booking) --}}
                                @if (!$hasFullDayBooking && $bulkDiscountAmount > 0 && count($selectedSlots) >= 2)
                                    <div class="flex items-center justify-between">
                                        <span class="text-[#717171]">Giảm giá đặt nhiều khung giờ</span>
                                        <span class="font-semibold text-primary">-{{ number_format($bulkDiscountAmount, 0, ',', '.') }}đ</span>
                                    </div>
                                @endif

                                {{-- Giảm giá Full booking --}}
                                @if ($fullBookingDiscount > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-[#717171]">Giảm giá đặt Full sân</span>
                                        <span class="font-semibold text-primary">-{{ number_format($fullBookingDiscount, 0, ',', '.') }}đ</span>
                                    </div>
                                @endif

                                {{-- Dịch vụ thêm --}}
                                @php
                                    $serviceTotal = 0;
                                    if (!empty($selectedServices) && $additionalServices) {
                                        foreach ($selectedServices as $id => $qty) {
                                            $service = $additionalServices->firstWhere('id', $id);
                                            if ($service && $qty > 0) {
                                                $serviceTotal += $service->price * $qty;
                                            }
                                        }
                                    }
                                    $serviceCount = array_sum($selectedServices ?? []);
                                @endphp
                                @if ($serviceTotal > 0)
                                    <div class="flex items-center justify-between">
                                        <span class="text-[#717171]">Dịch vụ thêm ({{ $serviceCount }})</span>
                                        <span class="font-semibold text-[#222222]">+{{ number_format($serviceTotal, 0, ',', '.') }}đ</span>
                                    </div>
                                @endif

                                {{-- Tổng tiền --}}
                                <div class="flex items-center justify-between pt-3 mt-2 border-t border-[#DDDDDD]">
                                    <span class="text-sm font-semibold text-[#222222]">Tổng tiền tạm tính</span>
                                    <span class="text-lg font-bold text-primary">{{ number_format($totalAmount, 0, ',', '.') }}đ</span>
                                </div>

                            </div>
                        </div>

                        {{-- Cột 2: Thời gian đã chọn --}}
                        <div class="w-full bg-white rounded-2xl p-4 border border-[#DDDDDD]">
                            <h3 class="text-base font-semibold text-[#222222] mb-3">Thời gian đã chọn</h3>

                            @php
                                // Tính TRỰC TIẾP từ $selectedSlots ngay tại đây (thay vì đọc $startTime/
                                // $endTime) — 2 property đó chỉ được cập nhật trong updatedSelectedSlots(),
                                // có lúc chưa kịp đồng bộ đúng lúc Blade render (đã ghi nhận thực tế: panel
                                // chỉ hiện đúng khung giờ vừa bấm cuối thay vì gộp min/max toàn bộ khung đã
                                // chọn). Gọi lại đúng công thức min(start)/max(end) mỗi lần render đảm bảo
                                // luôn khớp với số lượng "N khung giờ đã chọn" hiển thị ngay bên dưới.
                                [$pdDisplayStart, $pdDisplayEnd] = $this->computeCheckinCheckoutFromSlots();
                            @endphp

                            @if (!empty($selectedSlots) && !empty($pdDisplayStart) && !empty($pdDisplayEnd))
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-xl border border-[#DDDDDD] p-3 text-center">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-[#717171] mb-1">Nhận sân</p>
                                        <p class="text-lg font-bold text-[#222222]">{{ \Carbon\Carbon::parse($pdDisplayStart)->format('H:i') }}</p>
                                        <p class="text-xs text-[#717171] mt-0.5">{{ \Carbon\Carbon::parse($pdDisplayStart)->format('d/m/Y') }}</p>
                                    </div>
                                    <div class="rounded-xl border border-[#DDDDDD] p-3 text-center">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-[#717171] mb-1">Trả sân</p>
                                        <p class="text-lg font-bold text-[#222222]">{{ \Carbon\Carbon::parse($pdDisplayEnd)->format('H:i') }}</p>
                                        <p class="text-xs text-[#717171] mt-0.5">{{ \Carbon\Carbon::parse($pdDisplayEnd)->format('d/m/Y') }}</p>
                                    </div>
                                </div>
                                <p class="text-xs text-center font-semibold text-primary mt-3">
                                    {{ count($selectedSlots) }} khung giờ đã chọn
                                </p>
                            @else
                                <p class="text-sm text-[#717171] text-center py-6">Vui lòng chọn khung giờ ở lịch đặt sân phía trên.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Lưu ý giảm giá - CẬP NHẬT --}}
                    <div class="text-left mt-4">
                        @if ($hasFullDayBooking)
                            <p class="text-primary text-xs">
                                * Đã áp dụng ưu đãi đặc biệt cho Full sân — các khuyến mãi khác không áp dụng khi đặt full sân.
                            </p>
                        @else
                            @php
                                $bulkRules = $product->bulk_discount_rules ?? [];
                                $bulkRuleHint = collect($bulkRules)
                                    ->sortBy('slots')
                                    ->map(
                                        fn($r) => "{$r['discount']}% khi chọn {$r['slots']} khung giờ",
                                    )
                                    ->implode(', ');
                            @endphp
                            @if ($bulkRuleHint)
                                <p class="text-primary text-xs">
                                    * Khách hàng được giảm thêm {{ $bulkRuleHint }}
                                </p>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- wire:ignore.self: trạng thái mở/thu gọn của bottom-sheet (mobile) là class
             pd-sheet-peek/pd-sheet-full gắn thẳng qua classList JS (setState(), xem
             components/product-detail/infomation-book-room.blade.php), KHÔNG nằm trong state của
             Livewire — nếu không ignore, mỗi lần Livewire morph lại component (vd: upload ảnh CCCD
             qua @this.upload(), gõ input có wire:model...) sẽ ghi đè div này về đúng HTML server
             render (không có class đó), làm sheet tự đóng lại ngay sau khi vừa mở. Chỉ ignore
             CHÍNH thẻ này (.self) — các phần tử con bên trong (input, lỗi validate, ảnh preview...)
             vẫn được Livewire cập nhật bình thường. --}}
        <div class="w-full lg:sticky lg:top-32" id="pd-booking-form" wire:ignore.self
            data-preselected="{{ $fromBookingPage ? '1' : '0' }}">
            {{-- Thông tin đặt sân --}}
            @include('bladethemev1::components.product-detail.infomation-book-room')
        </div>
    </div>
</div>
</div>

{{-- Đặt ngoài #pd-booking-form (nơi có transform tạo containing block) để modal luôn hiển thị full màn hình --}}
@include('bladethemev1::components.payments.booking-confirmation-modal')

{{-- Đánh giá sao (thay cho khối Bình luận cũ) --}}
@include('bladethemev1::components.product-detail.ratings')

@php
    $pdAddress = $product->address ?? null;
    $pdLat = $product->latitude ?? null;
    $pdLng = $product->longitude ?? null;
    $pdHasCoords = !empty($pdLat) && !empty($pdLng);
    // Dùng toạ độ (nếu có) để đảm bảo ghim đúng vị trí thay vì chỉ tìm theo địa chỉ text
    $pdMapQuery = $pdHasCoords ? $pdLat . ',' . $pdLng : $pdAddress;
    $pdMapUrl =
        $product->map_url ?: ($pdMapQuery ? 'https://www.google.com/maps/search/?q=' . urlencode($pdMapQuery) : null);
    $pdMapEmbedSrc = $pdMapQuery
        ? 'https://www.google.com/maps?q=' .
            urlencode($pdMapQuery) .
            ($pdHasCoords ? '&ll=' . $pdLat . ',' . $pdLng . '&z=16' : '') .
            '&output=embed'
        : null;
@endphp
@if ($pdAddress)
    <div class="mt-10 pt-8 pb-6 md:pb-14 border-t border-gray-200">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">Địa chỉ</h2>
                <p class="text-base text-gray-700 mt-1">{{ $pdAddress }}</p>
            </div>
            @if ($pdMapUrl)
                <a href="{{ $pdMapUrl }}" target="_blank" rel="noopener"
                    class="shrink-0 inline-flex items-center gap-1.5 text-sm font-semibold text-primary underline underline-offset-2 whitespace-nowrap">
                    Xem trên Google Maps
                </a>
            @endif
        </div>

        @if ($pdMapEmbedSrc)
            <div class="w-full rounded-xl overflow-hidden border border-gray-200" style="height: 420px;">
                <iframe src="{{ $pdMapEmbedSrc }}" class="w-full h-full border-0" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
        @endif
    </div>
@endif

</div>
</div>
@push('scripts')
    <script>
        function processAndUpload(input, field, opts = {}) {
            const file = input.files[0];
            if (!file) return;

            const overlay = document.getElementById(`loading-${field}`);
            const statusText = document.getElementById(`status-${field}`);
            const progressBar = document.getElementById(`progress-${field}`);

            // 1. Hiển thị trạng thái đang xử lý
            overlay.classList.remove('hidden');
            statusText.innerText = "Đang tối ưu...";
            progressBar.style.width = "10%";

            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function(e) {
                const img = new Image();
                img.src = e.target.result;
                img.onload = function() {
                    // 2. Nén ảnh bằng Canvas
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;
                    const max_size = opts.maxSize || 1200;
                    const quality = opts.quality || 0.7;

                    if (width > height) {
                        if (width > max_size) {
                            height *= max_size / width;
                            width = max_size;
                        }
                    } else {
                        if (height > max_size) {
                            width *= max_size / height;
                            height = max_size;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);

                    canvas.toBlob(function(blob) {
                        const safeFileName = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                        const compressedFile = new File([blob], safeFileName, {
                            type: 'image/jpeg'
                        });

                        statusText.innerText = "Đang tải lên...";

                        // 3. Hiển thị preview ngay lập tức bằng ObjectURL (không cần chờ server)
                        const previewUrl = URL.createObjectURL(blob);
                        const previewImg = document.getElementById(`preview-${field}`);
                        const placeholder = document.getElementById(`placeholder-${field}`);
                        const checkmark = document.getElementById(`checkmark-${field}`);
                        if (previewImg) {
                            previewImg.src = previewUrl;
                            previewImg.classList.remove('hidden');
                        }
                        if (placeholder) {
                            placeholder.classList.add('hidden');
                        }
                        if (checkmark) {
                            checkmark.classList.remove('hidden');
                        }

                        statusText.innerText = "Đang tải lên...";

                        // 4. Sử dụng API của Livewire để upload thủ công
                        @this.upload(field, compressedFile,
                            (uploadedName) => {
                                overlay.classList.add('hidden'); // Hoàn tất
                            },
                            () => {
                                overlay.classList.add('hidden');
                                // Ẩn preview nếu upload thất bại
                                if (previewImg) {
                                    previewImg.classList.add('hidden');
                                    previewImg.src = '';
                                }
                                if (placeholder) {
                                    placeholder.classList.remove('hidden');
                                }
                                if (checkmark) {
                                    checkmark.classList.add('hidden');
                                }
                                alert('Lỗi tải lên, vui lòng thử lại.');
                            },
                            (event) => {
                                // Cập nhật % thanh tiến trình thực tế
                                progressBar.style.width = event.detail.progress + "%";
                            }
                        );
                    }, 'image/jpeg', quality);
                };
            };
        }
    </script>
    <script>
        function pdShareRoom() {
            const shareUrl = window.location.href;

            const notify = (message, type) => {
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: { message, type }
                }));
            };

            const fallbackCopy = () => {
                try {
                    const input = document.createElement('textarea');
                    input.value = shareUrl;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    input.setSelectionRange(0, shareUrl.length);
                    const ok = document.execCommand('copy');
                    document.body.removeChild(input);
                    return ok;
                } catch (e) {
                    return false;
                }
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shareUrl)
                    .then(() => notify('Đã sao chép liên kết', 'success'))
                    .catch(() => {
                        const ok = fallbackCopy();
                        notify(ok ? 'Đã sao chép liên kết' : 'Không thể sao chép liên kết', ok ? 'success' : 'error');
                    });
                return;
            }

            const ok = fallbackCopy();
            notify(ok ? 'Đã sao chép liên kết' : 'Không thể sao chép liên kết', ok ? 'success' : 'error');
        }

        (function() {
            const btns = [document.getElementById('pd-wishlist-btn'), document.getElementById('pd-wishlist-btn-mobile')]
                .filter(Boolean);
            if (!btns.length) return;

            const productId = btns[0].getAttribute('data-product-id');

            const notify = (message, type) => {
                window.dispatchEvent(new CustomEvent('notify', {
                    detail: { message, type }
                }));
            };

            const paintIcon = (active) => {
                btns.forEach((btn) => {
                    const icon = btn.querySelector('svg');
                    icon.setAttribute('fill', active ? '#ef4444' : 'none');
                    icon.style.color = active ? '#ef4444' : '';
                });
            };

            const setActive = (active) => {
                btns.forEach((btn) => btn.setAttribute('data-active', active ? '1' : '0'));
                paintIcon(active);
            };

            // Xác định trạng thái yêu thích ban đầu (nếu đã đăng nhập)
            const token = localStorage.getItem('auth_token');
            if (token) {
                fetch('/api/wishlist', {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json'
                        },
                    })
                    .then((res) => res.json())
                    .then((json) => {
                        const saved = (json.data || []).some((room) => String(room.id) === String(productId));
                        setActive(saved);
                    })
                    .catch(() => {});
            }

            btns.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const currentToken = localStorage.getItem('auth_token');
                    if (!currentToken) {
                        window.dispatchEvent(new CustomEvent('open-auth-modal'));
                        return;
                    }

                    const next = btn.getAttribute('data-active') !== '1';
                    setActive(next);

                    fetch('/api/wishlist/' + productId + '/toggle', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + currentToken,
                            'Accept': 'application/json'
                        },
                    })
                        .then((res) => {
                            if (!res.ok) throw new Error('toggle failed');
                            notify(next ? 'Đã lưu vào yêu thích' : 'Đã bỏ khỏi yêu thích', 'success');
                        })
                        .catch(() => {
                            setActive(!next);
                            notify('Không thể cập nhật yêu thích. Vui lòng thử lại.', 'error');
                        });
                });
            });
        })();
    </script>
@endpush

{{-- Real-time "khung giờ đang bị admin giữ chỗ" (echo-client.js) giờ đã nhúng chung ở
     layouts/master.blade.php (áp dụng cho MỌI trang, không riêng trang này nữa) — không cần
     @push('scripts') riêng ở đây nữa. --}}
