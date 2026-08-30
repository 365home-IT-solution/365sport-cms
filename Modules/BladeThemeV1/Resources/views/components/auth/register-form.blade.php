{{--
    Form Đăng ký — trang /dang-ky riêng theo giao diện mẫu tham khảo (khác auth/form.blade.php:
    KHÔNG đụng file đó, xem comment ở đầu file đó lý do). Vẫn dùng chung 3 API sẵn có
    (/api/auth/send-otp, /api/auth/verify-otp, /api/auth/register — xem ZaloOtpController) vì hệ
    thống chỉ hỗ trợ xác thực qua SĐT, chưa có đăng ký bằng email.

    Lưu ý khác biệt so với ảnh mẫu: API register() yêu cầu BẮT BUỘC "date_of_birth"
    (ZaloOtpController::register, rule 'required|date_format:d-m-Y|before:today') nên form này có
    thêm trường "Ngày sinh" không có trong ảnh mẫu — bỏ trường này sẽ khiến đăng ký luôn lỗi 422.
    Trường "Email" thu thập trên giao diện nhưng CHƯA gửi lên API (API hiện không nhận field này).

    Props: $primaryHex, $textOnPrimary.
--}}
@props(['primaryHex' => '#0B6B3A', 'textOnPrimary' => '#ffffff'])

