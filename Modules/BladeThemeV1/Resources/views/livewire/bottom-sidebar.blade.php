{{-- resources/views/bladethemev1/components/bottom-navigation.blade.php --}}
@php
    $currentUrl = request()->getPathInfo();
@endphp
<style>
    .bottom-navigation-1 {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1000;
    }

    .bottom-nav-1 {
        background: white;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-around;
        align-items: flex-end;
        padding: 8px 0 12px 0;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    }

    .nav-item-1 {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex: 1;
        color: #999;
        transition: color 0.3s ease;
        padding: 4px 8px;
        cursor: pointer;
        text-decoration: none;
    }

    .nav-item-1:hover {
        color: var(--color-primary);
    }

    .nav-item-1.active {
        color: var(--color-primary);
    }

    .nav-icon-1 {
        width: 24px;
        height: 24px;
        margin-bottom: 4px;
        fill: currentColor;
        stroke: currentColor;
        stroke-width: 2;
    }

    .nav-label-1 {
        font-size: 10px;
        font-weight: 500;
        text-align: center;
        white-space: nowrap;
    }

    .nav-item-1.active .nav-icon-1 {
        transform: scale(1.1);
    }

    /* Nút "Khám phá" tròn nổi ở giữa, theo mẫu tham khảo */
    .nav-item-explore {
        margin-top: -26px;
    }

    .nav-item-explore .nav-icon-1 {
        width: 52px;
        height: 52px;
        margin-bottom: 2px;
        padding: 13px;
        border-radius: 9999px;
        background: linear-gradient(135deg, #0B6B3A 0%, #1FB868 100%);
        color: #fff;
        box-shadow: 0 4px 12px rgba(11, 107, 58, 0.45);
        border: 3px solid #fff;
    }

    .nav-item-explore .nav-label-1 {
        color: #0B6B3A;
        font-weight: 700;
    }

    body {
        padding-bottom: 80px;
    }

    @media (max-width: 480px) {
        .nav-label-1 {
            font-size: 9px;
        }
        .nav-icon-1 {
            width: 20px;
            height: 20px;
        }
        .nav-item-explore .nav-icon-1 {
            width: 46px;
            height: 46px;
            padding: 11px;
        }
    }

    @media (min-width: 1024px) {
        .bottom-navigation-1 {
            display: none;
        }
        body {
            padding-bottom: 0;
        }
    }
</style>

<div class="bottom-navigation-1">
    <div class="bottom-nav-1">
        <a href="/" class="nav-item-1 {{ $currentUrl === '/' ? 'active' : '' }}">
            <div class="nav-icon-1">
                <x-heroicon-o-home />
            </div>
            <span class="nav-label-1">Trang chủ</span>
        </a>

        <a href="{{ route('map.page') }}" class="nav-item-1 {{ $currentUrl === '/ban-do' ? 'active' : '' }}">
            <div class="nav-icon-1">
                <x-heroicon-o-map />
            </div>
            <span class="nav-label-1">Bản đồ</span>
        </a>

        <a href="{{ route('explore.page') }}" class="nav-item-1 nav-item-explore {{ $currentUrl === '/kham-pha' ? 'active' : '' }}">
            <div class="nav-icon-1">
                <x-heroicon-o-squares-2x2 />
            </div>
            <span class="nav-label-1">Khám phá</span>
        </a>

        {{-- "Nổi bật" trỏ route('posts.page') (trang tin tức/bài viết thật), KHỚP với menu desktop
             ở components/home/top-bar.blade.php — trước đây trỏ route('map.page')/product.search,
             đã đổi lại theo đúng yêu cầu người dùng (bug đã gặp: mobile và desktop lệch nhau). --}}
        <a href="{{ route('posts.page') }}" class="nav-item-1 {{ $currentUrl === '/tin-tuc' ? 'active' : '' }}">
            <div class="nav-icon-1">
                <x-heroicon-o-fire />
            </div>
            <span class="nav-label-1">Nổi bật</span>
        </a>

        {{-- Chưa đăng nhập: điều hướng sang trang /dang-nhap riêng (không còn mở modal popup —
             xem components/header/actions/auth-button.blade.php, đổi song song ở header desktop). --}}
        <div
            x-data="{
                isLoggedIn: false,
                init() {
                    this.check();
                    window.addEventListener('auth-state-changed', () => this.check());
                },
                check() {
                    this.isLoggedIn = !!(localStorage.getItem('auth_token') && localStorage.getItem('auth_user'));
                },
            }"
            class="contents"
        >
            <a :href="isLoggedIn ? '/tai-khoan' : '{{ route('login.page') }}'" class="nav-item-1 {{ $currentUrl === '/tai-khoan' ? 'active' : '' }}">
                <div class="nav-icon-1">
                    <x-heroicon-o-user-circle />
                </div>
                <span class="nav-label-1" x-text="isLoggedIn ? 'Tài khoản' : 'Đăng nhập'"></span>
            </a>
        </div>
    </div>
</div>
