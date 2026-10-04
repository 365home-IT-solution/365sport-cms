{{--
    Hàng thanh tìm kiếm + điều hướng nhanh dưới top-bar xanh, theo mẫu tham khảo: ô "Tìm kiếm",
    icon lọc, và link Bản đồ ("Sân đã đặt"/"Yêu thích" đã chuyển vào dropdown thông tin khách hàng
    ở top-bar.blade.php).

    TOÀN BỘ lọc (submit form, icon lọc, nút "Tìm sân gần đây") đều đổ về trang bản đồ /ban-do
    (route('map.page'), xem MapPage::mount()) — KHÔNG còn điều hướng sang trang danh sách
    /s/{province} hay route('product.search') nữa (trước đây có 2 luồng lọc khác nhau tuỳ trang,
    người dùng yêu cầu gộp lại chỉ còn 1 nơi lọc duy nhất). Popup chỉ dùng bộ lọc CÓ THẬT trong hệ
    thống (RoomSearchService::search() / app/GraphQL/SearchSchema.php cũng hỗ trợ các field này,
    dùng chung cho /s/{province} nếu sau này cần bật lại):
    - "Môn thể thao": RoomType thật (Modules\Product\App\Models\RoomType) — KHÔNG bịa chip theo
      ảnh mẫu (Pickleball/Cầu lông/... ảnh mẫu có nhiều môn hơn DB thật đang có).
    - "Khu vực"/"Phường-Xã": Province/Ward thật (ProvinceBranch.province_id/ward_code).
    - "Tìm sân gần đây": Geolocation API, lat/lng truyền qua MapPage.
    Đã bỏ hẳn "Loại lịch" (Sân trống/Sự kiện-Xé vé/Ghép đội) trong ảnh mẫu vì Sự kiện/Xé vé/Ghép
    đội chưa có tính năng thật nào trong hệ thống — không dựng UI cho thứ chưa lọc được gì.
--}}
@php
    $generalSettings = new \App\Settings\GeneralSettings();
    $siteLogo = $generalSettings->brand_logo ?? null;
    $filterRoomTypes = \Modules\Product\App\Models\RoomType::orderBy('name')->get(['id', 'name', 'slug']);
    // Chỉ tỉnh CÓ SÂN THẬT (chi nhánh bật + có sân đang bán) — cùng quy tắc với chip tỉnh trang chủ và API /provinces.
    $filterProvinces = \App\Models\Province::withCourts()->orderBy('name')->get(['id', 'name', 'slug', 'code']);
    // Phường/Xã thật (App\Models\Ward, ward_code trên ProvinceBranch — RoomSearchService::search()
    // và SearchSchema.php ĐÃ hỗ trợ sẵn lọc theo ward_code, chỉ chưa có ở giao diện) — chỉ lấy
    // phường thuộc các tỉnh đang có chi nhánh thật (province_code khớp $filterProvinces) để danh
    // sách không phình to vô ích với hàng trăm phường không liên quan.
    $filterWards = \App\Models\Ward::whereIn('province_code', $filterProvinces->pluck('code'))
        ->orderBy('name')
        ->get(['code', 'name', 'province_code']);
@endphp

{{-- lg:top-14 (56px) = chiều cao thật của top-bar phía trên (đã dùng làm chuẩn ở nơi khác trong
     site, xem scroll-margin-top của .book-panel) — dính ngay dưới top-bar khi cuộn, không chồng
     lên nhau. z-[1140] thấp hơn top-bar (1150) 1 bậc vì cùng dính cả 2, top-bar phải luôn ở TRÊN
     nếu có sai lệch chiều cao nhỏ giữa các trang (nội dung ngày/tên khách dài ngắn khác nhau). --}}
