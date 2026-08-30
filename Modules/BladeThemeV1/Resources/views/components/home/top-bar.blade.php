{{--
    Top-bar xanh lá trên cùng trang chủ, theo mẫu tham khảo: ngày hôm nay (+ tên khách khi đã đăng
    nhập) bên trái, nút Đăng nhập/Đăng ký (hoặc cờ + chuông thông báo khi đã đăng nhập) bên phải.
    Trạng thái đăng nhập chỉ có ở client (localStorage.auth_token, xem bottom-sidebar.blade.php)
    nên toggle bằng Alpine, không Blade @auth (SSR không biết được).
--}}
@php
    $todayLabel = \Illuminate\Support\Str::ucfirst(
        \Carbon\Carbon::now()->locale('vi')->isoFormat('dddd, DD/MM/YYYY')
    );
    $generalSettings = new \App\Settings\GeneralSettings();
    // Bản logo màu sáng (brand_logo_light_version) để nổi trên nền xanh lá đậm của top-bar — nếu
    // chưa cấu hình thì rơi về logo thường (brand_logo), cùng nguồn dữ liệu Header.php đang dùng.
    $siteLogoLight = $generalSettings->brand_logo_light_version ?? $generalSettings->brand_logo ?? null;
@endphp

<div
    x-data="{
        isLoggedIn: false,
        userName: '',
        initials: '',
        init() {
            this.check();
            window.addEventListener('auth-state-changed', () => this.check());
        },
        check() {
            this.isLoggedIn = !!(localStorage.getItem('auth_token') && localStorage.getItem('auth_user'));
            if (this.isLoggedIn) {
                try {
                    const user = JSON.parse(localStorage.getItem('auth_user'));
                    this.userName = user?.fullname || '';
                } catch (e) { this.userName = ''; }
            }
            // Lấy 2 ký tự đầu của TỪ CUỐI + TỪ ĐẦU tên (kiểu Nguyễn Văn Nam -> NN) cho avatar tròn
            // hiển thị thay chỗ cờ ngôn ngữ khi đã đăng nhập.
            const words = this.userName.trim().split(/\s+/).filter(Boolean);
            this.initials = words.length
                ? (words[0][0] + (words[words.length - 1][0] || '')).toUpperCase()
                : 'KH';
        },
        // Cùng logic với components/header/actions/auth-button.blade.php (gọi API logout thật,
        // không chỉ xoá localStorage) — tách riêng bản này vì top-bar không dùng chung x-data với
        // component đó.
        logout() {
            const token = localStorage.getItem('auth_token');
            if (token) {
                fetch('/api/auth/logout', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
                });
            }
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.dispatchEvent(new CustomEvent('auth-state-changed'));
        },
    }"
    {{-- lg:sticky (không phải fixed) — giữ nguyên vị trí trong luồng bố cục nên KHÔNG cần thêm
         padding-top bù trừ ở từng trang, chỉ "dính" lại khi cuộn qua, đúng hiệu ứng "header cố
         định" mong muốn nhưng an toàn hơn fixed (fixed sẽ đè lên đúng phần nội dung đầu trang nếu
         quên bù padding, phải sửa TỪNG trang). Chỉ áp dụng ở lg+ (desktop) theo đúng yêu cầu —
         mobile vẫn cuộn bình thường như cũ. --}}
    class="w-full lg:sticky lg:top-0 lg:z-[1150]"
    style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 55%, #1AA859 100%);"
