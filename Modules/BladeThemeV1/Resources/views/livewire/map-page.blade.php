<div
    x-data="{
        venues: @js($venues),
        search: '',
        sidebarOpen: true,
        selected: null,
        selectedIndex: null,
        activeTab: 'info',
        isLoggedIn: false,
        availability: null,
        availabilityLoading: false,
        servicesData: null,
        servicesLoading: false,
        loadAvailability(slug) {
            this.availability = null;
            this.availabilityLoading = true;
            this.$wire.getTodayAvailability(slug).then((res) => {
                this.availability = res;
                this.availabilityLoading = false;
            }).catch(() => { this.availabilityLoading = false; });
        },
        loadServices(slug) {
            this.servicesData = null;
            this.servicesLoading = true;
            this.$wire.getServicesAndPricing(slug).then((res) => {
                this.servicesData = res;
                this.servicesLoading = false;
            }).catch(() => { this.servicesLoading = false; });
        },
        myLat: null,
        myLng: null,
        // Lọc danh sách bên trái theo bán kính (1-20km) — 20 (đầu mút phải thanh trượt) coi là
        // không giới hạn, giữ đúng hành vi mặc định trước đây (hiện tất cả sân, sắp xếp gần-xa)
        // cho tới khi người dùng chủ động kéo thanh trượt xuống dưới 20. Chỉ lọc DANH SÁCH, không
        // ẩn/hiện marker trên bản đồ — cùng cách search (ô tìm kiếm) đang lọc hiện có.
        maxDistanceKm: 20,
        // Khoảng cách thật (km) từ vị trí người dùng (myLat/myLng, set ở showMyLocationMarker()
        // phía dưới khi định vị xong) tới 1 sân — tự viết riêng ở đây thay vì phụ thuộc
        // window.__haversineKm (venue-grid.blade.php, trang chủ), vì trang bản đồ có thể tải độc
        // lập không kèm script đó.
        distanceKm(lat, lng) {
            if (lat == null || lng == null || this.myLat === null || this.myLng === null) return null;
            const R = 6371;
            const dLat = (lat - this.myLat) * Math.PI / 180;
            const dLng = (lng - this.myLng) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2
                + Math.cos(this.myLat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        },
        get filtered() {
            let list = this.venues;
            if (this.search) {
                const s = this.search.toLowerCase();
                list = list.filter(v => v.name.toLowerCase().includes(s) || (v.address || '').toLowerCase().includes(s));
            }
            // Sắp xếp lại theo GẦN - XA thật khi đã có vị trí người dùng — trước đây danh sách bên
            // trái luôn giữ nguyên thứ tự trả về từ server (không đổi theo vị trí), dù bản đồ đã
            // có toạ độ thật để tính.
            if (this.myLat !== null && this.myLng !== null) {
                list = list.map(v => ({ v, d: this.venueDistanceKm(v) }))
                    .filter(x => this.maxDistanceKm >= 20 || (x.d !== null && x.d <= this.maxDistanceKm))
                    .sort((x, y) => (x.d ?? Infinity) - (y.d ?? Infinity))
                    .map(x => x.v);
            }
            return list;
        },
        map: null,
        markers: {},
        baseLayers: {},
        activeBase: 'street',
        layersMenuOpen: false,
        userMarker: null,
        // Bật/tắt ở admin (App\Filament\Pages\DirectionsSettings) — xem drawRoute()/fetchRouteOrs()/
        // fetchRouteOsrm() bên dưới.
        directionsOrsEnabled: @js($directionsOrsEnabled),
        // Xem ghi chú đầy đủ tại config('services.carto') — CARTO Basemaps giờ BẮT BUỘC key, rỗng
        // thì tile chỉ hiện chữ đè API KEY REQUIRED.
        cartoBasemapsKey: @js($cartoBasemapsKey),
        init() {
            // Đánh dấu phiên bản JS đang chạy — nếu KHÔNG thấy dòng này trong Console sau khi tải
            // lại trang, nghĩa là trình duyệt đang chạy bản JS CŨ (cache trang/tab chưa tải lại
            // thật sự), không phải do code chưa được sửa ở server.
            console.log('[map-page] build 2026-08-23-routing-fix-9 (fix sticky+overflow-x-auto tab bar bug)');
            this.isLoggedIn = !!(localStorage.getItem('auth_token') && localStorage.getItem('auth_user'));
            window.addEventListener('auth-state-changed', () => {
                this.isLoggedIn = !!(localStorage.getItem('auth_token') && localStorage.getItem('auth_user'));
            });
            this.$nextTick(() => this.initMap());
        },
        initMap() {
            this.map = L.map(this.$refs.mapEl, { zoomControl: false }).setView([16.0, 106.0], 5.5);

            // Voyager (CARTO, dựa trên dữ liệu OSM nhưng style sạch hơn: nền màu kem/xanh nhạt,
            // biên giới mảnh, ưu tiên nhãn tên latin/English cho khu vực ngoài Việt Nam) — khớp
            // đúng phong cách ảnh mẫu, thay cho tile OSM mặc định trước đây (màu đậm, chữ Hán to,
            // đường biên giới tranh chấp nét đứt nổi bật). TRƯỚC ĐÂY miễn phí không cần key — CARTO
            // đã đổi chính sách, giờ bắt buộc key (xem config('services.carto'), lấy key tại
            // https://carto.com/basemaps/apikey), không thì tile chỉ hiện chữ đè API KEY REQUIRED
            // (bug thật đã gặp). Bỏ luôn tiền tố {s}. (subdomain xoay vòng) — endpoint có key dùng
            // đúng 1 host duy nhất theo tài liệu CARTO, {s}. không còn cần thiết.
            this.baseLayers.street = L.tileLayer(
                this.cartoBasemapsKey
                    ? `https://basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png?key=${this.cartoBasemapsKey}`
                    : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                }
            );
            // Vệ tinh (Esri World Imagery) — lựa chọn thứ 2 trong nút lớp bản đồ (layers) góc
            // trên phải, cũng miễn phí không cần API key.
            this.baseLayers.satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri',
            });
            this.baseLayers.street.addTo(this.map);
            L.control.zoom({ position: 'bottomright' }).addTo(this.map);

            // Nhãn 2 quần đảo Hoàng Sa/Trường Sa bằng tiếng Việt — tile nền (CARTO/OSM) chỉ có sẵn
            // nhãn tiếng Anh (Paracel/Spratly Islands), đè thêm nhãn tiếng Việt ở đúng toạ độ thật
            // của 2 quần đảo (không đụng tới đường biên giới/dữ liệu nền, chỉ thêm chữ hiển thị,
            // giống cách các bản đồ tiếng Việt vẫn dùng tên gọi Việt Nam cho khu vực này).
            const islandLabel = (lat, lng, text) => {
                L.marker([lat, lng], {
                    icon: L.divIcon({ className: '', html: `<span class='island-label'>${text}</span>`, iconSize: [0, 0] }),
                    interactive: false,
                    keyboard: false,
                }).addTo(this.map);
            };
            islandLabel(16.55, 111.9, 'Hoàng Sa');
            islandLabel(9.5, 113.7, 'Trường Sa');

            // Icon marker theo môn thể thao — MỖI icon tự khai màu/nét riêng (không dùng chung 1
            // bộ fill/stroke mặc định như trước) để vẽ được hình 2 tông màu (bóng trắng + hoạ tiết
            // màu trùng ghim) giống icon thể thao thật, dễ nhận ra hơn glyph nét mảnh trước đây.
            // sport_label giờ lấy từ RoomType.name THẬT (vd Sân cầu lông, Sân bóng đá mini) — so
            // khớp bằng TỪ KHOÁ chứa trong chuỗi (không so khớp tuyệt đối cả chuỗi) để không vỡ
            // nếu tên RoomType đổi thêm/bớt chữ (vd Sân cầu lông so với Cầu lông) — bug đã gặp: đổi
            // nguồn sport_label từ suy đoán tên chi nhánh sang RoomType.name thật làm toàn bộ icon
            // rơi về mặc định (chấm trắng) vì so khớp tuyệt đối cũ không còn khớp chuỗi mới. Toạ độ
            // hình vẽ nằm gọn trong khoảng y 2-17 (viewBox 0 0 24 24) để không lấn đuôi nhọn ghim.
            const SPORT_ICONS = [
                { keyword: 'pickleball', color: '#2563eb',
                  shapes: `<rect x='6.3' y='2.3' width='11.4' height='10' rx='5' fill='white'/><rect x='10.2' y='11.4' width='3.6' height='5.4' rx='1.6' fill='white'/><circle cx='9.3' cy='6' r='0.65' fill='currentColor'/><circle cx='12' cy='5' r='0.65' fill='currentColor'/><circle cx='14.7' cy='6' r='0.65' fill='currentColor'/><circle cx='9.3' cy='9.3' r='0.65' fill='currentColor'/><circle cx='12' cy='8.3' r='0.65' fill='currentColor'/><circle cx='14.7' cy='9.3' r='0.65' fill='currentColor'/>` },
                { keyword: 'cầu lông', color: '#dc2626',
                  shapes: `<path d='M12 2.2L8.4 8h7.2z' fill='white'/><path d='M8.4 8L6 16.5h12L15.6 8z' fill='white' fill-opacity='.55'/><path d='M8.4 8L6 16.5M15.6 8l2.4 8.5M12 8v8.5' stroke='currentColor' stroke-width='.7'/>` },
                { keyword: 'bóng đá', color: '#16a34a',
                  shapes: `<circle cx='12' cy='9.5' r='7.3' fill='white'/><path d='M12 5l2.4 1.75-.9 2.8h-3l-.9-2.8z' fill='currentColor'/><path d='M9.5 9.7L5.6 12.4M14.5 9.7l3.9 2.7M12 12.5v4.6' stroke='currentColor' stroke-width='1.1' stroke-linecap='round'/>` },
                { keyword: 'tennis', color: '#f59e0b',
                  shapes: `<ellipse cx='12' cy='7.7' rx='4.8' ry='5.6' fill='none' stroke='white' stroke-width='1.9'/><path d='M12 2.5v10.4M7 7.7h10M8.2 4l7.6 7.4M15.8 4L8.2 11.4' stroke='white' stroke-width='.9'/><rect x='10.6' y='13.2' width='2.8' height='5.4' rx='1.4' fill='white'/>` },
                { keyword: 'bóng rổ', color: '#0d9488',
                  shapes: `<circle cx='12' cy='9.5' r='7.3' fill='white'/><path d='M4.7 9.5h14.6M12 2.2v14.6M6.8 4.4c2.9 2.9 2.9 7.8 0 10.7M17.2 4.4c-2.9 2.9-2.9 7.8 0 10.7' stroke='currentColor' stroke-width='1.1' stroke-linecap='round'/>` },
            ];
            const sportStyle = (label) => {
                const lower = (label || '').toLowerCase();
                const found = SPORT_ICONS.find((s) => lower.indexOf(s.keyword) !== -1);
                return found || { color: '#2563eb', shapes: `<circle cx='12' cy='9' r='3.6' fill='white'/>` };
            };

            const bounds = [];
            this.venues.forEach((v, i) => {
                const style = sportStyle(v.sport_label);
                const icon = L.divIcon({
                    className: '',
                    html: `<div class='map-pin-icon' style='color:${style.color}'><svg viewBox='0 0 24 24' fill='currentColor' stroke='none'><path d='M12 2C7.58 2 4 5.58 4 10c0 5.25 8 12 8 12s8-6.75 8-12c0-4.42-3.58-8-8-8z'/></svg><svg class='map-pin-glyph' viewBox='0 0 24 24' fill='none'>${style.shapes}</svg></div>`,
                    iconSize: [42, 42],
                    iconAnchor: [21, 39],
                });
                const marker = L.marker([v.lat, v.lng], { icon }).addTo(this.map)
                    .bindTooltip(v.name, { permanent: false, direction: 'top' })
                    .on('click', () => this.selectVenue(v, i));
                this.markers[i] = marker;
                bounds.push([v.lat, v.lng]);
            });
            // Khung nhìn toàn Việt Nam (bao gồm cả 2 quần đảo Hoàng Sa/Trường Sa, toạ độ thật xa
            // ngoài khơi so với các sân đều nằm sát bờ) — CHỈ dùng làm phương án DỰ PHÒNG khi không
            // định vị được vị trí người dùng (trình duyệt không hỗ trợ/khách từ chối cấp quyền/hết
            // giờ chờ). Mặc định mở bản đồ giờ tự động zoom gần vị trí hiện tại NGAY (như thể khách
            // đã tự bấm nút Vị trí gần đây), không zoom xa hiện cả nước như trước nữa — xem nhánh
            // geolocation ngay bên dưới.
            const fitAllVenues = () => {
                bounds.push([16.55, 111.9], [9.5, 113.7]);
                if (bounds.length) {
                    this.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 6.5 });
                }
            };

            {{-- Tới từ nút "Tìm sân gần đây" ở 1 trang KHÁC (chưa có window.__panToMyLocation lúc
                 đó nên phải điều hướng kèm query lat/lng, xem findNearby() trong
                 components/home/search-bar.blade.php) — lia thẳng tới đây, KHÔNG cần tự định vị lại
                 vì toạ độ đã có sẵn từ trang trước. --}}
            @if ($initialLat && $initialLng)
                this.showMyLocationMarker({{ $initialLat }}, {{ $initialLng }});
                this.map.setView([{{ $initialLat }}, {{ $initialLng }}], 15);
            @else
                // enableHighAccuracy + maximumAge: trước đây gọi mặc định (không set gì) khiến
                // trình duyệt LUÔN dò lại vị trí từ đầu bằng nguồn kém chính xác nhất có sẵn
                // (thường là định vị theo Wi-Fi/IP trên máy tính không có GPS thật) — sai số có thể
                // lệch vài km và khác nhau MỖI LẦN tải lại trang, nhìn như vị trí bị nhảy lung tung
                // dù chỉ gọi định vị đúng 1 lần/lượt tải trang (bug đã gặp). enableHighAccuracy ưu
                // tiên nguồn chính xác nhất máy có; maximumAge cho phép dùng lại kết quả định vị
                // gần đây (trong 5 phút) thay vì luôn dò mới, giảm sai lệch giữa các lần tải trang
                // gần nhau.
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition((pos) => {
                        // Định vị THÀNH CÔNG — zoom gần vị trí ngay, giống hệt bấm nút Vị trí gần
                        // đây (window.__panToMyLocation bên dưới), không hiện khung nhìn cả nước.
                        this.showMyLocationMarker(pos.coords.latitude, pos.coords.longitude);
                        this.map.setView([pos.coords.latitude, pos.coords.longitude], 15, { animate: true });
                    }, () => {
                        // Từ chối cấp quyền/lỗi định vị — quay lại khung nhìn toàn quốc để khách
                        // vẫn thấy hết các sân, không kẹt ở khung nhìn mặc định hẹp vô nghĩa.
                        fitAllVenues();
                    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 });
                } else {
                    // Trình duyệt không hỗ trợ định vị — cùng phương án dự phòng.
                    fitAllVenues();
                }
            @endif

            // Cho phép gọi từ ngoài Alpine scope này (nút Tìm sân gần đây ở popup lọc dùng chung,
            // components/home/search-bar.blade.php) — bấm nút đó khi đang Ở SẴN trang bản đồ chỉ
            // cần lia bản đồ tới vị trí thật, không cần rời trang/tải lại như các trang danh sách
            // khác.
            window.__panToMyLocation = (lat, lng) => {
                if (!this.map) return;
                this.showMyLocationMarker(lat, lng);
                this.map.setView([lat, lng], 15, { animate: true });
            };
        },
        myLocationMarker: null,
        showMyLocationMarker(lat, lng) {
            // Ghi lại toạ độ thật để danh sách bên trái tự sắp xếp lại theo gần-xa —
            // trước đây hàm này CHỈ vẽ marker trên bản đồ, không hề lưu lại toạ độ
            // nên danh sách bên trái không có gì để tính khoảng cách (bug đã gặp).
            this.myLat = lat;
            this.myLng = lng;
            // Tính lại khoảng cách ĐƯỜNG THẬT mỗi khi vị trí thay đổi thật sự (hàm này là điểm chốt
            // duy nhất mọi luồng cập nhật vị trí đều đi qua — định vị lần đầu, bấm Tìm sân gần đây,
            // tới từ trang khác kèm toạ độ) — không tính lại theo mỗi lần render nên không tốn quota
            // dư thừa.
            this.fetchAllRouteDistances();
            if (this.myLocationMarker) {
                this.myLocationMarker.setLatLng([lat, lng]);
                return;
            }
            this.myLocationMarker = L.circleMarker([lat, lng], {
                radius: 7, color: '#fff', weight: 2, fillColor: '#1976D2', fillOpacity: 1,
            }).addTo(this.map).bindPopup('Vị trí của bạn');
        },
        // { [venue.slug]: km } — khoảng cách ĐƯỜNG THẬT (không phải chim bay) tính hàng loạt qua
        // fetchAllRouteDistances() bên dưới. Rỗng nghĩa là chưa tính xong/không tính được cho sân
        // đó — venueDistanceKm() tự rơi về chim bay (distanceKm()) trong trường hợp này.
        routeDistancesKm: {},
        // Khoảng cách hiển thị/dùng để sắp xếp CHO 1 SÂN — ưu tiên đường thật nếu đã có, chim bay
        // làm dự phòng (đang tính/không tính được). Trả về number|null giống hệt distanceKm(), CHƯA
        // toFixed() — định dạng ở nơi hiển thị.
        venueDistanceKm(v) {
            const routeKm = this.routeDistancesKm[v.slug];
            return routeKm != null ? routeKm : this.distanceKm(v.lat, v.lng);
        },
        // Tính khoảng cách đường thật hàng loạt qua OSRM Table API (miễn phí, không cần key, gọi
        // thẳng từ trình duyệt) — CHỦ Ý KHÔNG dùng ORS Matrix API dù đã có proxy backend sẵn (xem
        // DirectionsController::matrix()): kiểm tra thực tế phát hiện Matrix API của ORS dùng 1 GÓI
        // QUOTA RIÊNG, chỉ 500 lượt/ngày (không phải 2000/ngày như Directions), và tốn quota THEO SỐ
        // ĐIỂM ĐÍCH chứ không phải 1 đơn vị/lượt gọi — bật cho tính năng này chỉ vài lượt tải trang
        // là cạn sạch, ảnh hưởng lây sang tính năng Chỉ đường chính (dùng chung API key). Viết lại
        // riêng ở đây (không dùng chung window.__fetchRouteDistancesKm ở venue-grid.blade.php) vì
        // trang bản đồ có thể tải độc lập không kèm script đó, giống lý do distanceKm() không dùng
        // chung window.__haversineKm. Chỉ tính cho TỐI ĐA 50 sân GẦN NHẤT theo chim bay trước (giới
        // hạn hợp lý cho 1 lượt gọi OSRM Table) — sân càng xa thì độ chênh giữa chim bay/đường thật
        // càng ít ảnh hưởng tới cảm nhận gần nhất, nên chấp nhận giữ chim bay cho nhóm xa.
        async fetchAllRouteDistances() {
            if (this.myLat === null || this.myLng === null || !this.venues.length) return;

            const withDist = this.venues
                .map((v) => ({ v, d: this.distanceKm(v.lat, v.lng) }))
                .filter((x) => x.d !== null)
                .sort((a, b) => a.d - b.d)
                .slice(0, 50);
            if (!withDist.length) return;

            const points = withDist.map((x) => ({ lat: x.v.lat, lng: x.v.lng }));
            let distancesMeters = null;
            try {
                const coordStr = this.myLng + ',' + this.myLat + ';' + points.map((p) => p.lng + ',' + p.lat).join(';');
                const destIdx = points.map((_, i) => i + 1).join(';');
                const url = 'https://router.project-osrm.org/table/v1/driving/' + coordStr
                    + '?sources=0&destinations=' + destIdx + '&annotations=distance';
                const res = await fetch(url);
                const data = await res.json();
                if (data.distances && data.distances[0]) distancesMeters = data.distances[0];
            } catch (e) {}
            if (!distancesMeters) return;

            distancesMeters.forEach((m, i) => {
                if (m != null) this.routeDistancesKm[withDist[i].v.slug] = m / 1000;
            });
        },
        goTo(v) {
            if (!this.map) return;
            // Huỷ animation zoom/pan ĐANG CHẠY (nếu có) trước khi bắt đầu cái mới — bấm chuyển sân
            // liên tục trước khi animation trước kết thúc từng làm Leaflet crash (tooltip của marker
            // trước đó bị treo giữa animation, lỗi Cannot read properties of null khi frame tiếp
            // theo cố cập nhật vị trí tooltip đã mất tham chiếu map). map.stop() là API chính thức
            // của Leaflet để huỷ animation panTo/flyTo đang chạy, tránh đúng race condition này.
            this.map.stop();
            this.map.setView([v.lat, v.lng], 15);
        },
        routeLine: null,
        routeToken: 0,
        routeLoading: false,
        routeSteps: [],
        routeDistanceLabel: '',
        routeDurationLabel: '',
        routeError: '',
        clearRoute() {
            if (this.routeLine) { this.map.removeLayer(this.routeLine); this.routeLine = null; }
            this.routeSteps = [];
            this.routeDistanceLabel = '';
            this.routeDurationLabel = '';
            this.routeError = '';
        },
        // Dịch loại thao tác + hướng rẽ của 1 bước OSRM (steps[].maneuver) sang câu tiếng Việt kèm
        // tên đường thật — OSRM chỉ trả field type/modifier tiếng Anh, không có sẵn câu dịch.
        stepInstruction(step) {
            const type = (step.maneuver && step.maneuver.type) || '';
            const modifier = (step.maneuver && step.maneuver.modifier) || '';
            const name = step.name || '';
            const turnWord = {
                uturn: 'quay đầu',
                'sharp right': 'rẽ phải gắt',
                right: 'rẽ phải',
                'slight right': 'rẽ phải nhẹ',
                straight: 'đi thẳng',
                'slight left': 'rẽ trái nhẹ',
                left: 'rẽ trái',
                'sharp left': 'rẽ trái gắt',
            }[modifier] || 'tiếp tục di chuyển';
            if (type === 'depart') return 'Xuất phát' + (name ? (' trên ' + name) : '');
            if (type === 'arrive') return 'Đến nơi';
            if (type === 'roundabout' || type === 'rotary') return 'Vào vòng xuyến' + (name ? (', ra ở ' + name) : '');
            if (type === 'new name') return 'Tiếp tục trên ' + name;
            if (type === 'continue') return 'Tiếp tục ' + turnWord + (name ? (' trên ' + name) : '');
            if (type === 'turn' || type === 'end of road' || type === 'fork' || type === 'merge' || type === 'on ramp' || type === 'off ramp') {
                const capitalized = turnWord.charAt(0).toUpperCase() + turnWord.slice(1);
                return capitalized + (name ? (' vào ' + name) : '');
            }
            return name ? ('Đi theo ' + name) : 'Tiếp tục di chuyển';
        },
        // Dịch bước dẫn đường của OpenRouteService (steps[].type — mã số hướng rẽ chuẩn ORS, KHÁC
        // hoàn toàn field maneuver.type/modifier dạng chữ của OSRM ở stepInstruction() bên trên) —
        // xem https://giscience.github.io/openrouteservice-docs (mã 0-13 chuẩn ORS Directions API).
        stepInstructionOrs(step) {
            const name = step.name && step.name !== '-' ? step.name : '';
            const turnWord = {
                0: 'rẽ trái', 1: 'rẽ phải', 2: 'rẽ trái gắt', 3: 'rẽ phải gắt',
                4: 'rẽ trái nhẹ', 5: 'rẽ phải nhẹ', 6: 'đi thẳng', 9: 'quay đầu',
                12: 'đi sát làn trái', 13: 'đi sát làn phải',
            }[step.type];
            if (step.type === 11) return 'Xuất phát' + (name ? (' trên ' + name) : '');
            if (step.type === 10) return 'Đến nơi';
            if (step.type === 7) return 'Vào vòng xuyến' + (name ? (', ra ở ' + name) : '');
            if (step.type === 8) return 'Ra khỏi vòng xuyến' + (name ? (' vào ' + name) : '');
            if (turnWord) {
                const capitalized = turnWord.charAt(0).toUpperCase() + turnWord.slice(1);
                return capitalized + (name ? (' vào ' + name) : '');
            }
            return name ? ('Đi theo ' + name) : 'Tiếp tục di chuyển';
        },
        formatDistance(meters) {
            if (!meters || meters < 1) return '';
            return meters >= 1000 ? (meters / 1000).toFixed(1) + ' km' : Math.round(meters) + ' m';
        },
        formatDuration(seconds) {
            if (!seconds || seconds < 1) return '';
            const mins = Math.round(seconds / 60);
            if (mins < 60) return mins + ' phút';
            return Math.floor(mins / 60) + ' giờ ' + (mins % 60) + ' phút';
        },
        // Đường biên phía Tây của Việt Nam, ước lượng gần đúng theo từng mốc vĩ độ (kinh độ nhỏ
        // nhất còn tính là lãnh thổ VN tại vĩ độ đó) — dùng để lọc route OSRM đi lạc qua Lào/
        // Campuchia (2 nước đó nằm hẳn về phía Tây so với dải đất hẹp của Việt Nam). Không cần
        // chính xác tuyệt đối theo từng mét biên giới thật, chỉ cần đủ sát để bắt được trường hợp
        // route cắt sâu qua nước khác hàng chục/trăm km như trong lỗi thực tế đã gặp. Vĩ độ nằm
        // ngoài khoảng 8–23.4 (Bắc/Nam đều đã ra khỏi lãnh thổ) cũng coi là ngoài Việt Nam.
        VN_WEST_BORDER: [
            [23.4, 102.0], [22.0, 102.6], [21.0, 103.0], [20.0, 104.3],
            [19.0, 104.9], [18.0, 105.8], [17.0, 106.4], [16.0, 106.9],
            [15.0, 107.2], [14.0, 107.3], [13.0, 107.6], [12.3, 107.9],
            [11.5, 106.9], [10.8, 106.2], [10.0, 104.8], [8.5, 104.7],
        ],
        // checkWestBorder: giữ tham số cho tương thích lời gọi cũ nhưng LUÔN bật thật cho CẢ 2 nhà
        // cung cấp — trước đây tắt riêng cho ORS (tin rằng avoid_countries đã đủ chặn Lào/Campuchia),
        // nhưng bug thực tế đã gặp: ORS vẫn có thể vẽ ra 1 tuyến cắt qua Campuchia/vịnh Thái Lan dù
        // avoid_countries đang bật (avoid_countries chỉ chặn ĐI QUA lãnh thổ đó như 1 ràng buộc định
        // tuyến, không đảm bảo tuyệt đối không có điểm toạ độ nào lọt gần biên/ra ngoài khơi). Dung
        // sai nới từ 15km lên 35km để giảm rủi ro chặn nhầm route hợp lệ ở đoạn thắt eo miền Trung
        // (Quảng Bình/Hà Tĩnh, VN chỉ rộng ~50km — bug từng gặp lúc dung sai còn 15km) mà vẫn đủ chặt
        // để bắt lỗi cắt sâu qua nước khác hàng chục/trăm km.
        routeStaysInVietnam(coords, checkWestBorder) {
            if (checkWestBorder) {
                const border = this.VN_WEST_BORDER;
                const minLngAt = (lat) => {
                    if (lat >= border[0][0]) return border[0][1];
                    if (lat <= border[border.length - 1][0]) return border[border.length - 1][1];
                    for (let i = 0; i < border.length - 1; i++) {
                        const [lat1, lng1] = border[i];
                        const [lat2, lng2] = border[i + 1];
                        if (lat <= lat1 && lat >= lat2) {
                            const t = (lat1 - lat) / (lat1 - lat2);
                            return lng1 + (lng2 - lng1) * t;
                        }
                    }
                    return border[border.length - 1][1];
                };
                const insideBorder = coords.every((c) => {
                    const lng = c[0];
                    const lat = c[1];
                    if (lat < 8.0 || lat > 23.5) return false;
                    return lng >= minLngAt(lat) - 0.35; // dung sai ~35km cho sai số của xấp xỉ tuyến tính
                });
                if (!insideBorder) return false;
            }

            // Kiểm tra thứ 2, RIÊNG với việc lọc biên giới phía Tây ở trên, ÁP DỤNG CHO CẢ 2 nhà cung
            // cấp: dữ liệu đường xá 1 số khu vực bị đứt quãng, khiến nó nối 2 đoạn đường không liền
            // mạch bằng 1 đoạn thẳng băng qua BIỂN thay vì báo lỗi (bug thực tế đã gặp: đường vẽ ra
            // hẳn ngoài khơi Biển Đông, không bám theo bất kỳ con đường thật nào). 1 tuyến đường thật
            // đi theo road network luôn có rất nhiều điểm sát nhau (overview=full) — nếu có 2 điểm
            // LIÊN TIẾP cách nhau bất thường xa, gần như chắc chắn là đường nối ảo qua biển/vùng
            // không có dữ liệu, không phải đường thật. Ngưỡng 15km (thay vì 8km ban đầu) vì route hợp
            // lệ thực tế cho quãng đường rất xa (VD Hải Phòng - TP.HCM, hơn 1600km) từng bị chặn NHẦM
            // do 1 đoạn cao tốc được ORS đơn giản hoá xuống còn ~7.5km giữa 2 điểm liên tiếp (dữ liệu
            // thật, không phải lỗi) — 15km vẫn đủ chặt để bắt lỗi nối ảo qua biển hàng chục/trăm km.
            const R = 6371000;
            for (let i = 1; i < coords.length; i++) {
                const [lng1, lat1] = coords[i - 1];
                const [lng2, lat2] = coords[i];
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLng = (lng2 - lng1) * Math.PI / 180;
                const a = Math.sin(dLat / 2) ** 2
                    + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
                const segmentMeters = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                if (segmentMeters > 15000) return false;
            }

            return true;
        },
        // Kiểm tra hành lang di chuyển — bổ sung cho routeStaysInVietnam() ở trên. VN_WEST_BORDER chỉ
        // chặn được lệch sang phía TÂY (Lào/Campuchia); không bắt được trường hợp lệch sang hướng khác
        // trong khi vẫn kỹ thuật nằm trong khung biên giới xấp xỉ đó — bug thực tế đã gặp: ORS trả
        // về 1 route từ Cần Thơ đi vòng ngoằn ngoèo ra tận vịnh Thái Lan (phía Tây/Nam) rồi mới quay
        // lại, dù không chạm biên giới Lào/Campuchia. Cách chắc chắn hơn: so khung toạ độ của TOÀN
        // BỘ route với khung toạ độ giữa đúng điểm đi/đến của CHUYẾN ĐÓ — 1 đường thật hợp lý (dù có
        // vòng theo địa hình/bờ biển) không bao giờ đi lệch quá xa khỏi hành lang giữa 2 điểm, còn
        // route bị lỗi/đi vòng vô lý thì lệch hẳn ra ngoài. Biên nới rộng theo TỈ LỆ khoảng cách
        // chuyến đi (chuyến càng xa, đường càng có thể uốn lượn theo địa hình/bờ biển xa hơn).
        routeStaysInCorridor(fromLat, fromLng, toLat, toLng, coords) {
            const latMin = Math.min(fromLat, toLat);
            const latMax = Math.max(fromLat, toLat);
            const lngMin = Math.min(fromLng, toLng);
            const lngMax = Math.max(fromLng, toLng);
            const span = Math.max(latMax - latMin, lngMax - lngMin, 0.2);
            const margin = Math.max(0.6, span * 0.45);
            return coords.every((c) => {
                const lng = c[0];
                const lat = c[1];
                return lat >= latMin - margin && lat <= latMax + margin
                    && lng >= lngMin - margin && lng <= lngMax + margin;
            });
        },
        // Đường chỉ đường từ vị trí thật (myLat/myLng) tới sân đang xem — CHỈ vẽ khi người dùng
        // bấm nút Chỉ đường (xem template phía dưới), không tự động vẽ mỗi lần chọn sân như bản
        // trước. 2 nhà cung cấp tuỳ theo cấu hình admin (directionsOrsEnabled, xem
        // App\Filament\Pages\DirectionsSettings): OpenRouteService (chặn định tuyến qua Lào/
        // Campuchia ngay từ API, xem fetchRouteOrs()) khi bật, hoặc OSRM công cộng như trước đây
        // (tự lọc điểm ngoài lãnh thổ VN, xem fetchRouteOsrm()) khi tắt — nếu request lỗi thì KHÔNG
        // vẽ gì cả (đã bỏ hẳn đường thẳng nét đứt dự phòng trước đây — dễ gây hiểu lầm/chồng lẫn với
        // đường thật khi vị trí người dùng đổi giữa 2 lần bấm, bug đã gặp). routeToken chống race
        // condition khi bấm nút Chỉ đường nhiều lần liên tiếp trước khi request trước kịp trả lời.
        // Chốt myLat/myLng vào biến cục bộ NGAY LÚC BẤM (không đọc lại this.myLat/this.myLng sau
        // khi fetch xong) — nếu vị trí đổi giữa lúc đang chờ fetch (bấm nút Tìm sân gần đây ở popup
        // lọc), đường vẽ ra vẫn đúng khớp với toạ độ lúc bấm, không lẫn toạ độ mới hơn.
        async drawRoute(v) {
            this.clearRoute();
            this.routeToken++;
            const myToken = this.routeToken;
            if (this.myLat === null || this.myLng === null || !v.lat || !v.lng) return;
            const fromLat = this.myLat;
            const fromLng = this.myLng;
            this.routeLoading = true;
            try {
                let route = this.directionsOrsEnabled
                    ? await this.fetchRouteOrs(fromLat, fromLng, v.lat, v.lng)
                    : await this.fetchRouteOsrm(fromLat, fromLng, v.lat, v.lng);
                // ORS không dùng được (hết quota/lỗi kết nối: 'FALLBACK') HOẶC trả về route nhưng
                // không đạt chuẩn hợp lệ (null — vd bug từng gặp: đi vòng qua Philippines) — cả 2
                // trường hợp đều tự động thử lại bằng OSRM ngay, khách không thấy lỗi gì cả trừ khi
                // OSRM cũng không tìm được đường hợp lệ.
                if (this.directionsOrsEnabled && (!route || route === 'FALLBACK')) {
                    route = await this.fetchRouteOsrm(fromLat, fromLng, v.lat, v.lng);
                }
                if (myToken !== this.routeToken) return;
                if (!route) {
                    this.routeError = 'Chưa tìm được đường đi phù hợp cho quãng đường này.';
                    return;
                }

                // Chỉ tính toán hình học THUẦN (không đụng bản đồ/DOM) trước — getBounds()/isValid()
                // an toàn tuyệt đối, không thể bị ảnh hưởng bởi lỗi nội bộ Leaflet khi thao tác bản
                // đồ thật (xem giải thích ở dưới).
                const polyline = L.polyline(route.latlngs, { color: '#0B6B3A', weight: 4, opacity: .85 });
                const bounds = polyline.getBounds();
                if (!bounds.isValid()) {
                    throw new Error('Toạ độ đường đi không hợp lệ.');
                }

                // Gán this.routeLine/routeSteps/label NGAY, TRƯỚC KHI đụng vào bản đồ thật (addTo/
                // fitBounds) — bug thật đã gặp: fitBounds() có thể ném lỗi NỘI BỘ của Leaflet (VD
                // Tooltip.js: Cannot read properties of null khi 1 tooltip khác đang ở trạng thái
                // dở dang lúc animate zoom) dù toạ độ route hoàn toàn hợp lệ. Trước đây gán
                // this.routeLine SAU fitBounds() — lỗi đó khiến polyline đã addTo(map) rồi nhưng
                // KHÔNG BAO GIỜ được gán vào this.routeLine, làm clearRoute() lần sau không biết
                // đường mà xoá (polyline kẹt vĩnh viễn trên bản đồ), đồng thời route/step lại báo
                // lỗi không tìm được dù dữ liệu API hoàn toàn đúng. Giờ gán TRƯỚC nên route/step
                // LUÔN hiển thị đúng ngay khi dữ liệu hợp lệ, bất kể bước zoom (chỉ là hiệu ứng phụ)
                // có lỗi hay không.
                this.clearRoute();
                this.routeLine = polyline;
                this.routeDistanceLabel = this.formatDistance(route.distance);
                this.routeDurationLabel = this.formatDuration(route.duration);
                this.routeSteps = route.steps;

                polyline.addTo(this.map);
                // fitBounds() (tự động zoom cho vừa khung nhìn) CHỈ là hiệu ứng phụ — lỗi ở bước này
                // (VD lỗi nội bộ Leaflet nói trên) không được phép làm mất route/step đã gán thành
                // công ở trên. map.stop() huỷ animation zoom/pan đang chạy dở (VD từ goTo() khi vừa
                // bấm chọn sân) trước khi tự gọi fitBounds(), giảm khả năng va chạm animation.
                try {
                    this.map.stop();
                    this.map.fitBounds(bounds, { padding: [60, 60] });
                } catch (zoomErr) {
                    console.warn('[drawRoute] fitBounds() lỗi (bỏ qua, route/step vẫn hiển thị bình thường):', zoomErr);
                }
            } catch (e) {
                // Trước đây nuốt lỗi âm thầm, không báo gì cả — khách tưởng bấm không có phản hồi.
                // Giờ luôn báo lỗi rõ ràng kèm log console.error (biết ngay lỗi kỹ thuật gì, không
                // phải đoán), đồng thời dọn sạch mọi trạng thái nửa vời (nếu có).
                console.error('[drawRoute] lỗi khi dựng đường trên bản đồ:', e);
                this.clearRoute();
                this.routeError = 'Chưa tìm được đường đi phù hợp cho quãng đường này.';
            } finally {
                if (myToken === this.routeToken) this.routeLoading = false;
            }
        },
        // Kiểm tra 1 route (bất kể từ OSRM hay ORS) có ĐỦ ĐIỀU KIỆN để hiển thị hay không — dùng
        // CHUNG cho cả 2 nhà cung cấp, đảm bảo tiêu chuẩn giống hệt nhau, không để lọt route nửa
        // vời (có đường nhưng thiếu step, hoặc hợp lệ theo nhà cung cấp này nhưng đi lạc theo
        // kiểu khác):
        // 1. Quãng đường trả về không được dài gấp nhiều lần khoảng cách chim bay thật — dấu hiệu
        //    rõ của route ghép nối lung tung qua đường vòng cực xa (bug đã gặp ở OSRM).
        // 2. Toàn bộ điểm phải nằm trong hành lang hợp lý giữa điểm đi/đến (routeStaysInCorridor())
        //    — bắt được MỌI hướng đi lạc bất thường (không riêng lệch Tây qua Lào/Campuchia), VD bug
        //    thật đã gặp: ORS vòng ra tận vịnh Thái Lan dù không chạm biên giới Lào/Campuchia.
        // 3. Toàn bộ điểm phải nằm trong khung lãnh thổ Việt Nam (chỉ với OSRM, xem checkWestBorder ở
        //    routeStaysInVietnam()) + không có đoạn nhảy bất thường qua biển (áp dụng cho cả 2 nhà
        //    cung cấp).
        // 4. Phải có ít nhất 1 bước chỉ dẫn (steps) — route vẽ được nhưng không có step trước đây
        //    vẫn hiển thị đường bình thường, gây khó hiểu vì thiếu hẳn phần hướng dẫn đi đường (bug
        //    đã gặp: step lúc có lúc không dù đường vẫn vẽ ra).
        isValidRoute(fromLat, fromLng, toLat, toLng, distanceMeters, coords, steps, checkWestBorder) {
            // console.warn kèm lý do cụ thể ở mỗi nhánh reject — để lần sau route bị từ chối oan,
            // mở DevTools Console là biết ngay bước nào chặn thay vì phải đoán/giả lập lại thủ công.
            if (!coords || coords.length < 2 || !steps || steps.length === 0) {
                console.warn('[isValidRoute] reject: thiếu coords hoặc steps', { coordsLen: coords?.length, stepsLen: steps?.length });
                return false;
            }

            const R = 6371000;
            const dLat = (toLat - fromLat) * Math.PI / 180;
            const dLng = (toLng - fromLng) * Math.PI / 180;
            const a = Math.sin(dLat / 2) ** 2
                + Math.cos(fromLat * Math.PI / 180) * Math.cos(toLat * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
            const straightMeters = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            if (straightMeters > 2000 && distanceMeters > straightMeters * 2.5) {
                console.warn('[isValidRoute] reject: quãng đường quá dài so với đường chim bay', { straightMeters, distanceMeters, ratio: distanceMeters / straightMeters });
                return false;
            }

            if (!this.routeStaysInCorridor(fromLat, fromLng, toLat, toLng, coords)) {
                console.warn('[isValidRoute] reject: ra khỏi hành lang di chuyển (routeStaysInCorridor)', { fromLat, fromLng, toLat, toLng });
                return false;
            }

            if (!this.routeStaysInVietnam(coords, checkWestBorder)) {
                console.warn('[isValidRoute] reject: routeStaysInVietnam (biên giới Tây và/hoặc khoảng cách nhảy bất thường)', { checkWestBorder });
                return false;
            }

            return true;
        },
        // OSRM demo server (miễn phí, không cần API key, gọi thẳng từ trình duyệt) — nhánh MẶC ĐỊNH
        // khi chưa bật OpenRouteService ở admin. Trả về null nếu không định tuyến được HOẶC route
        // không đạt tiêu chuẩn hợp lệ (isValidRoute()), để drawRoute() báo lỗi thay vì vẽ sai/thiếu.
        // OSRM không có tham số tránh quốc gia nên PHẢI tự lọc biên giới Tây (checkWestBorder=true).
        async fetchRouteOsrm(fromLat, fromLng, toLat, toLng) {
            const url = 'https://router.project-osrm.org/route/v1/driving/'
                + fromLng + ',' + fromLat + ';' + toLng + ',' + toLat
                + '?overview=full&geometries=geojson&steps=true';
            const res = await fetch(url);
            const data = await res.json();
            const route = data.routes && data.routes[0] ? data.routes[0] : null;
            const coords = route ? route.geometry.coordinates : null;
            if (!route || !coords) return null;

            const rawSteps = (route.legs && route.legs[0] && route.legs[0].steps) || [];
            const steps = rawSteps.map((s) => ({
                text: this.stepInstruction(s),
                distance: this.formatDistance(s.distance),
            }));
            if (! this.isValidRoute(fromLat, fromLng, toLat, toLng, route.distance, coords, steps, true)) return null;

            return {
                distance: route.distance,
                duration: route.duration,
                latlngs: coords.map((c) => [c[1], c[0]]),
                steps,
            };
        },
        // OpenRouteService qua proxy backend (App\Http\Controllers\Api\DirectionsController) — API
        // key giữ RIÊNG ở server, không lộ ra JS. avoid_countries chặn định tuyến qua Lào/Campuchia
        // ngay tại API, nhưng KHÔNG đảm bảo tuyệt đối không có điểm nào lọt gần biên/ra ngoài khơi
        // (bug thật đã gặp 2 lần: ORS vẽ tuyến qua tận Philippines, và qua vịnh Thái Lan/Campuchia
        // dù avoid_countries đang bật) — vẫn chạy đủ cả isValidRoute() (biên giới Tây + hành lang +
        // gap-check) như OSRM, không tin tưởng tuyệt đối vào riêng avoid_countries.
        //
        // Trả về chuỗi 'FALLBACK' (thay vì route/null) khi backend báo hết quota/lỗi kết nối ORS
        // (field fallback:true trong response, xem DirectionsController) — drawRoute() đọc tín hiệu
        // này để TỰ ĐỘNG chuyển sang OSRM ngay, không cần vào admin tắt tay. Hết quota chỉ là tạm
        // thời trong ngày (ORS tự reset theo giờ UTC) nên không đổi cấu hình 'enabled' ở DB — lần
        // gọi tiếp theo sau giờ reset sẽ tự dùng lại ORS bình thường.
        async fetchRouteOrs(fromLat, fromLng, toLat, toLng) {
            const res = await fetch('/api/directions/route', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ from_lat: fromLat, from_lng: fromLng, to_lat: toLat, to_lng: toLng }),
            });
            if (!res.ok) {
                try {
                    const errData = await res.json();
                    if (errData.fallback) return 'FALLBACK';
                } catch (e) {}
                return null;
            }
            const data = await res.json();
            if (!data.coordinates || !data.coordinates.length) return null;

            const steps = (data.steps || []).map((s) => ({
                text: this.stepInstructionOrs(s),
                distance: this.formatDistance(s.distance),
            }));
            if (! this.isValidRoute(fromLat, fromLng, toLat, toLng, data.distance, data.coordinates, steps, false)) return null;

            return {
                distance: data.distance,
                duration: data.duration,
                latlngs: data.coordinates.map((c) => [c[1], c[0]]),
                steps,
            };
        },
        selectVenue(v, i) {
            this.selected = v;
            this.selectedIndex = i;
            this.activeTab = 'info';
            this.sidebarOpen = true;
            this.goTo(v);
            this.loadAvailability(v.slug);
            this.loadServices(v.slug);
            // Đổi sang sân KHÁC thì đường chỉ đường cũ (nếu có) không còn đúng nữa — xoá đi, chờ
            // người dùng tự bấm lại nút Chỉ đường cho sân mới nếu cần.
            this.routeToken++;
            this.clearRoute();
        },
        closeDetail() {
            this.selected = null;
            this.selectedIndex = null;
            this.routeToken++;
            this.clearRoute();
        },
        toggleFavorite() {
            if (!this.isLoggedIn) { window.dispatchEvent(new CustomEvent('open-auth-modal')); return; }
        },
        shareVenue(v) {
            if (navigator.share) { navigator.share({ title: v.name, url: v.url }); }
            else { navigator.clipboard.writeText(v.url); }
        },
    }"
    class="relative h-[calc(100dvh-56px)] w-full overflow-hidden lg:h-[calc(100vh-96px)]"
