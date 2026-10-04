<div class="mx-auto max-w-7xl px-4 py-4 sm:px-6"
     {{-- Chọn tỉnh ở nơi khác (popup khu vực, header, hero...) bắn 'province-selected' -> lưới này lọc theo.
          Chip bên dưới cũng đi qua đúng sự kiện này (pickProvince) nên Lịch đặt sân trực tuyến, gợi ý chi
          nhánh... tự theo. Lần đầu vào trang áp tỉnh đã lưu (localStorage) nếu tỉnh đó còn có sân. --}}
     x-data="{
        pickProvince(p) {
            if (!p) { $wire.setProvince(null); return; }
            try {
                localStorage.setItem('home_province_id', p.id);
                localStorage.setItem('home_province_name', p.name);
            } catch (e) {}
            window.dispatchEvent(new CustomEvent('province-selected', { detail: p }));
        },
     }"
     x-init="(() => { try { const saved = parseInt(localStorage.getItem('home_province_id') || '', 10); if (saved) $wire.setProvince(saved); } catch (e) {} })()"
     x-on:province-selected.window="if ($event.detail && $event.detail.id) $wire.setProvince(parseInt($event.detail.id, 10))">
    @if (count($provinces) > 1)
        {{-- Chip lọc theo tỉnh/thành — CHỈ tỉnh có sân thật (VenueGrid::mount()). Chỉ hiện khi có từ 2 tỉnh
             trở lên (1 tỉnh thì lọc vô nghĩa). --}}
        <div class="mb-2 -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
            <button type="button" @click="pickProvince(null)" wire:loading.attr="disabled"
                @class([
                    'shrink-0 whitespace-nowrap rounded-full border px-4 py-1.5 text-sm font-semibold transition',
                    'border-[#0B6B3A] bg-[#0B6B3A] text-white shadow' => $activeProvinceId === null,
                    'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => $activeProvinceId !== null,
                ])>
                Tất cả khu vực
            </button>
            @foreach ($provinces as $province)
                <button type="button" wire:key="vg-province-{{ $province['id'] }}"
                    @click="pickProvince(@js($province))" wire:loading.attr="disabled"
                    @class([
                        'shrink-0 whitespace-nowrap rounded-full border px-4 py-1.5 text-sm font-semibold transition',
                        'border-[#0B6B3A] bg-[#0B6B3A] text-white shadow' => $activeProvinceId === $province['id'],
                        'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' => $activeProvinceId !== $province['id'],
                    ])>
                    {{ $province['name'] }}
                </button>
            @endforeach
        </div>
    @endif

    @if (! empty($roomTypes))
        {{-- Chip lọc theo môn thể thao — "Tất cả" (activeType null) luôn mặc định khi vào trang.
             Bấm 1 chip lọc lại lưới sân bên dưới NGAY (Livewire, không rời trang), chỉ còn chi
             nhánh có ít nhất 1 sân đúng môn đó (xem VenueGrid::loadVenues()). --}}
        <div class="mb-3 -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
            <button
                type="button"
                wire:click="selectType(null)"
                wire:loading.attr="disabled"
                @class([
                    'shrink-0 whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold transition',
                    'bg-[#0B6B3A] text-white shadow' => $activeType === null,
                    'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== null,
                ])
            >
                Tất cả
            </button>
            @foreach ($roomTypes as $roomType)
                <button
                    type="button"
                    wire:click="selectType('{{ $roomType['slug'] }}')"
                    wire:loading.attr="disabled"
                    @class([
                        'shrink-0 whitespace-nowrap rounded-full px-4 py-1.5 text-sm font-semibold transition',
                        'bg-[#0B6B3A] text-white shadow' => $activeType === $roomType['slug'],
                        'bg-gray-100 text-gray-700 hover:bg-gray-200' => $activeType !== $roomType['slug'],
                    ])
                >
                    {{ $roomType['name'] }}
                </button>
            @endforeach
        </div>
    @endif

    @if (! empty($venues))
        <script>
            // Geolocation dùng chung cho toàn bộ thẻ sân (venues/card.blade.php) — chỉ hỏi quyền
            // định vị 1 lần, phát sự kiện 'user-geo-ready' để mỗi thẻ tự tính lại khoảng cách.
            if (!window.__haversineKm) {
                window.__haversineKm = function (lat1, lon1, lat2, lon2) {
                    const R = 6371;
                    const dLat = (lat2 - lat1) * Math.PI / 180;
                    const dLon = (lon2 - lon1) * Math.PI / 180;
                    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLon / 2) ** 2;
                    return (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))).toFixed(1);
                };
            }
            if (!window.__userGeo && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        window.__userGeo = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        window.dispatchEvent(new CustomEvent('user-geo-ready'));
                    },
                    () => {},
                    { timeout: 8000 }
                );
            }

            // Khoảng cách ĐƯỜNG THẬT (không phải chim bay) tính hàng loạt qua OSRM Table API (miễn
            // phí, không cần key, gọi thẳng từ trình duyệt) — 1 lượt gọi lấy khoảng cách thật tới
            // NHIỀU điểm cùng lúc. CHỦ Ý KHÔNG dùng ORS Matrix API ở đây (dù đã có proxy backend sẵn,
            // xem DirectionsController::matrix()) — kiểm tra thực tế phát hiện Matrix API của ORS
            // dùng 1 GÓI QUOTA RIÊNG, chỉ 500 lượt/ngày (không phải 2000/ngày như Directions), và
            // tốn quota THEO SỐ ĐIỂM ĐÍCH chứ không phải 1 đơn vị/lượt gọi (test 10 điểm đích tốn tới
            // ~10-13 đơn vị trong 1 lần gọi) — nếu bật cho tính năng này, chỉ vài lượt tải trang chủ
            // là cạn sạch quota, ảnh hưởng lây sang cả tính năng Chỉ đường chính (dùng chung API key).
            // Không tính lại theo mỗi lần render nên không lo tốn băng thông dù không tốn quota.
            if (!window.__fetchRouteDistancesKm) {
                window.__fetchRouteDistancesKm = async function (originLat, originLng, points) {
                    if (!points.length) return [];
                    let distancesMeters = null;
                    try {
                        const coordStr = originLng + ',' + originLat + ';' + points.map((p) => p.lng + ',' + p.lat).join(';');
                        const destIdx = points.map((_, i) => i + 1).join(';');
                        const url = 'https://router.project-osrm.org/table/v1/driving/' + coordStr
                            + '?sources=0&destinations=' + destIdx + '&annotations=distance';
                        const res = await fetch(url);
                        const data = await res.json();
                        if (data.distances && data.distances[0]) distancesMeters = data.distances[0];
                    } catch (e) {}
                    if (!distancesMeters) return points.map(() => null);

                    return distancesMeters.map((m) => (m == null ? null : m / 1000));
                };
            }

            // window.__routeDistancesKm: { [product_id]: km } — cache toàn cục, card.blade.php đọc
            // trực tiếp khi Livewire morph lại thẻ (VD đổi chip môn thể thao) mà KHÔNG cần chờ sự
            // kiện mới, vì thẻ mới init() SAU khi cache đã có sẵn.
            window.__routeDistancesKm = window.__routeDistancesKm || {};

            function fetchAndApplyRouteDistances() {
                if (!window.__userGeo) return;
                var grid = document.getElementById('venue-grid-track');
                if (!grid) return;
                var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-vid][data-lat]'));
                if (!cards.length) return;
                var vids = cards.map(function (c) { return c.dataset.vid; });
                var points = cards.map(function (c) { return { lat: parseFloat(c.dataset.lat), lng: parseFloat(c.dataset.lng) }; });

                window.__fetchRouteDistancesKm(window.__userGeo.lat, window.__userGeo.lng, points)
                    .then(function (kms) {
                        kms.forEach(function (km, i) {
                            if (km != null) window.__routeDistancesKm[vids[i]] = km;
                        });
                        window.dispatchEvent(new CustomEvent('route-distances-ready', { detail: { distances: window.__routeDistancesKm } }));
                        reorderVenueCards();
                    });
            }

            // Sắp xếp lại thẻ THEO ĐÚNG KHOẢNG CÁCH THẬT khi định vị xong — trước đây chỉ có badge
            // khoảng cách hiển thị trên từng thẻ (card.blade.php), còn thứ tự thẻ vẫn cố định theo
            // sort_order từ server, KHÔNG đổi theo vị trí thật (bug đã gặp: người dùng phản ánh
            // "hiển thị ngẫu nhiên" thay vì gần-xa theo vị trí đang mở trình duyệt).
            //
            // Tách thành hàm dùng lại được vì Livewire morph lại #venue-grid-track mỗi khi bấm chip
            // lọc môn thể thao (selectType()) — thẻ mới luôn ra theo thứ tự sort_order của server.
            // LƯU Ý QUAN TRỌNG: thẻ <script> này CHỈ chạy đúng 1 lần lúc trình duyệt parse HTML ban
            // đầu — Livewire morph KHÔNG làm nó chạy lại (bug đã gặp lần 1: chỉ "Tất cả" ở lần tải
            // trang đầu tiên được sắp xếp gần-xa, các môn khác bấm vào thì không, vì không có gì gọi
            // lại reorderVenueCards() sau mỗi lần morph). Vì vậy KHÔNG dựa vào việc script tự chạy
            // lại — VenueGrid::selectType() dispatch sự kiện Livewire 'venues-updated' sau mỗi lần
            // lọc, và listener bên dưới (đăng ký đúng 1 lần, tồn tại xuyên suốt các lần morph vì nó
            // gắn trên window/Livewire chứ không nằm trong phần DOM bị thay thế) gọi lại hàm này.
            // Ưu tiên khoảng cách ĐƯỜNG THẬT (window.__routeDistancesKm, đã tính xong qua
            // fetchAndApplyRouteDistances() ở trên) nếu có — chỉ dùng chim bay (haversine) làm dự
            // phòng khi chưa tính xong/không tính được (thẻ không có product_id, hoặc cả ORS lẫn
            // OSRM đều lỗi).
            function cardDistanceKm(card) {
                if (!card.dataset.lat) return Infinity;
                var vid = card.dataset.vid;
                if (vid && window.__routeDistancesKm[vid] != null) return window.__routeDistancesKm[vid];

                return parseFloat(window.__haversineKm(window.__userGeo.lat, window.__userGeo.lng, parseFloat(card.dataset.lat), parseFloat(card.dataset.lng)));
            }

            function reorderVenueCards() {
                var grid = document.getElementById('venue-grid-track');
                if (!grid || !window.__userGeo) return;
                var cards = Array.prototype.slice.call(grid.children);
                cards.sort(function (a, b) { return cardDistanceKm(a) - cardDistanceKm(b); });
                cards.forEach(function (card) { grid.appendChild(card); });
            }

            if (!window.__venueGeoListenerBound) {
                window.__venueGeoListenerBound = true;
                window.addEventListener('user-geo-ready', function () {
                    reorderVenueCards();
                    fetchAndApplyRouteDistances();
                });

                // Sự kiện Livewire riêng của VenueGrid — bắn sau MỌI lần selectType() (đổi chip môn
                // thể thao), kể cả lúc chưa có geo (hàm tự return sớm nếu window.__userGeo rỗng).
                //
                // KHÔNG được gọi `Livewire.on(...)` trực tiếp ở đây: thẻ <script> này nằm giữa HTML
                // được render lúc tải trang đầu tiên nên chạy NGAY khi trình duyệt parse tới đây —
                // TRƯỚC KHI bundle JS của Livewire (thường có `defer`, chỉ chạy sau khi parse xong
                // toàn trang) kịp định nghĩa `window.Livewire` — gọi thẳng sẽ ném ReferenceError âm
                // thầm và bỏ qua luôn dòng đăng ký, khiến chip lọc không bao giờ sắp xếp lại được dù
                // "Tất cả" (qua đường sự kiện 'user-geo-ready' ở trên, không phụ thuộc Livewire) vẫn
                // đúng (bug đã gặp: "chỉ có tất cả mới xếp gần-xa"). Dùng sự kiện 'livewire:init' —
                // Livewire luôn tự bắn đúng 1 lần khi JS của nó khởi tạo xong, bất kể thứ tự script.
                var bindLivewireListener = function () {
                    Livewire.on('venues-updated', function () {
                        reorderVenueCards();
                        fetchAndApplyRouteDistances();
                    });
                };
                if (window.Livewire) {
                    bindLivewireListener();
                } else {
                    document.addEventListener('livewire:init', bindLivewireListener);
                }
            }

            reorderVenueCards();
            fetchAndApplyRouteDistances();

            // Nạp trạng thái yêu thích THẬT (GET /api/wishlist) để tô đúng trái tim đã yêu thích
            // ngay từ đầu — trước đây nút yêu thích không lưu/đọc gì thật nên luôn hiện rỗng dù đã
            // bấm trước đó (bug đã gặp).
            (function () {
                var token = localStorage.getItem('auth_token');
                if (!token) return;
                fetch('/api/wishlist', { headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        var ids = (json.data || []).map(function (r) { return String(r.id); });
                        document.querySelectorAll('.venue-wishlist-btn').forEach(function (btn) {
                            if (ids.indexOf(btn.dataset.productId) !== -1) {
                                btn.setAttribute('data-active', '1');
                                btn.innerHTML = window.__heartSolidSvg;
                            }
                        });
                    })
                    .catch(function () {});
            })();
        </script>

        <div class="mb-3 flex items-center justify-between">
            @php($activeProvinceName = collect($provinces)->firstWhere('id', $activeProvinceId)['name'] ?? null)
            <h2 class="text-lg font-extrabold text-gray-900">
                {{ $activeProvinceName ? 'Sân thể thao tại ' . $activeProvinceName : 'Sân thể thao gần bạn' }}
            </h2>
            {{-- Toàn bộ lọc/duyệt sân đã gộp về trang bản đồ /ban-do — không còn
                 route('product.search') = /s nữa ở bất kỳ đâu (xem comment đầu
                 components/home/search-bar.blade.php). --}}
            <a href="{{ route('map.page') }}" class="text-sm font-semibold text-[#0B6B3A] underline underline-offset-2">
                Xem tất cả
            </a>
        </div>

        <div id="venue-grid-track" class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($venues as $venue)
                <x-bladethemev1::venues.card :venue="$venue" />
            @endforeach
        </div>
    @elseif ($activeType || $activeProvinceId)
        {{-- Đang lọc theo 1 môn/tỉnh nhưng không có chi nhánh nào khớp — khác trường hợp $roomTypes rỗng
             (không có RoomType nào trong hệ thống), ở đây chip vẫn hiển thị để bấm "Tất cả" quay lại. --}}
        <p class="py-8 text-center text-sm text-gray-500">Chưa có sân nào phù hợp với bộ lọc này.</p>
    @endif
</div>