>
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
            {{-- Nút hamburger mở drawer-menu (menu danh mục CMS cũ) đã BỎ theo yêu cầu người
                 dùng — trên mobile/tablet, điều hướng chính đã có đủ ở bottom nav
                 (livewire/bottom-sidebar.blade.php: Trang chủ/Bản đồ/Khám phá/Nổi bật/Tài khoản)
                 nên nút này chỉ còn trùng lặp/thừa. Logo giờ hiển thị ở MỌI kích thước màn hình
                 (trước đây chỉ lg+ mới có, mobile/tablet phải dùng hamburger) thay cho nút đó. --}}
            <a href="/" aria-label="Về trang chủ"
               class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 transition hover:bg-white/25">
                @if ($siteLogoLight)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($siteLogoLight) }}" alt="" class="h-6 w-6 object-contain">
                @else
                    <x-heroicon-s-globe-asia-australia class="h-5 w-5 text-white" />
                @endif
            </a>

            <div class="min-w-0">
                <span class="block text-sm font-semibold text-white/95">{{ $todayLabel }}</span>
                <span x-show="isLoggedIn && userName" x-cloak x-text="userName"
                      class="block truncate text-sm font-bold" style="color:#F5C518;"></span>
            </div>
        </div>

        {{-- Menu chính desktop — trước đây chỉ có ở thanh tìm kiếm (Bản đồ/Sân đã đặt/Yêu thích,
             components/home/search-bar.blade.php), thiếu hẳn Trang chủ/Khám phá/Nổi bật.
             "Khám phá" đặc biệt QUAN TRỌNG phải thêm ở đây — bottom-nav mobile
             (livewire/bottom-sidebar.blade.php) tự ẩn hoàn toàn từ 1024px trở lên
             (@media min-width:1024px { display:none }), nên nếu không thêm vào đây thì trang đó
             HOÀN TOÀN không thể truy cập được trên desktop (bug đã gặp). CHƯA thêm "Liên hệ" vì hệ
             thống hiện chưa có trang liên hệ thật (không route/nội dung nào tồn tại), tránh dựng
             link chết.
             "Nổi bật" trỏ route('posts.page') (trang tin tức/bài viết thật) — KHÔNG trỏ
             route('map.page') nữa (thử trước đó rồi đổi lại theo đúng yêu cầu người dùng: "Nổi
             bật" không phải chỗ lọc sân). Bỏ hẳn mục "Bài viết" riêng vì trùng đúng 1 đích đến với
             "Nổi bật" — gộp lại còn 1 mục duy nhất thay vì 2 link cùng trỏ 1 nơi. --}}
        <nav class="hidden shrink-0 items-center gap-6 lg:flex">
            <a href="/" class="text-sm font-semibold text-white/90 transition hover:text-white">Trang chủ</a>
            <a href="{{ route('explore.page') }}" class="text-sm font-semibold text-white/90 transition hover:text-white">Khám phá</a>
            <a href="{{ route('posts.page') }}" class="text-sm font-semibold text-white/90 transition hover:text-white">Nổi bật</a>
            {{-- Trang landing giới thiệu/thu hút chủ sân đăng ký hợp tác (partnerPage(), route
                 'partner.page') — chỉ 1 route/view tĩnh mới, không đụng logic đặt sân/giá thật. --}}
            <a href="{{ route('partner.page') }}" class="text-sm font-semibold text-white/90 transition hover:text-white">Làm đối tác</a>
        </nav>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <div x-show="!isLoggedIn" x-cloak class="flex items-center gap-2">
                <a href="{{ route('login.page') }}"
                   class="rounded-full bg-white px-4 py-1.5 text-xs font-bold text-[#0B6B3A] shadow-sm transition hover:bg-white/90 sm:px-5 sm:py-2 sm:text-sm">
                    Đăng nhập
                </a>
                <a href="{{ Route::has('register.page') ? route('register.page') : route('login.page') }}"
                   class="rounded-full border border-white/70 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-white/10 sm:px-5 sm:py-2 sm:text-sm">
                    Đăng kí
                </a>
            </div>

            <a href="/tai-khoan#orders-section" x-show="isLoggedIn" x-cloak
               class="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-white transition hover:bg-white/25">
                <x-heroicon-o-bell class="h-5 w-5" />
            </a>

            {{-- Đã đăng nhập: avatar (chữ đầu tên khách) thay cho cờ ngôn ngữ — ưu tiên hiện thông
                 tin khách hàng thay vì icon ngôn ngữ tĩnh (chưa đổi được locale thật, xem comment
                 cờ bên dưới). Hover vào hiện dropdown Thông tin/Sân đã đặt/Đăng xuất (cùng nội dung
                 với dropdown click ở components/header/actions/auth-button.blade.php — chỉ đổi
                 sang mở bằng hover theo đúng yêu cầu, không phải click, vì đây là top-bar mới). --}}
            <div x-show="isLoggedIn" x-cloak class="relative"
                 x-data="{ dropOpen: false }"
                 @mouseenter="dropOpen = true" @mouseleave="dropOpen = false">
                <button type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-xs font-extrabold shadow-sm transition hover:bg-white/90"
                        style="color:#0B6B3A;" x-text="initials"></button>

                <div x-show="dropOpen" x-cloak
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                     class="absolute right-0 top-full z-[1300] mt-1 w-52 rounded-2xl border border-gray-100 bg-white py-2 text-left shadow-lg shadow-black/10">
                    <div class="border-b border-gray-100 px-4 py-2.5">
                        <p class="truncate text-sm font-semibold text-gray-900" x-text="userName"></p>
                    </div>
                    <a href="/tai-khoan" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 hover:text-gray-900">
                        <x-heroicon-o-user-circle class="h-4 w-4 text-gray-400" />
                        Thông tin
                    </a>
                    <a href="/tai-khoan#orders-section" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 hover:text-gray-900">
                        <x-heroicon-o-clipboard-document-check class="h-4 w-4 text-gray-400" />
                        Sân đã đặt
                    </a>
                    <a href="{{ route('favorites.page') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 hover:text-gray-900">
                        <x-heroicon-o-heart class="h-4 w-4 text-gray-400" />
                        Yêu thích
                    </a>
                    <div class="my-1 border-t border-gray-100"></div>
                    <button type="button" @click="logout()"
                            class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm text-red-500 transition hover:bg-red-50">
                        <x-heroicon-o-arrow-right-on-rectangle class="h-4 w-4" />
                        Đăng xuất
                    </button>
                </div>
            </div>

            {{-- Cờ ngôn ngữ — CHỈ hiện khi CHƯA đăng nhập (đã đăng nhập thì avatar khách hàng ở
                 trên thay chỗ này). Chỉ hiển thị, chưa đổi được locale thật (site hiện chỉ có 1
                 ngôn ngữ). --}}
            <span x-show="!isLoggedIn" x-cloak class="flex h-8 w-8 items-center justify-center rounded-full bg-white text-base shadow-sm" title="Tiếng Việt">
                🇻🇳
            </span>
        </div>
    </div>
</div>