<div class="w-full border-b border-gray-100 bg-white lg:sticky lg:top-14 lg:z-[1140]"
     x-data="{
        filterOpen: false,
        filterType: '',
        filterProvince: '',
        filterProvinceCode: '',
        filterWard: '',
        locating: false,
        locateError: '',
        wards: {{ $filterWards->map(fn ($w) => ['code' => $w->code, 'name' => $w->name, 'province_code' => $w->province_code])->values()->toJson() }},
        provinceCodes: {{ $filterProvinces->mapWithKeys(fn ($p) => [$p->slug => $p->code])->toJson() }},
        get wardsInProvince() {
            if (!this.filterProvinceCode) return [];
            var pc = this.filterProvinceCode;
            return this.wards.filter(function (w) { return w.province_code === pc; });
        },
        pickProvince(slug) {
            this.filterProvince = slug;
            this.filterProvinceCode = slug ? (this.provinceCodes[slug] || '') : '';
            this.filterWard = '';
        },
        resetFilters() {
            this.filterType = '';
            this.pickProvince('');
        },
        // Tìm sân gần đây: đang SẴN ở trang bản đồ thì chỉ lia bản đồ tới vị trí thật, không tải
        // lại trang (window.__panToMyLocation, xem livewire/map-page.blade.php) — ở các trang
        // khác, điều hướng sang trang bản đồ kèm lat/lng (MapPage đọc và tự pan tới sau khi tải
        // xong, xem initMap() trong map-page.blade.php).
        findNearby() {
            if (!navigator.geolocation) { this.locateError = 'Trình duyệt không hỗ trợ định vị.'; return; }
            this.locating = true;
            this.locateError = '';
            navigator.geolocation.getCurrentPosition((pos) => {
                this.locating = false;
                var lat = pos.coords.latitude, lng = pos.coords.longitude;
                if (typeof window.__panToMyLocation === 'function') {
                    window.__panToMyLocation(lat, lng);
                    this.filterOpen = false;
                    return;
                }
                window.location.href = '{{ route('map.page') }}?lat=' + lat + '&lng=' + lng;
            }, () => {
                this.locating = false;
                this.locateError = 'Không lấy được vị trí — hãy cho phép quyền định vị.';
            }, { timeout: 10000 });
        },
     }">
    {{-- TOÀN BỘ lọc (môn thể thao/khu vực/phường/tìm gần đây) đều đổ về trang bản đồ /ban-do —
         không còn điều hướng sang trang danh sách /s/{slug} nữa (trước đây có, nhưng người dùng
         không muốn tách 2 luồng lọc khác nhau giữa các trang — chỉ còn đúng 1 nơi lọc duy nhất).
         MapPage::mount() đọc q/type/province/ward_code/lat/lng từ query string. --}}
    <form id="search-bar-form" method="GET"
          action="{{ route('map.page') }}"
          class="mx-auto flex max-w-7xl items-center gap-2 px-4 py-3 sm:gap-4 sm:px-6">
        {{-- Chỉ hiện ở khoảng tablet (sm–lg) — desktop (lg+) đã có logo riêng ở top-bar phía trên
             (components/home/top-bar.blade.php), tránh lặp 2 logo. --}}
        <a href="/" class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#0B6B3A]/10 sm:flex lg:hidden">
            @if ($siteLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($siteLogo) }}" alt="" class="h-6 w-6 object-contain">
            @else
                <x-heroicon-s-globe-asia-australia class="h-5 w-5 text-[#0B6B3A]" />
            @endif
        </a>

        <div class="flex flex-1 items-center gap-2 rounded-full border border-gray-200 px-4 py-2">
            <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
            </svg>
            <input type="text" name="q" placeholder="Tìm kiếm"
                   class="w-full border-0 p-0 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0">
        </div>

        {{-- Chỉ MỞ POPUP lọc (bên dưới), không submit form nữa. --}}
        <button type="button" @click="filterOpen = true"
                class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition hover:bg-gray-50"
                aria-label="Bộ lọc tìm kiếm">
            <x-heroicon-o-adjustments-horizontal class="h-5 w-5" />
            <span x-show="filterType || filterProvince || filterWard" x-cloak
                  class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-[#0B6B3A] ring-2 ring-white"></span>
        </button>

        {{-- "Sân đã đặt"/"Yêu thích" nằm trong dropdown tài khoản (top-bar) — chỉ còn "Bản đồ" ở đây. --}}
        <div class="hidden shrink-0 items-center gap-5 md:flex">
            <a href="{{ route('map.page') }}" class="flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-[#0B6B3A]">
                <x-heroicon-o-map class="h-5 w-5" />
                Bản đồ
            </a>
        </div>

        {{-- Ẩn — chỉ để gửi kèm 'type' (RoomType.slug) qua GET khi submit, search-results.js đã
             đọc sẵn query 'type' (xem buildSearchFilters() trong public/js/search-results.js). --}}
        <input type="hidden" name="type" x-model="filterType">
        {{-- Chỉ THỰC SỰ dùng khi submit vào trang bản đồ (isMapPage, xem MapPage::mount()) — ở
             các trang khác, khu vực đã nằm trong path "/s/{slug}" nên input này dư vô hại (bị bỏ
             qua). --}}
        <input type="hidden" name="province" x-model="filterProvince">
        {{-- ward_code: RoomSearchService::search()/SearchSchema.php + MapPage::mount() đều đã đọc
             field này (thêm ở đợt cập nhật lọc Phường/Xã). --}}
        <input type="hidden" name="ward_code" x-model="filterWard">
    </form>

    {{-- ── POPUP LỌC (trượt từ phải vào, theo ảnh mẫu) ── --}}
    {{-- z-[1200]: PHẢI cao hơn MỌI phần tử fixed khác trong layout — header #main-header-bar
         (z-1150, ép fixed ở 1 số trang như booking-board.blade.php), bottom nav mobile
         livewire/bottom-sidebar.blade.php (z-1000), và hàng chip chi nhánh nổi trên bản đồ
         (z-500, livewire/map-page.blade.php). TRƯỚC ĐÂY chỉ z-[600]: thấp hơn bottom-sidebar/
         header nên trên mobile, thanh điều hướng dưới cùng đè lên đúng nút "Tìm kiếm" ở chân
         popup — nhìn như popup "nhỏ lại"/mất nút dù thật ra nút vẫn ở đó, chỉ bị che (bug đã gặp,
         ảnh thực tế trên iPhone giả lập DevTools). --}}
    <div x-show="filterOpen" x-cloak class="fixed inset-0 z-[1200]">
        {{-- Lớp bắt sự kiện "bấm ra ngoài để đóng" — để TRONG SUỐT (không tô đen mờ) để giao diện
             chính phía sau không bị tối/ảnh hưởng, chỉ popup bên phải nổi lên. --}}
        <div class="absolute inset-0" @click="filterOpen = false"></div>

        <div class="absolute inset-y-0 right-0 flex w-full max-w-sm flex-col bg-white shadow-2xl"
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">

            <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
                <button type="button" @click="filterOpen = false" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <h2 class="flex-1 text-base font-bold text-gray-900">Tìm kiếm</h2>
                <button type="button" @click="resetFilters()" class="text-xs font-semibold text-gray-400 transition hover:text-[#0B6B3A]">
                    Đặt lại
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-5 space-y-7">
                {{-- Tìm sân gần đây — định vị thật (Geolocation API), xem findNearby() ở x-data --}}
                <button type="button" @click="findNearby()" :disabled="locating"
                        class="flex w-full items-center gap-3 rounded-2xl border border-[#0B6B3A]/25 bg-[#0B6B3A]/5 px-4 py-3 text-left transition hover:bg-[#0B6B3A]/10 disabled:opacity-60">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-[#0B6B3A] shadow-sm">
                        <svg x-show="!locating" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg x-show="locating" x-cloak class="h-5 w-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V2.5"/>
                        </svg>
                    </span>
                    <span class="flex-1">
                        <span class="block text-sm font-bold text-gray-900" x-text="locating ? 'Đang định vị...' : 'Tìm sân gần đây'"></span>
                        <span class="block text-xs text-gray-500">Dùng vị trí hiện tại của bạn</span>
                    </span>
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <p x-show="locateError" x-cloak class="-mt-5 text-xs font-medium text-red-500" x-text="locateError"></p>

                {{-- Môn thể thao — RoomType thật, không bịa thêm --}}
                <div>
                    <p class="mb-2.5 text-sm font-bold text-gray-900">Môn thể thao</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="filterType = ''"
                                class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                                :class="filterType === '' ? 'border-[#0B6B3A] bg-[#0B6B3A] text-white shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                            Tất cả
                        </button>
                        @foreach ($filterRoomTypes as $rt)
                        <button type="button" @click="filterType = '{{ $rt->slug }}'"
                                class="rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                                :class="filterType === '{{ $rt->slug }}' ? 'border-[#0B6B3A] bg-[#0B6B3A] text-white shadow-sm' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                            {{ $rt->name }}
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Khu vực — Province thật đang có chi nhánh --}}
                <div>
                    <p class="mb-2.5 text-sm font-bold text-gray-900">Khu vực</p>
                    <div class="relative">
                        <select :value="filterProvince" @change="pickProvince($event.target.value)"
                                class="w-full appearance-none rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 pr-9 text-sm text-gray-800 transition focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]">
                            <option value="">Tất cả khu vực</option>
                            @foreach ($filterProvinces as $p)
                            <option value="{{ $p->slug }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>

                {{-- Phường/Xã — CHỈ hiện khi đã chọn 1 khu vực cụ thể (danh sách phường phụ thuộc
                     tỉnh, xem wardsInProvince getter ở x-data) --}}
                <div x-show="filterProvinceCode" x-cloak
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                    <p class="mb-2.5 text-sm font-bold text-gray-900">Phường / Xã</p>
                    <div class="relative">
                        <select x-model="filterWard" :disabled="!wardsInProvince.length"
                                class="w-full appearance-none rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 pr-9 text-sm text-gray-800 transition focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A] disabled:bg-gray-50 disabled:text-gray-400">
                            <option value="">Tất cả phường/xã</option>
                            <template x-for="w in wardsInProvince" :key="w.code">
                                <option :value="w.code" x-text="w.name"></option>
                            </template>
                        </select>
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                    <p x-show="filterProvinceCode && !wardsInProvince.length" x-cloak class="mt-2 text-xs text-gray-400">
                        Chưa có dữ liệu phường/xã chi tiết cho khu vực này.
                    </p>
                </div>
            </div>

            <div class="border-t border-gray-100 bg-white p-4" style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
                <button type="submit" @click="filterOpen = false"
                        class="w-full rounded-xl py-3.5 text-sm font-bold text-white shadow-md transition hover:opacity-90 active:scale-[.99]"
                        style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);"
                        {{-- Nút này nằm NGOÀI <form> (popup là sibling, không phải con của form) —
                             gắn form="..." để vẫn submit đúng form thật, không cần form JS riêng. --}}
                        form="search-bar-form">
                    Tìm kiếm
                </button>
            </div>
        </div>
    </div>
</div>
