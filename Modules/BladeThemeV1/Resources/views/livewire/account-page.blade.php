<div
    x-data="{
        logout() {
            const token = localStorage.getItem('auth_token');
            if (token) {
                fetch('/api/auth/logout', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
                }).catch(() => {});
            }
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.dispatchEvent(new CustomEvent('auth-state-changed'));
            window.location.href = '/';
        },
        openModal() {
            window.dispatchEvent(new CustomEvent('open-auth-modal'));
        },

        // ── Điều hướng trong trang: 'main' (tổng quan) | 'profile' (thông tin khách hàng) |
        // 'settings' (cài đặt tài khoản) | 'placeholder' (mục chưa có dữ liệu thật, hiện chữ Đang
        // cập nhật thay vì bịa nội dung — xem Nhóm của tôi/Danh sách lịch học/Gói hội viên/...). Dùng
        // Alpine state thay vì route riêng để tái sử dụng nguyên vẹn $wire đã load (orders,
        // discountCodes...) không phải gọi lại API khi chuyển qua lại.
        page: 'main',
        placeholderTitle: '',
        openPlaceholder(title) {
            this.placeholderTitle = title;
            this.page = 'placeholder';
        },

        // ── Profile Edit ───────────────────────────────────────────────────────
        editLoading: false,
        editError: '',
        editSuccess: '',
        editFullname: '',
        editDob: '',

        // CCCD upload state
        cccdFrontFile: null,
        cccdBackFile: null,
        cccdFrontPreview: null,
        cccdBackPreview: null,
        cccdLoading: false,
        cccdError: '',
        cccdSuccess: '',

        // Change password state
        pwFormOpen: false,
        pwLoading: false,
        pwError: '',
        pwSuccess: '',
        currentPw: '',
        newPw: '',
        newPwConfirm: '',
        showPw: false,

        // Liên kết email — 2 bước: nhập email + gửi mã (emailStep='enter'), rồi nhập mã 6 số +
        // mật khẩu tuỳ chọn cho đăng nhập bằng email (emailStep='verify'). Xem
        // ZaloOtpController::sendEmailVerificationCode()/verifyEmail().
        emailFormOpen: false,
        emailStep: 'enter',
        emailLoading: false,
        emailError: '',
        emailSuccess: '',
        newEmail: '',
        emailOtp: '',
        emailNewPassword: '',
        emailNewPasswordConfirm: '',
        currentEmail: '',

        // Nạp lại dữ liệu form chỉnh sửa hồ sơ từ localStorage.auth_user — gọi lúc mount/đổi trạng
        // thái auth VÀ mỗi lần vào lại trang Thông tin khách hàng để form luôn khớp dữ liệu mới
        // nhất (không còn là nội dung modal — trang riêng, xem page === profile bên dưới).
        openEdit() {
            const user = JSON.parse(localStorage.getItem('auth_user') || '{}');
            this.editFullname = user.fullname || '';
            // Convert DOB: 'YYYY-MM-DD' → 'YYYY-MM-DD' (input[type=date] dùng YYYY-MM-DD)
            if (user.date_of_birth) {
                const parts = user.date_of_birth.split('-');
                this.editDob = parts.length === 3 ? parts[0] + '-' + parts[1] + '-' + parts[2] : '';
            } else {
                this.editDob = '';
            }
            this.editError = '';
            this.editSuccess = '';
            this.cccdError = '';
            this.cccdSuccess = '';
            this.cccdFrontFile = null;
            this.cccdBackFile = null;
            this.cccdFrontPreview = null;
            this.cccdBackPreview = null;
            this.pwError = '';
            this.pwSuccess = '';
            this.currentPw = '';
            this.newPw = '';
            this.newPwConfirm = '';
            this.showPw = false;
            this.emailFormOpen = false;
            this.emailStep = 'enter';
            this.emailError = '';
            this.emailSuccess = '';
            this.newEmail = '';
            this.emailOtp = '';
            this.emailNewPassword = '';
            this.emailNewPasswordConfirm = '';
            this.currentEmail = user.email || '';
        },

        onCccdPick(event, side) {
            const file = event.target.files[0];
            if (!file) return;
            // Validate type & size (5MB)
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) {
                this.cccdError = 'Chỉ chấp nhận ảnh JPG, PNG, WEBP.';
                event.target.value = '';
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                this.cccdError = 'Ảnh không được vượt quá 5MB.';
                event.target.value = '';
                return;
            }
            this.cccdError = '';
            const reader = new FileReader();
            reader.onload = (e) => {
                if (side === 'front') { this.cccdFrontPreview = e.target.result; this.cccdFrontFile = file; }
                else                  { this.cccdBackPreview  = e.target.result; this.cccdBackFile  = file; }
            };
            reader.readAsDataURL(file);
        },

        async saveProfile() {
            this.editLoading = true;
            this.editError = '';
            this.editSuccess = '';
            const token = localStorage.getItem('auth_token');
            if (!token) { this.editLoading = false; return; }

            try {
                const fd = new FormData();
                if (this.editFullname.trim()) fd.append('fullname', this.editFullname.trim());
                if (this.editDob) {
                    // Convert YYYY-MM-DD → DD-MM-YYYY for API
                    const [y, m, d] = this.editDob.split('-');
                    fd.append('date_of_birth', d + '-' + m + '-' + y);
                }

                const res  = await fetch('/api/auth/me', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok) { this.editError = data.message || 'Có lỗi xảy ra.'; return; }

                localStorage.setItem('auth_user', JSON.stringify(data));
                this.editSuccess = 'Cập nhật thông tin thành công!';
                window.dispatchEvent(new CustomEvent('auth-state-changed'));
            } catch {
                this.editError = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.editLoading = false;
            }
        },

        async changePassword() {
            if (!this.currentPw || !this.newPw || !this.newPwConfirm) {
                this.pwError = 'Vui lòng điền đầy đủ thông tin.';
                return;
            }
            if (this.newPw.length < 8) {
                this.pwError = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
                return;
            }
            if (this.newPw !== this.newPwConfirm) {
                this.pwError = 'Xác nhận mật khẩu không khớp.';
                return;
            }
            this.pwLoading = true;
            this.pwError   = '';
            this.pwSuccess = '';
            const token = localStorage.getItem('auth_token');
            if (!token) { this.pwLoading = false; return; }
            try {
                const res  = await fetch('/api/auth/change-password', {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Authorization': 'Bearer ' + token,
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({
                        current_password:       this.currentPw,
                        password:               this.newPw,
                        password_confirmation:  this.newPwConfirm,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.pwError = data.errors?.current_password?.[0]
                        || data.errors?.password?.[0]
                        || data.message
                        || 'Có lỗi xảy ra.';
                    return;
                }
                this.pwSuccess    = 'Đổi mật khẩu thành công!';
                this.currentPw    = '';
                this.newPw        = '';
                this.newPwConfirm = '';
            } catch {
                this.pwError = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.pwLoading = false;
            }
        },

        async emailSendCode() {
            if (!this.newEmail) {
                this.emailError = 'Vui lòng nhập email.';
                return;
            }
            this.emailLoading = true;
            this.emailError   = '';
            this.emailSuccess = '';
            const token = localStorage.getItem('auth_token');
            if (!token) { this.emailLoading = false; return; }
            try {
                const res  = await fetch('/api/auth/email/send-code', {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Authorization': 'Bearer ' + token,
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({ email: this.newEmail }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.emailError = data.errors?.email?.[0] || data.message || 'Có lỗi xảy ra.';
                    return;
                }
                this.emailSuccess = data.message;
                this.emailStep    = 'verify';
            } catch {
                this.emailError = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.emailLoading = false;
            }
        },

        async emailVerify() {
            if (!this.emailOtp) {
                this.emailError = 'Vui lòng nhập mã xác thực.';
                return;
            }
            if (this.emailNewPassword && this.emailNewPassword !== this.emailNewPasswordConfirm) {
                this.emailError = 'Xác nhận mật khẩu không khớp.';
                return;
            }
            this.emailLoading = true;
            this.emailError   = '';
            this.emailSuccess = '';
            const token = localStorage.getItem('auth_token');
            if (!token) { this.emailLoading = false; return; }
            try {
                const res  = await fetch('/api/auth/email/verify', {
                    method:  'POST',
                    headers: {
                        'Content-Type':  'application/json',
                        'Authorization': 'Bearer ' + token,
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({
                        otp:                    this.emailOtp,
                        password:               this.emailNewPassword || undefined,
                        password_confirmation:  this.emailNewPasswordConfirm || undefined,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.emailError = data.errors?.otp?.[0]
                        || data.errors?.password?.[0]
                        || data.message
                        || 'Có lỗi xảy ra.';
                    return;
                }
                localStorage.setItem('auth_user', JSON.stringify(data.user));
                this.currentEmail = data.user.email || '';
                this.emailSuccess = 'Đã liên kết email thành công!';
                this.emailOtp     = '';
                this.emailNewPassword = '';
                this.emailNewPasswordConfirm = '';
                this.emailFormOpen = false;
                window.dispatchEvent(new CustomEvent('auth-state-changed'));
            } catch {
                this.emailError = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.emailLoading = false;
            }
        },

        async saveCccd() {
            if (!this.cccdFrontFile && !this.cccdBackFile) {
                this.cccdError = 'Vui lòng chọn ít nhất một ảnh CCCD.';
                return;
            }
            this.cccdLoading = true;
            this.cccdError   = '';
            this.cccdSuccess = '';
            const token = localStorage.getItem('auth_token');
            if (!token) { this.cccdLoading = false; return; }

            try {
                const fd = new FormData();
                if (this.cccdFrontFile) fd.append('cccd_front', this.cccdFrontFile);
                if (this.cccdBackFile)  fd.append('cccd_back',  this.cccdBackFile);

                const res  = await fetch('/api/auth/me', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok) { this.cccdError = data.message || 'Lỗi tải CCCD.'; return; }

                localStorage.setItem('auth_user', JSON.stringify(data));
                this.cccdSuccess = 'Cập nhật CCCD thành công!';
                window.dispatchEvent(new CustomEvent('auth-state-changed'));
            } catch {
                this.cccdError = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.cccdLoading = false;
            }
        },
    }"
    x-init="
        $nextTick(() => {
            const token = localStorage.getItem('auth_token');
            $wire.loadUserOrders(token || '');
            openEdit();
        });
        window.addEventListener('auth-state-changed', () => {
            const t = localStorage.getItem('auth_token');
            $wire.loadUserOrders(t || '');
            openEdit();
        });
        window.addEventListener('auth-force-logout', () => {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.dispatchEvent(new CustomEvent('auth-state-changed'));
        });
    "
    class="max-w-[1600px] mx-auto px-4 md:px-8 py-10 space-y-5"
>

    {{-- ── INITIAL LOADING ── --}}
    @if($isLoading)
    <div class="flex flex-col items-center justify-center py-24 gap-4">
        <svg class="animate-spin w-8 h-8 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <p class="text-sm text-gray-400">Đang tải tài khoản...</p>
    </div>

    {{-- ── NOT LOGGED IN ── --}}
    @elseif(!$isLoggedIn)
    <div class="flex flex-col items-center justify-center min-h-[55vh]  text-center py-16">
        <div class="w-20 h-20 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center mb-5 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-9 h-9 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-2">Bạn chưa đăng nhập</h2>
        <p class="text-gray-500 text-sm mb-8 max-w-xs leading-relaxed">
            Đăng nhập để xem thông tin tài khoản và lịch sử đặt sân của bạn.
        </p>
        <button
            @click="openModal()"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold shadow-sm transition-opacity hover:opacity-85"
            style="background-color: {{ $primaryHex }}; color: {{ $textOnPrimary }};"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
            </svg>
            Đăng nhập ngay
        </button>
    </div>

    {{-- ── LOGGED IN ── --}}
    @else

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

    {{-- ── LEFT: Sidebar tài khoản (giống ảnh mẫu) ── --}}
    <div class="lg:col-span-4 space-y-4">

        {{-- Profile Card --}}
        <button type="button" @click="openEdit(); page = 'profile'"
            class="w-full flex items-center gap-4 bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-left hover:shadow-md transition-shadow">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-base font-bold shrink-0 text-white"
                 style="background: linear-gradient(135deg, #0B6B3A 0%, #1FB868 100%);">
                @php
                    $parts = array_filter(explode(' ', trim($fullname)));
                    $initials = count($parts) >= 2
                        ? mb_strtoupper(mb_substr($parts[array_key_last($parts) - 1] ?? '', 0, 1) . mb_substr(end($parts), 0, 1))
                        : mb_strtoupper(mb_substr($fullname, 0, 2));
                @endphp
                {{ $initials ?: '?' }}
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-bold text-gray-900 truncate">{{ $fullname }}</h1>
                <p class="text-sm text-gray-500 truncate mt-0.5">{{ $phone ?: 'Chưa cập nhật SĐT' }}</p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
            </svg>
        </button>

        {{-- Hạng thành viên + 4 lối tắt --}}
        <div class="relative rounded-2xl overflow-hidden p-5 text-white shadow-sm"
             style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);
                    @if($membershipTierCardImg) background-image: linear-gradient(160deg, rgba(0,0,0,.25) 0%, rgba(0,0,0,.55) 100%), url('{{ $membershipTierCardImg }}'); background-size: cover; background-position: center; @endif">
            <p class="text-xs font-semibold uppercase tracking-wider opacity-80">Hạng thành viên</p>
            <h2 class="text-xl font-extrabold uppercase mt-1 tracking-wide">{{ $membershipTierName ?? 'Thành viên' }}</h2>

            @if($membershipTierName)
            <div class="mt-3 h-1.5 rounded-full bg-white/25 overflow-hidden">
                <div class="h-full rounded-full bg-white transition-all" style="width: {{ $tierProgressPercent }}%"></div>
            </div>
            <p class="mt-2 text-xs opacity-90">
                @if($nextTierName)
                    Còn <span class="font-bold">{{ number_format($amountToNextTier, 0, ',', '.') }}đ</span> để lên hạng <span class="font-bold">{{ $nextTierName }}</span>
                @else
                    Bạn đang ở hạng thành viên cao nhất 🎉
                @endif
            </p>
            @endif

            <div class="grid grid-cols-4 gap-2 mt-4">
                <a href="#orders-section" class="flex flex-col items-center gap-1.5 text-center">
                    <span class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                        </svg>
                    </span>
                    <span class="text-[11px] font-medium leading-tight">Lịch đã đặt</span>
                </a>
                <button type="button" @click="openPlaceholder('Thông báo')" class="flex flex-col items-center gap-1.5 text-center">
                    <span class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                    </span>
                    <span class="text-[11px] font-medium leading-tight">Thông báo</span>
                </button>
                <button type="button" @click="openPlaceholder('Khoá học')" class="flex flex-col items-center gap-1.5 text-center opacity-90">
                    <span class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                        </svg>
                    </span>
                    <span class="text-[11px] font-medium leading-tight">Khoá học</span>
                </button>
                <a href="#orders-section" @click="accountTab = 'coupons'" class="flex flex-col items-center gap-1.5 text-center">
                    <span class="w-11 h-11 rounded-xl bg-white/15 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m-6 0V6a3 3 0 013-3v0a3 3 0 013 3v2.25m-6 0h6m-9 3.75h.008v.008H6v-.008zm0 3h.008v.008H6v-.008z"/>
                        </svg>
                    </span>
                    <span class="text-[11px] font-medium leading-tight">Ưu đãi</span>
                </a>
            </div>
        </div>

        {{-- Hoạt động --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <p class="px-4 pt-4 pb-2 text-xs font-bold text-gray-400 uppercase tracking-wide">Hoạt động</p>
            <div class="divide-y divide-gray-50">
                @foreach ([
                    ['label' => 'Danh sách lịch học', 'icon' => 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25'],
                ] as $item)
                <button type="button" @click="openPlaceholder('{{ $item['label'] }}')"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">{{ $item['label'] }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                @endforeach

                {{-- "Nhóm của tôi" — trước đây là mục placeholder chung, giờ có dữ liệu thật (quỹ
                     nhóm góp tiền đặt sân, App\Models\BookingFund) nên tách trang riêng (page:
                     'funds') đúng cách làm với "Gói của tôi" ở trên. --}}
                <button type="button" @click="page = 'funds'"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Nhóm của tôi</span>
                    @if (count($ledFunds) + count($joinedFunds) > 0)
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">{{ count($ledFunds) + count($joinedFunds) }}</span>
                    @endif
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>

                {{-- "Gói hội viên" — trước đây là mục placeholder "Đang cập nhật" chung với các mục
                     chưa có dữ liệu thật. Có DỮ LIỆU THẬT rồi (CustomerPackage, xem
                     AccountPage::loadPackages()) nên tách ra trang RIÊNG (page: 'packages') thay vì
                     dùng chung placeholder — theo yêu cầu: quản lý gói + lịch sử dùng gói không
                     gộp chung với trang đặt sân. --}}
                <button type="button" @click="page = 'packages'"
                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Gói của tôi</span>
                    @if (count($packages) > 0)
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 border border-emerald-200 rounded-full px-2 py-0.5">{{ count($packages) }}</span>
                    @endif
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Hệ thống --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <p class="px-4 pt-4 pb-2 text-xs font-bold text-gray-400 uppercase tracking-wide">Hệ thống</p>
            <div class="divide-y divide-gray-50">
                <button type="button" @click="openEdit(); page = 'settings'" class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.992l1.005.828c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.397-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.992l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Cài đặt</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <div class="w-full flex items-center gap-3 px-4 py-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Thông tin phiên bản</span>
                    <span class="text-xs text-gray-400">365Sport Web</span>
                </div>
                <button type="button" @click="openPlaceholder('Điều khoản và chính sách')" class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 12h-9m9-3h-9m3.75 6h-3.75M15 12h-.008v.008H15V12zm-3-3h.008v.008H12V9z"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Điều khoản và chính sách</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <button type="button" @click="openPlaceholder('Ứng dụng có gì mới')" class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Ứng dụng có gì mới</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
                <button type="button" @click="logout()" class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-red-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-red-500">Đăng xuất</span>
                </button>
            </div>
        </div>

        <p class="text-center text-[11px] text-gray-400 pb-2">Thông tin phiên bản: 365Sport Web</p>
    </div>

    {{-- ── RIGHT: nội dung đổi theo "page" (Alpine state ở root x-data) — sidebar bên trái GIỮ
         NGUYÊN, chỉ cột phải đổi giữa Danh sách đặt lịch / Thông tin khách hàng / Cài đặt / trang
         "Đang cập nhật" (cho các mục chưa có dữ liệu thật), giống đúng ảnh mẫu trang Cài đặt (vẫn
         thấy sidebar trái khi xem Cài đặt). ── --}}
    <div class="lg:col-span-8">
    <div id="orders-section" x-data="{ accountTab: 'orders' }" x-show="page === 'main'" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        {{-- Header xanh giống ảnh mẫu --}}
        <div class="flex items-center justify-between gap-3 px-5 py-4 text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
            <h2 class="text-base font-bold">Danh sách đặt lịch</h2>
            <button type="button" wire:click="filterBranch(null)"
                class="inline-flex items-center gap-1.5 text-xs font-semibold bg-white/15 hover:bg-white/25 transition-colors px-3 py-1.5 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                </svg>
                Xem tất cả
            </button>
        </div>

        {{-- Tabs --}}
        @if(count($discountCodes) > 0)
        <div class="flex border-b border-gray-100">
            <button
                @click="accountTab = 'orders'"
                class="flex-1 px-4 py-3 text-sm font-semibold text-center transition-colors border-b-2"
                :class="accountTab === 'orders' ? 'border-[#0B6B3A] text-[#0B6B3A]' : 'border-transparent text-gray-400 hover:text-gray-600'"
            >
                Đặt lịch
                <span class="ml-1 font-normal text-gray-400">({{ $totalCount }})</span>
            </button>
            <button
                @click="accountTab = 'coupons'"
                class="flex-1 px-4 py-3 text-sm font-semibold text-center transition-colors border-b-2"
                :class="accountTab === 'coupons' ? 'border-[#0B6B3A] text-[#0B6B3A]' : 'border-transparent text-gray-400 hover:text-gray-600'"
            >
                Mã giảm giá
                <span class="ml-1 font-normal text-gray-400">({{ count($discountCodes) }})</span>
            </button>
        </div>
        @endif

        {{-- Tab: Mã giảm giá --}}
        @if(count($discountCodes) > 0)
        <div x-show="accountTab === 'coupons'" x-cloak class="divide-y divide-gray-50">
            @foreach($discountCodes as $code)
            <div class="flex items-center gap-4 px-5 py-4" x-data="{ copied: false }">
                <div class="shrink-0 w-[92px] text-xl font-extrabold leading-tight" style="color: {{ $primaryHex }};">
                    {{ $code['value_label'] }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $code['name'] ?? 'Mã giảm giá' }}</p>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium border {{ $code['status']['class'] }}">
                            {{ $code['status']['label'] }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 font-mono mt-0.5">{{ $code['code'] }}</p>
                    @if($code['min_order_value'] || $code['end_at'])
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        @if($code['min_order_value'])
                            Đơn tối thiểu {{ number_format((float) $code['min_order_value'], 0, ',', '.') }}đ
                        @endif
                        @if($code['min_order_value'] && $code['end_at']) &middot; @endif
                        @if($code['end_at'])
                            HSD: {{ $code['end_at'] }}
                        @endif
                    </p>
                    @endif
                </div>

                <button
                    @click="navigator.clipboard.writeText('{{ $code['code'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                    class="shrink-0 w-10 h-10 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:border-gray-300 transition-colors"
                    title="Sao chép mã"
                >
                    <svg x-show="!copied" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124M15.75 17.25h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75"/>
                    </svg>
                    <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                </button>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Tab: Lịch sử đặt sân --}}
        <div x-show="accountTab === 'orders'">

        {{-- Empty state --}}
        @if(count($orders) === 0)
        <div class="py-14 flex flex-col items-center gap-3 text-center px-6">
            <div class="w-16 h-16 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mb-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                </svg>
            </div>
            <p class="text-gray-700 font-semibold">Chưa có đơn đặt sân nào</p>
            <p class="text-gray-400 text-sm">Hãy khám phá và đặt sân ngay hôm nay!</p>
            <a href="/"
               class="mt-3 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold shadow-sm transition-opacity hover:opacity-85"
               style="background-color: {{ $primaryHex }}; color: {{ $textOnPrimary }};">
                Khám phá sân
            </a>
        </div>

        {{-- Order list — bố cục theo ảnh mẫu: ribbon "Đơn ngày", tên chi nhánh màu cam,
             "Chi tiết: {sân}: {khung giờ} | Ngày {ngày}", "Địa chỉ: {địa chỉ}". --}}
        @else
        <div class="divide-y divide-gray-50">
            @foreach($orders as $order)
            @php
                $statusConfig = match($order['status']) {
                    'paid'               => ['label' => 'Đã thanh toán', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                    'deposit'            => ['label' => 'Đã cọc',        'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                    'pending'            => ['label' => 'Chờ thanh toán','class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                    'failed'             => ['label' => 'Thất bại',       'class' => 'bg-red-50 text-red-700 border-red-200'],
                    'cancelled_payment'  => ['label' => 'Đã hủy',         'class' => 'bg-gray-100 text-gray-600 border-gray-200'],
                    default              => ['label' => ucfirst($order['status']), 'class' => 'bg-gray-100 text-gray-600 border-gray-200'],
                };
                // Khách tự huỷ (cancelled_at) đè lên nhãn trạng thái thanh toán gốc — xem
                // Order::isCancelled()/AccountPage::fetchPage().
                if ($order['is_cancelled']) {
                    $statusConfig = ['label' => 'Đã huỷ', 'class' => 'bg-gray-100 text-gray-600 border-gray-200'];
                }
            @endphp
            <div class="p-4 sm:p-5 hover:bg-gray-50/60 transition-colors">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wide"
                          style="background-color: #0B6B3A18; color: #0B6B3A;">
                        {{ $order['order_type_label'] }}
                    </span>
                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $statusConfig['class'] }}">
                        {{ $statusConfig['label'] }}
                    </span>
                </div>

                <div class="flex items-start gap-3">
                    {{-- Thumbnail --}}
                    <div class="w-14 h-14 rounded-xl overflow-hidden shrink-0 bg-gray-100 border border-gray-100">
                        @if($order['thumbnail'])
                            <img src="{{ $order['thumbnail'] }}" alt="{{ $order['venue_name'] }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-[#0B6B3A0d]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#0B6B3A80]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205l3 1m1.5.5l-1.5-.5M6.75 7.364V3h-3v18m3-13.636l10.5-3.819"/>
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <a href="/thong-tin-dat-phong/{{ $order['order_code'] }}" class="font-bold text-sm leading-snug hover:underline" style="color: #F5A623;">
                            {{ $order['venue_name'] }}
                        </a>
                        <p class="text-xs text-gray-500 mt-1">
                            Chi tiết: {{ $order['court_name'] }}{{ $order['time_label'] ? ': ' . $order['time_label'] : '' }}
                            @if($order['date_label']) | Ngày {{ $order['date_label'] }} @endif
                        </p>
                        @if($order['address'])
                        <p class="text-xs text-gray-500 mt-0.5">Địa chỉ: {{ $order['address'] }}</p>
                        @endif
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50">
                    <span class="text-sm font-bold" style="color: {{ $primaryHex }};">
                        {{ number_format($order['amount'], 0, ',', '.') }}đ
                    </span>
                    <div class="flex items-center gap-1">
                        {{-- Khách tự huỷ đơn/xin hoàn tiền — xem AccountPage::cancelOrder() /
                             Order::canBeCancelledByCustomer(). wire:confirm dùng dialog xác
                             nhận gốc của trình duyệt (Livewire 3), đủ dùng cho thao tác 1 lần,
                             không cần dựng modal riêng như trang tra cứu đơn (Blade thuần). --}}
                        @if($order['can_cancel'])
                        <button
                            type="button"
                            wire:click="cancelOrder({{ $order['id'] }})"
                            wire:confirm="Huỷ đơn {{ $order['order_code'] }}? Bạn sẽ được hoàn {{ number_format($order['cancel_refund_amount'], 0, ',', '.') }}đ (70% đã trừ phí xử lý). Không thể hoàn tác."
                            class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700 transition-colors px-2.5 py-1.5 rounded-lg hover:bg-red-50"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Huỷ đơn
                        </button>
                        @endif
                        <a
                            href="/thong-tin-dat-phong/{{ $order['order_code'] }}"
                            class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-gray-800 transition-colors px-2.5 py-1.5 rounded-lg hover:bg-gray-100"
                        >
                            Xem chi tiết
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($totalPages > 1)
        @php $tp = $totalPages; @endphp
        <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between gap-3">
            {{-- Info --}}
            <span class="text-xs text-gray-400">
                {{ ($currentPage - 1) * $perPage + 1 }}–{{ min($currentPage * $perPage, $totalCount) }}
                / {{ $totalCount }} đơn
            </span>

            {{-- Controls --}}
            <div class="flex items-center gap-1">
                {{-- Prev --}}
                <button
                    wire:click="goToPage({{ $currentPage - 1 }})"
                    @class(['p-1.5 rounded-lg text-gray-400 transition-colors', 'opacity-30 cursor-not-allowed' => $currentPage === 1, 'hover:bg-gray-100 hover:text-gray-700' => $currentPage > 1])
                    @disabled($currentPage === 1)
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                    </svg>
                </button>

                {{-- Page numbers --}}
                @for($p = 1; $p <= $tp; $p++)
                    @if($p === 1 || $p === $tp || abs($p - $currentPage) <= 1)
                    <button
                        wire:click="goToPage({{ $p }})"
                        class="min-w-[30px] h-[30px] rounded-lg text-xs font-medium transition-colors"
                        style="{{ $p === $currentPage ? 'background-color:' . $primaryHex . '; color:' . $textOnPrimary . ';' : 'color:#6b7280;' }}"
                        @class(['hover:bg-gray-100' => $p !== $currentPage])
                    >{{ $p }}</button>
                    @elseif(abs($p - $currentPage) === 2)
                    <span class="text-xs text-gray-300 px-0.5">…</span>
                    @endif
                @endfor

                {{-- Next --}}
                <button
                    wire:click="goToPage({{ $currentPage + 1 }})"
                    @class(['p-1.5 rounded-lg text-gray-400 transition-colors', 'opacity-30 cursor-not-allowed' => $currentPage === $tp, 'hover:bg-gray-100 hover:text-gray-700' => $currentPage < $tp])
                    @disabled($currentPage === $tp)
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>
            </div>
        </div>
        @endif
        @endif
        </div>{{-- /tab: orders --}}
    </div>

    {{-- ── Cột phải: TRANG "THÔNG TIN KHÁCH HÀNG" ── --}}
    <div x-show="page === 'profile'" x-cloak x-data="{ profileTab: 'overview' }" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
            <button type="button" @click="page = 'main'" class="w-8 h-8 rounded-full flex items-center justify-center bg-white/15 hover:bg-white/25 transition-colors shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="text-base font-bold">Thông tin khách hàng</h2>
        </div>

        <div class="p-5 space-y-5">
            {{-- Header hồ sơ --}}
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-lg font-bold shrink-0 text-white"
                     style="background: linear-gradient(135deg, #0B6B3A 0%, #1FB868 100%);">
                    {{ $initials ?: '?' }}
                </div>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-gray-900 truncate">{{ $fullname }}</h3>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $phone ?: 'Chưa cập nhật SĐT' }}</p>
                    @if($dob)
                    <p class="text-sm text-gray-500">Năm sinh: {{ \Carbon\Carbon::parse($dob)->format('Y') }}</p>
                    @endif
                </div>
            </div>

            {{-- Tabs --}}
            <div class="flex bg-gray-50 rounded-full border border-gray-100 p-1">
                <button type="button" @click="profileTab = 'overview'"
                    class="flex-1 py-2 rounded-full text-sm font-semibold transition-colors"
                    :class="profileTab === 'overview' ? 'bg-[#0B6B3A] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    Tổng quan
                </button>
                <button type="button" @click="profileTab = 'links'"
                    class="flex-1 py-2 rounded-full text-sm font-semibold transition-colors"
                    :class="profileTab === 'links' ? 'bg-[#0B6B3A] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'">
                    Liên kết
                </button>
            </div>

            {{-- Tab: Liên kết — chưa có tính năng liên kết tài khoản mạng xã hội thật --}}
            <div x-show="profileTab === 'links'" x-cloak class="py-12 text-center text-sm text-gray-400">
                Đang cập nhật
            </div>

            {{-- Tab: Tổng quan — chỉnh sửa hồ sơ + CCCD (dữ liệu/logic thật, không đổi) --}}
            <div x-show="profileTab === 'overview'" class="space-y-5">
                {{-- Thông tin cơ bản --}}
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Thông tin cá nhân</h3>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Họ và tên</label>
                        <input x-model="editFullname" type="text" placeholder="Nguyễn Văn A"
                            class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                            style="--tw-ring-color: {{ $primaryHex }}40;">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Ngày sinh</label>
                        <input x-model="editDob" type="date"
                            class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                            style="--tw-ring-color: {{ $primaryHex }}40;">
                    </div>

                    <div x-show="editError" x-cloak class="text-sm text-red-500 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <span x-text="editError"></span>
                    </div>
                    <div x-show="editSuccess" x-cloak class="text-sm text-green-600 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-text="editSuccess"></span>
                    </div>

                    <button @click="saveProfile()" :disabled="editLoading"
                        class="w-full rounded-lg py-2.5 text-sm font-semibold flex items-center justify-center gap-2 transition-opacity hover:opacity-85 disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background-color: {{ $primaryHex }}; color: {{ $textOnPrimary }};">
                        <svg x-show="editLoading" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span x-text="editLoading ? 'Đang lưu...' : 'Lưu thông tin'"></span>
                    </button>
                </div>

                <hr class="border-gray-100">

                {{-- CCCD Upload --}}
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                        Căn cước công dân
                        @if($hasCccd)
                        <span class="text-xs font-medium text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full">Đã có</span>
                        @else
                        <span class="text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">Chưa có</span>
                        @endif
                    </h3>

                    @if($hasCccd)
                    <div class="flex gap-2">
                        <div class="flex-1 rounded-xl overflow-hidden border border-gray-200 aspect-video">
                            <img src="{{ $cccdFrontUrl }}" alt="Mặt trước" class="w-full h-full object-cover">
                        </div>
                        <div class="flex-1 rounded-xl overflow-hidden border border-gray-200 aspect-video">
                            <img src="{{ $cccdBackUrl }}" alt="Mặt sau" class="w-full h-full object-cover">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">Muốn cập nhật CCCD? Chọn ảnh mới bên dưới.</p>
                    @endif

                    {{-- Upload areas --}}
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(['front' => 'Mặt trước', 'back' => 'Mặt sau'] as $side => $label)
                        <label class="relative flex flex-col items-center justify-center h-28 rounded-xl border-2 border-dashed border-gray-300 cursor-pointer overflow-hidden hover:border-gray-400 hover:bg-gray-50 transition-all">
                            <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp"
                                @change="onCccdPick($event, '{{ $side }}')">

                            {{-- Preview --}}
                            <template x-if="{{ $side === 'front' ? 'cccdFrontPreview' : 'cccdBackPreview' }}">
                                <img :src="{{ $side === 'front' ? 'cccdFrontPreview' : 'cccdBackPreview' }}"
                                    class="absolute inset-0 w-full h-full object-cover rounded-xl">
                            </template>

                            {{-- Placeholder --}}
                            <template x-if="!{{ $side === 'front' ? 'cccdFrontPreview' : 'cccdBackPreview' }}">
                                <div class="flex flex-col items-center gap-1 text-gray-400 pointer-events-none">
                                    <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                    </svg>
                                    <span class="text-xs font-medium">{{ $label }}</span>
                                </div>
                            </template>

                            {{-- Hover overlay when has preview --}}
                            <template x-if="{{ $side === 'front' ? 'cccdFrontPreview' : 'cccdBackPreview' }}">
                                <div class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                                    <span class="text-white text-xs font-semibold">Đổi ảnh</span>
                                </div>
                            </template>
                        </label>
                        @endforeach
                    </div>

                    <div x-show="cccdError" x-cloak class="text-sm text-red-500 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <span x-text="cccdError"></span>
                    </div>
                    <div x-show="cccdSuccess" x-cloak class="text-sm text-green-600 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-text="cccdSuccess"></span>
                    </div>

                    <p class="text-xs text-gray-400 leading-relaxed">
                        * Ảnh CCCD phải rõ nét, có mã QR đọc được. Chấp nhận JPG, PNG, WEBP (tối đa 5MB mỗi ảnh).
                    </p>

                    <button @click="saveCccd()" :disabled="cccdLoading || (!cccdFrontFile && !cccdBackFile)"
                        class="w-full rounded-lg py-2.5 text-sm font-semibold flex items-center justify-center gap-2 transition-opacity hover:opacity-85 disabled:opacity-40 disabled:cursor-not-allowed border-2"
                        style="border-color: {{ $primaryHex }}; color: {{ $primaryHex }};">
                        <svg x-show="cccdLoading" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span x-text="cccdLoading ? 'Đang tải lên...' : 'Cập nhật CCCD'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>{{-- /trang thông tin khách hàng --}}

    {{-- ── Cột phải: TRANG "CÀI ĐẶT" ── --}}
    <div x-show="page === 'settings'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
            <button type="button" @click="page = 'main'" class="w-8 h-8 rounded-full flex items-center justify-center bg-white/15 hover:bg-white/25 transition-colors shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="text-base font-bold">Cài đặt</h2>
        </div>

        <div class="divide-y divide-gray-50">
            <button type="button" @click="openPlaceholder('Cài đặt thông báo')" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                </svg>
                <span class="flex-1 text-sm font-medium text-gray-700">Cài đặt thông báo</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                </svg>
            </button>

            <button type="button" @click="openPlaceholder('Ngôn ngữ')" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M11.5 3a17 17 0 000 18M12.5 3a17 17 0 010 18"/>
                </svg>
                <span class="flex-1 text-sm font-medium text-gray-700">Ngôn ngữ</span>
                <span class="text-xs text-gray-400">Tiếng Việt</span>
            </button>

            {{-- Đổi mật khẩu — logic thật (changePassword()), chỉ đổi cách hiển thị: bấm để mở
                 rộng form ngay trong danh sách thay vì mở modal riêng. --}}
            <div>
                <button type="button" @click="pwFormOpen = !pwFormOpen" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Đổi mật khẩu</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0 transition-transform" :class="pwFormOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>

                <div x-show="pwFormOpen" x-cloak class="px-5 pb-5 space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Mật khẩu hiện tại</label>
                        <div class="relative">
                            <input x-model="currentPw" :type="showPw ? 'text' : 'password'"
                                placeholder="Mật khẩu hiện tại"
                                class="w-full rounded-lg px-3 py-2.5 pr-10 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                                style="--tw-ring-color: {{ $primaryHex }}40;">
                            <button type="button" @click="showPw = !showPw"
                                class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                                <svg x-show="!showPw" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <svg x-show="showPw" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Mật khẩu mới</label>
                        <input x-model="newPw" :type="showPw ? 'text' : 'password'"
                            placeholder="Ít nhất 8 ký tự"
                            class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                            style="--tw-ring-color: {{ $primaryHex }}40;">
                    </div>

                    <div x-show="newPw" x-cloak>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Xác nhận mật khẩu mới</label>
                        <input x-model="newPwConfirm" :type="showPw ? 'text' : 'password'"
                            placeholder="Nhập lại mật khẩu mới"
                            class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                            style="--tw-ring-color: {{ $primaryHex }}40;">
                    </div>

                    <div x-show="pwError" x-cloak class="text-sm text-red-500 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <span x-text="pwError"></span>
                    </div>
                    <div x-show="pwSuccess" x-cloak class="text-sm text-green-600 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-text="pwSuccess"></span>
                    </div>

                    <button @click="changePassword()" :disabled="pwLoading || !currentPw || !newPw"
                        class="w-full rounded-lg py-2.5 text-sm font-semibold flex items-center justify-center gap-2 transition-opacity hover:opacity-85 disabled:opacity-40 disabled:cursor-not-allowed border-2"
                        style="border-color: {{ $primaryHex }}; color: {{ $primaryHex }};">
                        <svg x-show="pwLoading" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                        <span x-text="pwLoading ? 'Đang xử lý...' : 'Đổi mật khẩu'"></span>
                    </button>
                </div>
            </div>

            {{-- Liên kết email — dùng để đăng nhập bằng email/mật khẩu thay vì OTP điện thoại.
                 2 bước: nhập email (emailStep='enter') → nhập mã 6 số gửi qua mail + đặt mật khẩu
                 tuỳ chọn (emailStep='verify'). Xem emailSendCode()/emailVerify() ở trên. --}}
            <div>
                <button type="button" @click="emailFormOpen = !emailFormOpen" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                    </svg>
                    <span class="flex-1 text-sm font-medium text-gray-700">Liên kết email</span>
                    <span class="text-xs text-gray-400" x-text="currentEmail || 'Chưa liên kết'"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300 shrink-0 transition-transform" :class="emailFormOpen ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                    </svg>
                </button>

                <div x-show="emailFormOpen" x-cloak class="px-5 pb-5 space-y-3">
                    <template x-if="emailStep === 'enter'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
                                <input x-model="newEmail" type="email"
                                    placeholder="email@example.com"
                                    class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                                    style="--tw-ring-color: {{ $primaryHex }}40;">
                            </div>

                            <button @click="emailSendCode()" :disabled="emailLoading || !newEmail"
                                class="w-full rounded-lg py-2.5 text-sm font-semibold flex items-center justify-center gap-2 transition-opacity hover:opacity-85 disabled:opacity-40 disabled:cursor-not-allowed border-2"
                                style="border-color: {{ $primaryHex }}; color: {{ $primaryHex }};">
                                <svg x-show="emailLoading" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                                <span x-text="emailLoading ? 'Đang gửi...' : 'Gửi mã xác thực'"></span>
                            </button>
                        </div>
                    </template>

                    <template x-if="emailStep === 'verify'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Mã xác thực (đã gửi tới <span x-text="newEmail"></span>)</label>
                                <input x-model="emailOtp" type="text" inputmode="numeric" maxlength="6"
                                    placeholder="Nhập mã 6 số"
                                    class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                                    style="--tw-ring-color: {{ $primaryHex }}40;">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Đặt mật khẩu đăng nhập bằng email (tuỳ chọn)</label>
                                <input x-model="emailNewPassword" type="password"
                                    placeholder="Ít nhất 8 ký tự"
                                    class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                                    style="--tw-ring-color: {{ $primaryHex }}40;">
                            </div>

                            <div x-show="emailNewPassword" x-cloak>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Xác nhận mật khẩu</label>
                                <input x-model="emailNewPasswordConfirm" type="password"
                                    placeholder="Nhập lại mật khẩu"
                                    class="w-full rounded-lg px-3 py-2.5 text-sm text-gray-900 bg-gray-50 border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                                    style="--tw-ring-color: {{ $primaryHex }}40;">
                            </div>

                            <button @click="emailVerify()" :disabled="emailLoading || !emailOtp"
                                class="w-full rounded-lg py-2.5 text-sm font-semibold flex items-center justify-center gap-2 transition-opacity hover:opacity-85 disabled:opacity-40 disabled:cursor-not-allowed border-2"
                                style="border-color: {{ $primaryHex }}; color: {{ $primaryHex }};">
                                <svg x-show="emailLoading" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                                <span x-text="emailLoading ? 'Đang xác thực...' : 'Xác nhận'"></span>
                            </button>
                        </div>
                    </template>

                    <div x-show="emailError" x-cloak class="text-sm text-red-500 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        <span x-text="emailError"></span>
                    </div>
                    <div x-show="emailSuccess" x-cloak class="text-sm text-green-600 flex items-center gap-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span x-text="emailSuccess"></span>
                    </div>
                </div>
            </div>

            <button type="button" @click="logout()" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-gray-50 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/>
                </svg>
                <span class="flex-1 text-sm font-medium text-gray-700">Đăng xuất tài khoản</span>
            </button>

            <button type="button" @click="openPlaceholder('Xoá tài khoản')" class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-red-50 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                </svg>
                <span class="flex-1 text-sm font-medium text-red-500">Xoá tài khoản</span>
            </button>
        </div>
    </div>{{-- /trang cài đặt --}}

    {{-- ── Cột phải: TRANG "NHÓM CỦA TÔI" — quỹ nhóm góp tiền đặt sân (App\Models\BookingFund).
         Trước đây "Xem quỹ"/"Tạo quỹ" điều hướng ra route riêng /quy-nhom/* (đẩy khách ra khỏi
         trang tài khoản) — theo yêu cầu, nhúng NGAY vào khung này qua $fundsView ('list'/'detail'/
         'create', xem AccountPage::viewFund()/startCreateFund()/backToFundsList()), dùng CHUNG
         2 component Livewire CreateFund/FundDetail với trang riêng (cờ embedded=true đổi layout,
         xem 2 file đó) — route /quy-nhom/* VẪN GIỮ nguyên cho link mời chia sẻ ra ngoài app. ── --}}
    <div x-show="page === 'funds'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
            <button type="button"
                @click="if ({{ $fundsView === 'list' ? 'true' : 'false' }}) { page = 'main'; } else { $wire.backToFundsList(); }"
                class="w-8 h-8 rounded-full flex items-center justify-center bg-white/15 hover:bg-white/25 transition-colors shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="text-base font-bold flex-1">Nhóm của tôi</h2>
            @if ($fundsView === 'list')
                <button type="button" wire:click="startCreateFund" class="text-xs font-semibold bg-white/15 hover:bg-white/25 transition-colors rounded-full px-3 py-1.5 shrink-0">+ Tạo quỹ</button>
            @endif
        </div>

        @if ($fundsView === 'create')
            @livewire('bladethemev1::booking-fund.create-fund', ['embedded' => true], key('embedded-create-fund'))

        @elseif ($fundsView === 'detail')
            @livewire('bladethemev1::booking-fund.fund-detail', ['code' => $viewingFundCode, 'embedded' => true], key('embedded-fund-'.$viewingFundCode))

        @else
        <div class="p-5 space-y-5">
            {{-- Tìm quỹ bằng mã mời --}}
            <div x-data="{ joinCode: '' }" class="flex items-center gap-2">
                <input type="text" x-model="joinCode" placeholder="Nhập mã mời quỹ nhóm..."
                       class="flex-1 rounded-lg border border-gray-200 px-3 py-2.5 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-emerald-400">
                <button type="button" @click="if (joinCode.trim()) $wire.viewFund(joinCode.trim())"
                    class="shrink-0 text-sm font-semibold px-4 py-2.5 rounded-lg bg-gray-900 text-white hover:opacity-90">Tìm</button>
            </div>

            @if (count($ledFunds) > 0)
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Bạn làm trưởng nhóm</p>
                    <div class="space-y-2">
                        @foreach ($ledFunds as $f)
                            <button type="button" wire:click="viewFund('{{ $f['invite_code'] }}')"
                               class="w-full text-left block rounded-xl border border-gray-100 p-3 hover:bg-gray-50" wire:key="led-{{ $f['invite_code'] }}">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $f['title'] }}</p>
                                    @if ($f['status'] === 'closed')
                                        <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200">Đã đóng</span>
                                    @endif
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-emerald-500" style="width: {{ $f['progress'] }}%;"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">{{ number_format($f['collected'], 0, ',', '.') }}đ / {{ number_format($f['target_amount'], 0, ',', '.') }}đ</p>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (count($joinedFunds) > 0)
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Quỹ bạn đã tham gia</p>
                    <div class="space-y-2">
                        @foreach ($joinedFunds as $f)
                            <button type="button" wire:click="viewFund('{{ $f['invite_code'] }}')"
                               class="w-full text-left block rounded-xl border border-gray-100 p-3 hover:bg-gray-50" wire:key="joined-{{ $f['invite_code'] }}">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $f['title'] }}</p>
                                    @if ($f['status'] === 'closed')
                                        <span class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 border border-gray-200">Đã đóng</span>
                                    @endif
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full bg-emerald-500" style="width: {{ $f['progress'] }}%;"></div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">{{ number_format($f['collected'], 0, ',', '.') }}đ / {{ number_format($f['target_amount'], 0, ',', '.') }}đ</p>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (count($ledFunds) === 0 && count($joinedFunds) === 0)
                <div class="py-10 flex flex-col items-center gap-2 text-center">
                    <p class="text-gray-700 font-semibold text-sm">Bạn chưa có quỹ nhóm nào</p>
                    <p class="text-gray-400 text-xs">Tạo quỹ mới hoặc nhập mã mời để tham gia quỹ của bạn bè.</p>
                </div>
            @endif
        </div>
        @endif
    </div>{{-- /trang nhóm của tôi --}}

    {{-- ── Cột phải: TRANG "GÓI CỦA TÔI" — quản lý gói nhiều buổi + lịch sử dùng gói, TÁCH RIÊNG
         khỏi trang đặt sân (book.blade.php chỉ có nút MUA gói, không quản lý/xem lịch sử). Dữ liệu
         từ AccountPage::loadPackages() (CustomerPackage tra theo SĐT đang đăng nhập). ── --}}
    <div x-show="page === 'packages'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
            <button type="button" @click="page = 'main'" class="w-8 h-8 rounded-full flex items-center justify-center bg-white/15 hover:bg-white/25 transition-colors shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="text-base font-bold">Gói của tôi</h2>
        </div>

        @if (count($packages) === 0)
            <div class="py-20 flex flex-col items-center gap-3 text-center px-6">
                <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mb-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 3.75c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                    </svg>
                </div>
                <p class="text-gray-700 font-semibold">Bạn chưa có gói nhiều buổi nào</p>
                <p class="text-gray-400 text-sm">Mua gói ngay tại trang đặt sân của chi nhánh để tiết kiệm hơn.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50" x-data="{ openId: null }">
                @foreach ($packages as $pkg)
                    <div class="px-5 py-4" wire:key="my-package-{{ $pkg['id'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-900">{{ $pkg['name'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $pkg['scope_label'] }}</p>
                            </div>
                            <span class="shrink-0 text-xs font-semibold border rounded-full px-2.5 py-1 {{ $pkg['status_class'] }}">{{ $pkg['status_label'] }}</span>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-emerald-500" style="width: {{ $pkg['sessions_total'] > 0 ? min(100, round($pkg['sessions_remaining'] / $pkg['sessions_total'] * 100)) : 0 }}%;"></div>
                            </div>
                            <span class="shrink-0 text-xs font-semibold text-gray-600">Còn {{ $pkg['sessions_remaining'] }}/{{ $pkg['sessions_total'] }} buổi</span>
                        </div>

                        <div class="mt-2 flex items-center justify-between text-xs text-gray-400">
                            <span>Mua ngày {{ $pkg['purchased_at'] }}</span>
                            @if ($pkg['expires_at'])
                                <span>Hết hạn {{ $pkg['expires_at'] }}</span>
                            @endif
                        </div>

                        @if (count($pkg['redemptions']) > 0)
                            <button type="button" @click="openId = (openId === {{ $pkg['id'] }} ? null : {{ $pkg['id'] }})"
                                class="mt-2 text-xs font-semibold text-emerald-600 hover:underline">
                                <span x-show="openId !== {{ $pkg['id'] }}">Xem lịch sử dùng ({{ count($pkg['redemptions']) }})</span>
                                <span x-show="openId === {{ $pkg['id'] }}" x-cloak>Ẩn lịch sử dùng</span>
                            </button>
                            <div x-show="openId === {{ $pkg['id'] }}" x-cloak class="mt-2 rounded-lg bg-gray-50 border border-gray-100 divide-y divide-gray-100">
                                @foreach ($pkg['redemptions'] as $r)
                                    <div class="px-3 py-2 flex items-center justify-between text-xs">
                                        <span class="text-gray-600">{{ $r['room_name'] ?? 'Buổi đặt sân' }}</span>
                                        <span class="text-gray-400">{{ $r['redeemed_at'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>{{-- /trang gói của tôi --}}

    {{-- ── Cột phải: TRANG "ĐANG CẬP NHẬT" — dùng cho mọi mục chưa có dữ liệu/tính năng thật
         (Nhóm của tôi, Danh sách lịch học, Thông báo, Khoá học, Điều khoản và chính
         sách, Ứng dụng có gì mới, Xoá tài khoản...) thay vì bịa nội dung giả. ── --}}
    <div x-show="page === 'placeholder'" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex items-center gap-3 px-5 py-4 text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
            <button type="button" @click="page = 'main'" class="w-8 h-8 rounded-full flex items-center justify-center bg-white/15 hover:bg-white/25 transition-colors shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="text-base font-bold" x-text="placeholderTitle"></h2>
        </div>
        <div class="py-20 flex flex-col items-center gap-3 text-center px-6">
            <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mb-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.992l1.005.828c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.02-.397-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.992l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <p class="text-gray-700 font-semibold" x-text="placeholderTitle"></p>
            <p class="text-gray-400 text-sm">Tính năng đang được cập nhật, vui lòng quay lại sau.</p>
        </div>
    </div>{{-- /trang đang cập nhật --}}

    </div>{{-- /right column --}}

    </div>{{-- /grid --}}

    @endif
</div>