<div
    x-data="{
        step: 'form',
        phone: '', email: '', fullname: '', date_of_birth: '',
        password: '', password_confirmation: '',
        showPassword: false, showConfirm: false,
        otp: '', phoneToken: '',
        loading: false, error: '',

        submitForm() {
            this.error = '';
            if (!this.phone || !this.fullname || !this.date_of_birth) {
                this.error = 'Vui lòng điền đầy đủ các trường bắt buộc.'; return;
            }
            if (this.password.length < 8) {
                this.error = 'Mật khẩu phải có ít nhất 8 ký tự.'; return;
            }
            if (this.password !== this.password_confirmation) {
                this.error = 'Nhập lại mật khẩu không khớp.'; return;
            }
            this.sendOtp();
        },

        async sendOtp() {
            this.loading = true; this.error = '';
            try {
                const res  = await fetch('/api/auth/send-otp', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({ phone: this.phone }),
                });
                const data = await res.json();
                if (!res.ok) { this.error = data.message; return; }
                this.step = 'otp';
            } catch (e) {
                this.error = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.loading = false;
            }
        },

        async verifyOtp() {
            this.loading = true; this.error = '';
            try {
                const res  = await fetch('/api/auth/verify-otp', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({ phone: this.phone, otp: this.otp }),
                });
                const data = await res.json();
                if (!res.ok) { this.error = data.message; return; }
                if (!data.is_new_user) {
                    // Số điện thoại đã có tài khoản — verify-otp trả token luôn, đăng nhập thẳng
                    // (đỡ bắt người dùng quay lại trang đăng nhập nhập lại OTP).
                    this.finishAuth(data.token, data.user);
                    return;
                }
                this.phoneToken = data.phone_token;
                await this.registerUser();
            } catch (e) {
                this.error = 'Lỗi kết nối. Vui lòng thử lại.';
            } finally {
                this.loading = false;
            }
        },

        async registerUser() {
            try {
                const dob = this.date_of_birth ? this.date_of_birth.split('-').reverse().join('-') : '';
                const res  = await fetch('/api/auth/register', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({
                        phone_token: this.phoneToken,
                        fullname: this.fullname,
                        date_of_birth: dob,
                        password: this.password,
                        password_confirmation: this.password_confirmation,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    if (res.status === 409) { window.location.href = '{{ route('login.page') }}'; return; }
                    this.error = data.message;
                    return;
                }
                this.finishAuth(data.token, data.user);
            } catch (e) {
                this.error = 'Lỗi kết nối. Vui lòng thử lại.';
            }
        },

        finishAuth(token, user) {
            localStorage.setItem('auth_token', token);
            localStorage.setItem('auth_user', JSON.stringify(user));
            window.dispatchEvent(new CustomEvent('auth-state-changed'));
            window.location.href = '/';
        },
    }"
    x-init="if (localStorage.getItem('auth_token')) { window.location.href = '/'; }"
>
    {{-- BƯỚC 1: Thông tin đăng ký --}}
    <div x-show="step === 'form'">
        <h2 class="text-lg font-semibold text-gray-900 mb-1">Đăng ký</h2>
        <p class="text-sm text-gray-500 mb-5">Tạo tài khoản để đặt sân nhanh hơn và nhận ưu đãi dành cho thành viên.</p>

        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Số điện thoại của bạn?</label>
            <div class="flex items-stretch gap-2">
                <span class="flex shrink-0 items-center gap-1 rounded-xl border border-gray-200 bg-white px-3 text-sm text-gray-700">🇻🇳 +84</span>
                <input x-model="phone" type="tel" autocomplete="tel" placeholder="Nhập số điện thoại"
                    class="w-full rounded-xl px-4 py-3 text-gray-900 text-sm bg-white border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                    style="--tw-ring-color: {{ $primaryHex }}40;">
            </div>
        </div>

        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Email của bạn?</label>
            <input x-model="email" type="email" autocomplete="email" placeholder="Nhập email của bạn"
                class="w-full rounded-xl px-4 py-3 text-gray-900 text-sm bg-white border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                style="--tw-ring-color: {{ $primaryHex }}40;">
        </div>

        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Tên đầy đủ (*)</label>
            <input x-model="fullname" type="text" placeholder="Nhập họ và tên"
                class="w-full rounded-xl px-4 py-3 text-gray-900 text-sm bg-white border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                style="--tw-ring-color: {{ $primaryHex }}40;">
        </div>

        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Ngày sinh (*)</label>
            <input x-model="date_of_birth" type="date"
                class="w-full rounded-xl px-4 py-3 text-gray-900 text-sm bg-white border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                style="--tw-ring-color: {{ $primaryHex }}40;">
        </div>

        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Mật khẩu (*)</label>
            <div class="relative">
                <input x-model="password" :type="showPassword ? 'text' : 'password'" placeholder="Nhập mật khẩu (*)"
                    class="w-full rounded-xl px-4 py-3 pr-10 text-gray-900 text-sm bg-white border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                    style="--tw-ring-color: {{ $primaryHex }}40;">
                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-xs font-medium text-gray-500 mb-1.5">Nhập mật khẩu</label>
            <div class="relative">
                <input x-model="password_confirmation" :type="showConfirm ? 'text' : 'password'" placeholder="Nhập lại mật khẩu"
                    class="w-full rounded-xl px-4 py-3 pr-10 text-gray-900 text-sm bg-white border border-gray-200 focus:outline-none focus:ring-2 focus:border-transparent transition-all"
                    style="--tw-ring-color: {{ $primaryHex }}40;">
                <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>
            </div>
        </div>

        <div x-show="error" x-cloak class="mb-3 text-sm text-red-500">
            <span x-text="error"></span>
        </div>

        <button @click="submitForm()" :disabled="loading"
            class="w-full rounded-lg py-3.5 text-sm font-bold flex items-center justify-center gap-2 transition-opacity hover:opacity-85 disabled:opacity-50"
            style="background-color: {{ $primaryHex }}; color: {{ $textOnPrimary }};">
            <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span x-text="loading ? 'Đang xử lý...' : 'ĐĂNG KÝ'"></span>
        </button>

        <p class="text-center text-sm mt-4">
            Bạn đã có tài khoản?
            <a href="{{ route('login.page') }}" class="font-semibold underline" style="color: {{ $primaryHex }};">Đăng nhập</a>
        </p>
    </div>

    {{-- BƯỚC 2: Xác nhận OTP --}}
    <div x-show="step === 'otp'">
        <button @click="step = 'form'; error = ''" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800 mb-4 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            Quay lại
        </button>

        <h2 class="text-lg font-semibold text-gray-900 mb-1">Nhập mã OTP</h2>
        <p class="text-sm text-gray-500 mb-5">
            Mã đã gửi đến Zalo số <span class="font-semibold" style="color: {{ $primaryHex }};" x-text="phone"></span>.
        </p>

        <div class="mb-4">
            <input x-model="otp" type="text" maxlength="6" placeholder="_ _ _ _ _ _"
                @keydown.enter="verifyOtp()"
                class="w-full rounded-lg px-3 py-2.5 text-gray-900 text-sm tracking-[0.5em] text-center bg-white border border-gray-200 focus:outline-none transition-all">
        </div>

        <div x-show="error" x-cloak class="mb-3 text-sm text-red-500">
            <span x-text="error"></span>
        </div>

        <button @click="verifyOtp()" :disabled="loading || otp.length < 6"
            class="w-full rounded-lg py-3.5 text-sm font-bold flex items-center justify-center gap-2 transition-opacity hover:opacity-85 disabled:opacity-50"
            style="background-color: {{ $primaryHex }}; color: {{ $textOnPrimary }};">
            <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span x-text="loading ? 'Đang xác nhận...' : 'Xác nhận OTP'"></span>
        </button>
    </div>
</div>