>
    {{-- Hàng chip chi nhánh — thay cho pill lọc theo môn thể thao (xem giải thích trong MapPage.php) --}}
    <div x-show="!selected" class="absolute left-0 right-0 top-0 z-[500] overflow-x-auto px-3 py-2" style="background:linear-gradient(to bottom, rgba(255,255,255,.95), rgba(255,255,255,0));">
        <div class="flex w-max gap-2">
            <template x-for="(v, i) in venues" :key="i">
                <button type="button" @click="selectVenue(v, i)"
                    class="whitespace-nowrap rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm hover:border-[#0B6B3A] hover:text-[#0B6B3A]"
                    x-text="v.name"></button>
            </template>
        </div>
    </div>

    {{-- Cột trái: danh sách tìm kiếm HOẶC panel chi tiết sân (khi đã chọn 1 sân) --}}
    <div x-show="sidebarOpen" x-cloak
         class="absolute inset-y-0 left-0 z-[400] flex w-full max-w-md flex-col border-r border-gray-200 bg-white shadow-lg lg:w-[600px]"
         :class="selected ? '' : 'pt-14'">

        {{-- ===== Danh sách tìm kiếm ===== --}}
        <template x-if="!selected">
            <div class="flex h-full flex-col">
                <div class="border-b border-gray-100 p-3">
                    <div class="flex items-center gap-2 rounded-full border border-gray-200 px-3 py-2">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                        </svg>
                        <input x-model="search" type="text" placeholder="Tìm kiếm sân quanh đây."
                               class="w-full border-0 p-0 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0">
                    </div>
                </div>

                {{-- Lọc theo bán kính — chỉ có ý nghĩa khi đã biết vị trí thật (myLat/myLng), ẩn
                     hẳn nếu chưa định vị được thay vì hiện 1 control vô dụng. --}}
                <div x-show="myLat !== null" x-cloak class="border-b border-gray-100 px-4 py-3">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 border-[#0B6B3A]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#0B6B3A]"></span>
                        </span>
                        <span class="text-sm font-semibold text-gray-800">
                            Khoảng cách
                            <span x-text="maxDistanceKm >= 20 ? '(không giới hạn)' : '(' + maxDistanceKm + ' km)'"></span>
                        </span>
                    </div>
                    <input type="range" min="1" max="20" step="1" x-model.number="maxDistanceKm"
                           class="h-1.5 w-full cursor-pointer appearance-none rounded-full accent-[#0B6B3A]"
                           :style="'background: linear-gradient(to right, #0B6B3A 0%, #0B6B3A ' + ((maxDistanceKm - 1) / 19 * 100) + '%, #e5e7eb ' + ((maxDistanceKm - 1) / 19 * 100) + '%, #e5e7eb 100%)'">
                    <div class="mt-1 flex justify-between text-[11px] text-gray-400">
                        <span>1 km</span>
                        <span>20 km</span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <template x-for="(v, i) in filtered" :key="i">
                        <button type="button" @click="selectVenue(v, i)"
                                class="flex w-full items-start gap-3 border-b border-gray-50 px-4 py-3 text-left hover:bg-gray-50">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-900" x-text="v.name"></span>
                                <span class="block text-xs text-gray-500" x-text="v.address"></span>
                            </span>
                            <span x-show="venueDistanceKm(v) !== null" x-cloak
                                  class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600"
                                  x-text="venueDistanceKm(v) !== null ? venueDistanceKm(v).toFixed(1) + ' km' : ''"></span>
                        </button>
                    </template>

                    <p x-show="!filtered.length" class="p-4 text-center text-sm text-gray-400">Không tìm thấy sân phù hợp.</p>
                </div>
            </div>
        </template>

        {{-- ===== Panel chi tiết sân ===== --}}
        <template x-if="selected">
            <div class="flex h-full flex-col overflow-y-auto">
                <div class="flex items-center gap-2 border-b border-gray-100 p-3">
                    <button type="button" @click="closeDetail()" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    </button>
                    <span class="flex-1 truncate text-sm font-semibold text-gray-900" x-text="selected.name"></span>
                    <button type="button" @click="closeDetail()" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Ảnh bìa + nút hành động --}}
                <div class="relative h-40 w-full shrink-0 bg-gray-100">
                    <template x-if="selected.image_url">
                        <img :src="selected.image_url" :alt="selected.name" class="h-full w-full object-cover">
                    </template>
                    <div class="absolute inset-x-3 bottom-3 flex items-center gap-2">
                        <button type="button" @click="shareVenue(selected)"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-gray-600 shadow-sm hover:text-gray-900">
                            <x-heroicon-o-share class="h-4 w-4" />
                        </button>
                        <button type="button" @click="toggleFavorite()"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-gray-600 shadow-sm hover:text-rose-500">
                            <x-heroicon-o-heart class="h-4 w-4" />
                        </button>
                        {{-- Chỉ đường: CHỈ vẽ khi bấm vào (không tự động khi chọn sân) — ẩn khi
                             chưa có vị trí thật (myLat/myLng, set ở showMyLocationMarker()). --}}
                        <button type="button" @click="drawRoute(selected); activeTab = 'directions'" x-show="myLat !== null && myLng !== null" x-cloak
                                :disabled="routeLoading"
                                class="flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-gray-600 shadow-sm hover:text-[#0B6B3A] disabled:opacity-60">
                            <svg x-show="!routeLoading" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <svg x-show="routeLoading" x-cloak class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12a8 8 0 018-8V2.5"/>
                            </svg>
                        </button>
                        <a :href="selected.url"
                           class="ml-auto rounded-full bg-[#F5A623] px-4 py-2 text-xs font-extrabold uppercase text-white shadow-sm hover:brightness-95">
                            Đặt lịch
                        </a>
                    </div>
                </div>

                {{-- Chỉ dẫn đi đường chuyển thành 1 TAB riêng bên dưới (activeTab === 'directions')
                     thay vì chèn ngay đây — trước đây đặt NGAY DƯỚI ảnh bìa, quãng đường dài (nhiều
                     step) đẩy toàn bộ badge đánh giá/tên/địa chỉ/hàng tab xuống rất sâu, nhìn như
                     mất hẳn các tab thông tin (bug đã gặp, ảnh thực tế quãng TP.HCM → Cần Thơ). --}}

                {{-- Badge đánh giá --}}
                <div class="px-3 pt-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#0B6B3A]/10 px-3 py-1 text-xs font-bold text-[#0B6B3A]">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.958a1 1 0 00.95.69h4.162c.969 0 1.371 1.24.588 1.81l-3.37 2.448a1 1 0 00-.363 1.118l1.287 3.957c.3.922-.755 1.688-1.538 1.118l-3.37-2.448a1 1 0 00-1.175 0l-3.37 2.448c-.783.57-1.838-.196-1.538-1.118l1.287-3.957a1 1 0 00-.363-1.118L2.062 9.385c-.783-.57-.38-1.81.588-1.81h4.163a1 1 0 00.95-.69l1.286-3.958z"/></svg>
                        <template x-if="selected.rating_avg">
                            <span x-text="selected.rating_avg + ' (' + selected.rating_count + ' đánh giá)'"></span>
                        </template>
                        <template x-if="!selected.rating_avg">
                            <span>Chưa có đánh giá</span>
                        </template>
                    </span>
                </div>

                {{-- Tên + badge môn thể thao --}}
                <div class="px-3 pt-3">
                    <p class="text-base font-extrabold text-gray-900" x-text="selected.name"></p>
                    <template x-if="selected.sport_label">
                        <span class="mt-1 inline-block rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-600" x-text="selected.sport_label"></span>
                    </template>
                </div>

                {{-- Địa chỉ / giờ / hotline --}}
                <div class="mx-3 mt-3 space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3 text-sm">
                    <p class="flex items-start gap-2 text-gray-700">
                        <x-heroicon-o-map-pin class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                        <span x-text="selected.address"></span>
                    </p>
                    <p class="flex items-center gap-2 text-gray-700" x-show="selected.hours">
                        <x-heroicon-o-clock class="h-4 w-4 shrink-0 text-gray-400" />
                        <span x-text="selected.hours"></span>
                    </p>
                    <p class="flex items-center gap-2" x-show="selected.hotline">
                        <x-heroicon-o-phone class="h-4 w-4 shrink-0 text-gray-400" />
                        <a class="font-semibold text-[#0B6B3A]" :href="'tel:' + selected.hotline" x-text="selected.hotline"></a>
                    </p>
                </div>

                {{-- Tabs — sticky trong panel cuộn (xem div bọc ngoài "overflow-y-auto") để luôn
                     bấm chuyển tab được dù nội dung tab đang xem (vd bảng khung giờ/bảng giá) dài
                     và đã cuộn xuống — trước đó tab nằm trong luồng cuộn bình thường nên cuộn
                     xuống xem bảng dài là mất luôn hàng tab, tưởng như không đổi tab được nữa.
                     QUAN TRỌNG: sticky và overflow-x-auto PHẢI đặt ở 2 phần tử KHÁC NHAU (ngoài/
                     trong), không được gộp chung 1 class list — đây là lỗi CSS phổ biến: gán cả 2
                     trên CÙNG 1 phần tử khiến chính phần tử đó trở thành 1 "vùng cuộn" mới, phá vỡ
                     ngữ cảnh tính toán vị trí sticky, khiến hàng tab biến mất hẳn khi cuộn (bug thật
                     đã gặp: bấm Chỉ đường/xem step dài xong thì mất sạch hàng tab, không bấm lại
                     tab khác được, phải bấm X đóng hẳn panel). --}}
                <div class="sticky top-0 z-10 mt-4 border-b border-gray-100 bg-white">
                    <div class="flex gap-4 overflow-x-auto px-3 text-sm">
                        <template x-for="tab in [
                            {key:'info', label:'Thông tin'},
                            {key:'directions', label:'Chỉ đường'},
                            {key:'membership', label:'Gói hội viên'},
                            {key:'services', label:'Dịch vụ'},
                            {key:'gallery', label:'Hình ảnh'},
                            {key:'policy', label:'Điều khoản & quy định'},
                            {key:'reviews', label:'Đánh giá'},
                        ]" :key="tab.key">
                            <button type="button" @click="activeTab = tab.key"
                                class="relative shrink-0 whitespace-nowrap border-b-2 pb-2 pt-1 font-semibold transition"
                                :class="activeTab === tab.key ? 'border-[#0B6B3A] text-[#0B6B3A]' : 'border-transparent text-gray-500'">
                                <span x-text="tab.label"></span>
                                {{-- Chấm xanh báo đã có kết quả chỉ đường sẵn sàng — chỉ hiện đúng
                                     tab 'directions', giúp nhận ra ngay không cần bấm vào xem thử. --}}
                                <span x-show="tab.key === 'directions' && routeSteps.length" x-cloak
                                      class="absolute -right-1.5 -top-0.5 h-1.5 w-1.5 rounded-full bg-[#0B6B3A]"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="flex-1 p-3">
                    <div x-show="activeTab === 'directions'">
                        <div x-show="routeLoading" x-cloak class="flex items-center gap-2 rounded-xl border border-gray-100 bg-gray-50 p-3 text-sm text-gray-500">
                            <svg class="h-4 w-4 shrink-0 animate-spin text-[#0B6B3A]" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V2.5"></path>
                            </svg>
                            <span>Đang tìm đường đi...</span>
                        </div>

                        <p x-show="routeError && !routeLoading" x-cloak class="text-xs font-medium text-red-500" x-text="routeError"></p>

                        <div x-show="routeSteps.length && !routeLoading" x-cloak class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Hướng dẫn đi đường</p>
                                <button type="button" @click="clearRoute()" class="text-gray-400 hover:text-gray-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <p class="mb-2 text-xs font-semibold text-[#0B6B3A]" x-text="routeDistanceLabel + (routeDurationLabel ? (' · khoảng ' + routeDurationLabel) : '')"></p>
                            <ol class="space-y-2 text-sm">
                                <template x-for="(step, si) in routeSteps" :key="si">
                                    <li class="flex items-start gap-2">
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#0B6B3A]/10 text-[10px] font-bold text-[#0B6B3A]" x-text="si + 1"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-gray-800" x-text="step.text"></span>
                                            <span class="block text-xs text-gray-400" x-show="step.distance" x-text="step.distance"></span>
                                        </span>
                                    </li>
                                </template>
                            </ol>
                        </div>

                        {{-- Trạng thái rỗng — chưa từng bấm Chỉ đường, hoặc chưa có vị trí thật. Cho
                             bấm ngay tại đây luôn, không bắt buộc phải quay lại nút ở ảnh bìa. --}}
                        <div x-show="!routeLoading && !routeError && !routeSteps.length" class="py-8 text-center">
                            <svg class="mx-auto mb-2 h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <p class="text-sm text-gray-400" x-show="myLat !== null && myLng !== null">Bấm nút bên dưới để xem hướng dẫn đi đường.</p>
                            <p class="text-sm text-gray-400" x-show="myLat === null || myLng === null">Cần biết vị trí của bạn trước — bấm Tìm sân gần đây ở bộ lọc.</p>
                            <button type="button" @click="drawRoute(selected)" x-show="myLat !== null && myLng !== null"
                                    class="mt-3 rounded-lg bg-[#0B6B3A] px-4 py-2 text-xs font-bold text-white hover:opacity-90">
                                Chỉ đường
                            </button>
                        </div>
                    </div>

                    <div x-show="activeTab === 'info'">
                        <p class="text-sm text-gray-600" x-text="selected.description || 'Chưa có mô tả.'"></p>
                        <p class="mt-1 mb-3 text-xs text-gray-400" x-show="selected.court_count">
                            <span x-text="selected.court_count"></span> sân con đang mở đặt lịch.
                        </p>

                        {{-- Ưu đãi THẬT đang áp dụng cho chi nhánh này (Promotion đã gắn khung giờ
                             của sân thuộc chi nhánh — xem MapPage::mount()), cùng nguồn dữ liệu với
                             tab "Ưu đãi" ở trang Khám phá. --}}
                        <template x-if="selected.promotions && selected.promotions.length">
                            <div class="mb-4 space-y-2">
                                <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Ưu đãi đang áp dụng</p>
                                <template x-for="(promo, pi) in selected.promotions" :key="pi">
                                    <div class="rounded-lg p-3 text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
                                        <p class="flex items-center gap-1.5 text-sm font-extrabold">
                                            <span>🎁</span>
                                            <span x-text="promo.name"></span>
                                        </p>
                                        <p class="mt-0.5 text-xs text-white/90" x-show="promo.summary" x-text="promo.summary"></p>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Bảng khung giờ HÔM NAY — chỉ để xem nhanh (không đặt trực tiếp ở đây,
                             xem MapPage::getTodayAvailability()); bấm "Đặt lịch" ở ảnh bìa phía
                             trên để sang trang đặt lịch đầy đủ, chọn được ngày khác. --}}
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Khung giờ hôm nay</p>

                        <div x-show="availabilityLoading" class="py-6 text-center text-xs text-gray-400">Đang tải khung giờ...</div>

                        <template x-if="!availabilityLoading && availability && availability.courts && availability.courts.length">
                            <div class="overflow-x-auto rounded-lg border border-gray-100">
                                <table class="w-full border-collapse text-[11px]">
                                    <thead>
                                        <tr>
                                            <th class="sticky left-0 z-10 bg-gray-50 px-2 py-1.5 text-left font-bold text-gray-600">Sân</th>
                                            <template x-for="(t, ti) in availability.times" :key="ti">
                                                <th class="whitespace-nowrap bg-gray-50 px-1.5 py-1.5 text-center font-bold text-gray-500" x-text="t"></th>
                                            </template>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(court, ci) in availability.courts" :key="ci">
                                            <tr class="border-t border-gray-100">
                                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-2 py-1 font-semibold text-gray-800" x-text="court.name"></td>
                                                <template x-for="(cell, cj) in court.cells" :key="cj">
                                                    <td class="p-0.5">
                                                        <div class="h-5 w-8 rounded"
                                                             :class="{
                                                                'bg-white border border-gray-200': cell.status === 'free',
                                                                'bg-red-400': cell.status === 'booked',
                                                                'bg-gray-300': cell.status === 'locked',
                                                             }"
                                                             :title="cell.label + ' — ' + (cell.status === 'free' ? 'Trống' : cell.status === 'booked' ? 'Đã đặt' : 'Khoá')"></div>
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <p x-show="!availabilityLoading && (!availability || !availability.courts || !availability.courts.length)"
                           class="text-xs text-gray-400">Chưa có khung giờ để hiển thị.</p>

                        <a :href="selected.url" class="mt-3 block w-full rounded-lg bg-[#0B6B3A] py-2.5 text-center text-sm font-bold text-white hover:opacity-90">
                            Xem đầy đủ &amp; đặt lịch
                        </a>
                    </div>

                    <div x-show="activeTab === 'gallery'">
                        <div class="grid grid-cols-3 gap-2" x-show="selected.gallery && selected.gallery.length">
                            <template x-for="(img, gi) in (selected.gallery || [])" :key="gi">
                                <img :src="img" class="aspect-square w-full rounded-lg object-cover">
                            </template>
                        </div>
                        <p class="text-sm text-gray-400" x-show="!selected.gallery || !selected.gallery.length">Chưa có hình ảnh.</p>
                    </div>

                    <div x-show="activeTab === 'membership'">
                        <p class="text-sm text-gray-400">Chưa có gói hội viên.</p>
                    </div>

                    <div x-show="activeTab === 'services'">
                        <div x-show="servicesLoading" class="py-6 text-center text-xs text-gray-400">Đang tải...</div>

                        <template x-if="!servicesLoading && servicesData">
                            <div>
                                {{-- Bảng giá theo khung giờ (dữ liệu giá thật — chỉ 1 mức giá/khung giờ,
                                     khác ảnh mẫu có 2 cột Cố định/Vãng lai vì hệ thống hiện chưa có
                                     khái niệm đó, xem MapPage::getServicesAndPricing()). --}}
                                <template x-if="servicesData.pricing && servicesData.pricing.length">
                                    <div class="mb-4">
                                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Bảng giá sân</p>
                                        <template x-for="(court, ci) in servicesData.pricing" :key="ci">
                                            <div class="mb-3 overflow-hidden rounded-lg border border-gray-100">
                                                <div class="bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-700" x-text="court.name"></div>
                                                <table class="w-full text-xs">
                                                    <tbody>
                                                        <template x-for="(row, ri) in court.rows" :key="ri">
                                                            <tr class="border-t border-gray-100">
                                                                <td class="px-3 py-1.5 text-gray-600" x-text="row.range"></td>
                                                                <td class="px-3 py-1.5 text-right font-semibold text-gray-900" x-text="row.price"></td>
                                                            </tr>
                                                        </template>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="servicesData.services && servicesData.services.length">
                                    <div>
                                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Dịch vụ thêm</p>
                                        <div class="divide-y divide-gray-100 rounded-lg border border-gray-100">
                                            <template x-for="(s, si) in servicesData.services" :key="si">
                                                <div class="flex items-center justify-between px-3 py-2 text-sm">
                                                    <span class="text-gray-700" x-text="s.name"></span>
                                                    <span class="font-semibold text-gray-900" x-text="s.price"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <p class="text-sm text-gray-400"
                                   x-show="(!servicesData.pricing || !servicesData.pricing.length) && (!servicesData.services || !servicesData.services.length)">
                                    Chưa có dịch vụ bổ sung.
                                </p>
                            </div>
                        </template>
                    </div>
                    <div x-show="activeTab === 'policy'">
                        <p class="text-sm text-gray-400">Chưa có điều khoản & quy định.</p>
                    </div>
                    <div x-show="activeTab === 'reviews'">
                        <p class="text-sm text-gray-400" x-show="!selected.rating_count">Chưa có đánh giá nào.</p>
                        <p class="text-sm text-gray-600" x-show="selected.rating_count" x-text="'Điểm trung bình ' + selected.rating_avg + '/5 từ ' + selected.rating_count + ' đánh giá.'"></p>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Nút mở/đóng sidebar trên mobile --}}
    <button type="button" @click="sidebarOpen = !sidebarOpen"
            x-show="!selected"
            class="absolute left-3 top-[62px] z-[450] flex h-9 w-9 items-center justify-center rounded-full bg-white shadow-md lg:hidden">
        <svg class="h-5 w-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    {{-- wire:ignore BẮT BUỘC: Leaflet tự vẽ/thao tác DOM bên trong div này (tiles, panes, marker)
         hoàn toàn ngoài tầm biết của Livewire. Không có wire:ignore, mỗi lần gọi $wire.call()
         (loadAvailability()/loadServices() khi mở panel chi tiết 1 sân) khiến Livewire chạy 1
         vòng morph DOM cho TOÀN BỘ component — morphdom không nhận diện được các node Leaflet tự
         tạo (không phải do Blade render ra) nên dọn sạch/thay thế chúng, làm bản đồ "biến mất"
         sau khi đợi phản hồi (bug đã gặp: đợi 1 lát ở map thì mất bản đồ). --}}
    <div x-ref="mapEl" wire:ignore class="relative z-0 h-full w-full"></div>

    {{-- @once ở TRONG root element (Livewire chỉ chấp nhận đúng 1 phần tử gốc) --}}
    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <style>
            .map-pin-icon { position: relative; width: 42px; height: 42px; filter: drop-shadow(0 2px 3px rgba(0,0,0,.35)); }
            .map-pin-icon svg:first-child { position: absolute; inset: 0; width: 42px; height: 42px; }
            .map-pin-glyph { position: absolute; inset: 0; width: 42px; height: 42px; }
            .island-label {
                display: inline-block; white-space: nowrap; font-weight: 700; font-size: 12px;
                color: #0f4c81; letter-spacing: .03em; text-shadow: 0 1px 2px rgba(255,255,255,.9), 0 0 4px rgba(255,255,255,.9);
                pointer-events: none;
            }
        </style>
    @endonce
</div>
