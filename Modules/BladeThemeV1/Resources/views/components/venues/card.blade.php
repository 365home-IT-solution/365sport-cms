{{--
    Thẻ sân/chi nhánh dạng lưới ở trang chủ, theo mẫu tham khảo: ảnh + badge khoảng cách, tên, địa
    chỉ, giờ mở cửa, badge đánh giá (nếu có), nút cam "ĐẶT LỊCH", tim yêu thích, chia sẻ.
    Props: $venue (mảng — xem VenueGrid::loadVenues()).
--}}
@props(['venue'])

<div
    {{-- data-lat/data-lng: venue-grid.blade.php đọc 2 thuộc tính này để SẮP XẾP LẠI thứ tự thẻ theo
         khoảng cách thật sau khi định vị xong (trước đây chỉ có badge khoảng cách hiển thị, thứ tự
         thẻ vẫn cố định theo sort_order — bug đã gặp, người dùng phản ánh "hiển thị ngẫu nhiên"
         thay vì theo vị trí thật). --}}
    @if ($venue['lat'] && $venue['lng'])
        {{-- data-vid: định danh cho window.__routeDistancesKm (venue-grid.blade.php) — dùng TOẠ ĐỘ
             thay vì product_id, vì product_id có thể rỗng (chi nhánh chưa gắn sân đại diện đủ điều
             kiện — cùng lý do nút Yêu thích bên dưới đôi khi không hiện) trong khi toạ độ luôn có
             sẵn ở đây (cùng điều kiện @if bao ngoài). Trước đây khoá theo product_id khiến TOÀN BỘ
             card thiếu product_id bị bỏ qua khỏi lượt tính khoảng cách thật, badge giữ nguyên chim
             bay dù tính năng đã bật (bug đã gặp: người dùng phản ánh "hoàn toàn không thay đổi"). --}}
        data-lat="{{ $venue['lat'] }}" data-lng="{{ $venue['lng'] }}"
        data-vid="{{ $venue['lat'] }},{{ $venue['lng'] }}"
        x-data="{
            dist: null,
            init() {
                const calc = () => {
                    if (!window.__userGeo) return;
                    this.dist = window.__haversineKm(window.__userGeo.lat, window.__userGeo.lng, {{ $venue['lat'] }}, {{ $venue['lng'] }});
                };
                calc();
                window.addEventListener('user-geo-ready', calc);
                // Khoảng cách đường thật (window.__routeDistancesKm, tính hàng loạt ở
                // venue-grid.blade.php) — ưu tiên hơn chim bay khi đã có. Đọc cache CÓ SẴN ngay
                // (Livewire morph lại thẻ này sau khi lọc môn thể thao thì cache có thể đã tính xong
                // từ trước, không cần chờ sự kiện mới), đồng thời vẫn lắng nghe sự kiện cho lần tính
                // đầu tiên/lần tính lại sau khi đổi bộ lọc.
                const routeKey = '{{ $venue['lat'] }},{{ $venue['lng'] }}';
                const applyRouteDist = () => {
                    const km = window.__routeDistancesKm && window.__routeDistancesKm[routeKey];
                    if (km != null) this.dist = km.toFixed(1);
                };
                applyRouteDist();
                window.addEventListener('route-distances-ready', applyRouteDist);
            }
        }"
    @endif
    class="venue-card group flex flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm transition hover:shadow-md"
>
    <a href="{{ $venue['url'] }}" class="relative block aspect-[16/10] w-full overflow-hidden bg-gray-100">
        @if ($venue['image_url'])
            <img src="{{ $venue['image_url'] }}" alt="{{ $venue['name'] }}" loading="lazy"
                 class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="flex h-full w-full items-center justify-center text-gray-300">
                <x-heroicon-o-photo class="h-10 w-10" />
            </div>
        @endif

        @if ($venue['lat'] && $venue['lng'])
            <span x-show="dist !== null" x-cloak
                  class="absolute left-2 top-2 rounded-md bg-black/60 px-1.5 py-0.5 text-[11px] font-semibold text-white">
                (<span x-text="dist"></span>km)
            </span>
        @endif

        @if ($venue['rating'])
            <span class="absolute right-2 top-2 flex items-center gap-0.5 rounded-md bg-white/95 px-1.5 py-0.5 text-[11px] font-bold text-gray-800 shadow-sm">
                <svg class="h-3 w-3 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.363 1.118l1.287 3.957c.3.922-.755 1.688-1.538 1.118l-3.37-2.448a1 1 0 00-1.175 0l-3.37 2.448c-.783.57-1.838-.196-1.538-1.118l1.287-3.957a1 1 0 00-.363-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 00.95-.69l1.286-3.958z"/></svg>
                {{ $venue['rating'] }}
            </span>
        @endif

        {{-- Chi nhánh có bán gói nhiều buổi (PackagePlan::categoryIdsWithActivePlans()) — icon hộp
             quà nhỏ báo cho khách biết có thể mua gói ở đây. --}}
        @if (! empty($venue['has_package']))
            <span class="absolute bottom-2 left-2 flex h-7 w-7 items-center justify-center rounded-full bg-[#F5A623] text-white shadow-sm"
                  title="Có bán gói nhiều giờ" aria-label="Có bán gói nhiều giờ">
                <x-heroicon-o-gift class="h-4 w-4" />
            </span>
        @endif

        <div class="absolute bottom-2 right-2 flex items-center gap-1.5">
            {{-- Nút yêu thích THẬT — trước đây chỉ toggle 1 class CSS không có style nào áp dụng
                 (bug đã gặp: bấm không thấy đổi gì) và KHÔNG lưu lại (mất trạng thái khi tải lại
                 trang). Giờ dùng lại đúng cơ chế wishlist thật đang chạy ở nơi khác trong site
                 (window.__homeToggleWishlist, public/js/home-sections.js — gọi API
                 /api/wishlist/{id}/toggle) — gắn theo SÂN ĐẠI DIỆN của chi nhánh vì hệ thống chỉ
                 có khái niệm yêu thích ở cấp sân, không có ở cấp chi nhánh. Trạng thái ban đầu
                 (đã yêu thích hay chưa) được nạp qua script chung ở venue-grid.blade.php sau khi
                 gọi GET /api/wishlist. --}}
            @if ($venue['product_id'])
                {{-- window.__homeToggleWishlist() tự kiểm tra đăng nhập (phát 'open-auth-modal'
                     nếu chưa) nên không cần lặp lại điều kiện đó ở đây. --}}
                <button type="button" class="venue-wishlist-btn flex h-7 w-7 items-center justify-center rounded-full bg-white/95 text-gray-500 shadow-sm transition hover:text-rose-500"
                        data-product-id="{{ $venue['product_id'] }}" data-active="0"
                        aria-label="Yêu thích"
                        onclick="event.preventDefault(); event.stopPropagation(); window.__homeToggleWishlist('{{ $venue['product_id'] }}', this);">
                    <x-heroicon-o-heart class="h-4 w-4" />
                </button>
            @endif
            <button type="button"
                    onclick="event.preventDefault(); if(navigator.share){navigator.share({title:'{{ $venue['name'] }}', url:'{{ $venue['url'] }}'});}else{navigator.clipboard.writeText('{{ $venue['url'] }}');}"
                    class="flex h-7 w-7 items-center justify-center rounded-full bg-white/95 text-gray-500 shadow-sm transition hover:text-gray-800"
                    aria-label="Chia sẻ">
                <x-heroicon-o-share class="h-4 w-4" />
            </button>
        </div>
    </a>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <a href="{{ $venue['url'] }}" class="line-clamp-1 text-sm font-bold text-gray-900 hover:text-[#0B6B3A]">
            {{ $venue['name'] }}
        </a>

        @if ($venue['address'])
            <p class="line-clamp-1 text-xs text-gray-500">{{ $venue['address'] }}</p>
        @endif

        <div class="mt-1 flex items-center justify-between gap-2">
            @if ($venue['hours'])
                <span class="flex items-center gap-1 text-xs font-medium text-gray-500">
                    <x-heroicon-o-clock class="h-3.5 w-3.5" />
                    {{ $venue['hours'] }}
                </span>
            @else
                <span></span>
            @endif

            <a href="{{ $venue['url'] }}"
               class="shrink-0 rounded-md bg-[#F5A623] px-3 py-1.5 text-xs font-extrabold uppercase text-white shadow-sm transition hover:brightness-95">
                Đặt lịch
            </a>
        </div>
    </div>
</div>
