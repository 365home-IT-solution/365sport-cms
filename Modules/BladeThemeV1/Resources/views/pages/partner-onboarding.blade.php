@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')

    {{--
        Đăng ký đối tác = MUA GÓI (port luồng MiniHouse): chọn gói + số tháng (+ trang landing nếu gói bán thêm) → nhập thông tin
        → (lần đầu) chờ duyệt để được tặng dùng thử, hoặc thanh toán QR PayOS → tài khoản đăng nhập gửi về email.
        API: GET /api/public/partner-plans, /partner-address; POST /api/public/partner-purchase; GET /api/public/partner-purchase/{mã}.
        Mã đơn lưu localStorage + ?ma= để quay lại theo dõi; trang tự hỏi lại trạng thái mỗi 5 giây khi đang chờ.
    --}}
    <main class="min-h-screen bg-gray-50 px-4 py-8 sm:px-6" x-data="partnerOnboarding(@js(['goi' => $planSlug, 'ma' => $code]))" x-init="init()" x-cloak>
        <div class="mx-auto max-w-4xl">
            <p class="text-sm font-bold uppercase tracking-widest text-[#0B6B3A]">Dành cho chủ sân</p>
            <h1 class="mt-1 text-3xl font-extrabold text-gray-900">Đăng ký làm đối tác</h1>
            <p class="mt-2 text-gray-500">Chọn gói, điền thông tin cơ sở — chúng tôi gửi tài khoản quản trị về email của bạn.</p>

            {{-- ===== Đang theo dõi một đơn ===== --}}
            <template x-if="result">
                <section class="mt-8 rounded-2xl bg-white p-6 shadow-sm sm:p-8">
                    <template x-if="result.stage === 'pending_approval'">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Đã nhận đăng ký của bạn</h2>
                            <p class="mt-2 text-gray-600">Chúng tôi sẽ duyệt hồ sơ và tặng bạn thời gian dùng thử. Tài khoản đăng nhập kèm mật khẩu được gửi về <strong x-text="result.partner.email"></strong> ngay sau khi duyệt.</p>
                            <p class="mt-3 text-sm text-gray-400">Trang này tự cập nhật; bạn có thể đóng và quay lại bằng liên kết này (đã lưu trên trình duyệt).</p>
                        </div>
                    </template>

                    <template x-if="result.stage === 'pending_payment'">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Thanh toán gói dịch vụ</h2>
                            <template x-if="result.payment">
                                <div class="mt-4 grid gap-6 sm:grid-cols-[220px_1fr]">
                                    <div x-show="qrUrl()">
                                        <img :src="qrUrl()" alt="Mã QR thanh toán" class="mx-auto w-full max-w-[220px] rounded-xl border border-gray-200 bg-white p-2">
                                        <p class="mt-1 text-center text-xs text-gray-400">Quét bằng app ngân hàng</p>
                                    </div>
                                    <div :class="qrUrl() ? '' : 'sm:col-span-2'">
                                        <p class="text-3xl font-extrabold text-[#0B6B3A]" x-text="result.payment.amount_vnd > 0 ? money(result.payment.amount_vnd) : 'Chúng tôi sẽ báo số tiền'"></p>
                                        <p class="text-sm text-gray-500"><span x-text="result.payment.plan && result.payment.plan.name"></span> · <span x-text="result.payment.months"></span> tháng<span x-show="result.payment.landing_addon"> · kèm trang landing</span></p>
                                        <dl class="mt-4 divide-y divide-gray-100 text-sm" x-show="result.payment.payment">
                                            <div class="flex justify-between py-2" x-show="result.payment.payment && result.payment.payment.account.account_number"><dt class="text-gray-500">Số tài khoản</dt><dd class="font-semibold" x-text="result.payment.payment.account.account_number"></dd></div>
                                            <div class="flex justify-between py-2" x-show="result.payment.payment && result.payment.payment.account.account_name"><dt class="text-gray-500">Chủ tài khoản</dt><dd class="font-semibold" x-text="result.payment.payment.account.account_name"></dd></div>
                                            <div class="flex justify-between py-2"><dt class="text-gray-500">Nội dung chuyển khoản</dt><dd class="font-semibold" x-text="result.payment.transaction_code"></dd></div>
                                        </dl>
                                        <p class="mt-3 text-sm text-gray-500" x-show="result.payment.source === 'request'">Gói này chưa có phí cố định: chúng tôi sẽ liên hệ báo số tiền; khi chuyển khoản ghi nội dung <strong x-text="result.payment.transaction_code"></strong>.</p>
                                        <a x-show="result.payment.payment && result.payment.payment.checkout_url" :href="result.payment.payment && result.payment.payment.checkout_url" target="_blank" rel="noopener"
                                           class="mt-4 inline-flex rounded-full bg-[#0B6B3A] px-6 py-2.5 text-sm font-bold text-white hover:bg-[#095c31]">Mở trang thanh toán</a>
                                        <p class="mt-3 text-xs text-gray-400">Sau khi nhận tiền, hệ thống tự kích hoạt và gửi tài khoản đăng nhập về email của bạn.</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="result.stage === 'paid' || result.stage === 'active' || result.stage === 'trial'">
                        <div>
                            <h2 class="text-xl font-bold text-[#0B6B3A]">Tài khoản đã sẵn sàng</h2>
                            <p class="mt-2 text-gray-600">
                                <span x-show="result.stage === 'trial'">Bạn đang dùng thử đến <strong x-text="dateOf(result.subscription && result.subscription.expires_at)"></strong>. </span>
                                Thông tin đăng nhập đã được gửi về <strong x-text="result.partner.email"></strong>.
                            </p>
                            <a x-show="result.account && result.account.login_url" :href="result.account && result.account.login_url" class="mt-5 inline-flex rounded-full bg-[#0B6B3A] px-6 py-2.5 text-sm font-bold text-white hover:bg-[#095c31]">Vào trang quản trị</a>
                            <p class="mt-3 text-sm text-gray-400" x-show="result.stage === 'paid'">Đã nhận thanh toán — tài khoản đang được tạo, vui lòng chờ ít phút.</p>
                        </div>
                    </template>

                    <template x-if="['cancelled','expired','rejected'].includes(result.stage)">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900" x-text="result.stage === 'rejected' ? 'Đăng ký chưa được chấp nhận' : 'Đơn đã đóng'"></h2>
                            <p class="mt-2 text-gray-600" x-text="result.stage === 'rejected' ? 'Chúng tôi đã gửi lý do về email của bạn.' : 'Đơn thanh toán đã bị huỷ hoặc hết hạn.'"></p>
                            <button type="button" @click="reset()" class="mt-4 rounded-full border border-gray-300 px-5 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Đăng ký lại</button>
                        </div>
                    </template>
                </section>
            </template>

            {{-- ===== Form đăng ký ===== --}}
            <template x-if="!result">
                <form class="mt-8 space-y-8" @submit.prevent="submit()">
                    <section>
                        <h2 class="text-lg font-bold text-gray-900">1. Chọn gói</h2>
                        <div class="mt-3 grid gap-4" :class="plans.length > 1 ? 'sm:grid-cols-3' : 'max-w-md'">
                            <template x-for="p in plans" :key="p.id">
                                <label class="cursor-pointer rounded-2xl border-2 bg-white p-4 transition" :class="form.plan_id === p.id ? 'border-[#0B6B3A] shadow-md' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" class="sr-only" :value="p.id" x-model.number="form.plan_id" @change="form.landing_addon = false">
                                    <p class="font-extrabold text-gray-900" x-text="p.name"></p>
                                    <p class="mt-1 text-xs text-gray-500" x-text="p.description"></p>
                                    <p class="mt-3"><span class="text-xl font-extrabold text-[#0B6B3A]" x-text="money(p.price_vnd)"></span><span class="text-xs text-gray-400"> / tháng</span></p>
                                    <p class="mt-1 text-xs text-gray-500" x-text="p.max_branches ? 'Tối đa ' + p.max_branches + ' chi nhánh' : 'Không giới hạn chi nhánh'"></p>
                                </label>
                            </template>
                        </div>
                        <p class="mt-3 text-sm text-red-600" x-show="errors.plan_id" x-text="first('plan_id')"></p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <template x-for="n in periodOptions" :key="n">
                                <button type="button" @click="form.periods = n" class="rounded-full border px-4 py-1.5 text-sm font-semibold" :class="form.periods === n ? 'border-[#0B6B3A] bg-[#0B6B3A] text-white' : 'border-gray-300 bg-white text-gray-700'" x-text="n + ' tháng'"></button>
                            </template>
                        </div>

                        <label class="mt-4 flex items-start gap-2 text-sm text-gray-700" x-show="current() && current().landing_addon.available">
                            <input type="checkbox" x-model="form.landing_addon" class="mt-0.5 rounded border-gray-300 text-[#0B6B3A]">
                            <span>Mua kèm <strong>trang landing riêng theo tên miền phụ</strong> của cơ sở (+<span x-text="current() ? money(current().landing_addon.price_vnd) : ''"></span> / kỳ)</span>
                        </label>

                        <p class="mt-4 rounded-xl bg-[#0B6B3A]/5 px-4 py-3 text-sm text-gray-700" x-show="current()">
                            Tạm tính: <strong class="text-[#0B6B3A]" x-text="money(total())"></strong>
                            <span x-show="trialMonths > 0"> — lần đầu đăng ký, sau khi được duyệt bạn được <strong>dùng thử <span x-text="trialMonths"></span> tháng</strong>; chỉ thanh toán khi hết thời gian dùng thử.</span>
                        </p>
                    </section>

                    <section class="rounded-2xl bg-white p-6 shadow-sm">
                        <h2 class="text-lg font-bold text-gray-900">2. Thông tin liên hệ</h2>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div><label class="text-sm font-semibold text-gray-700">Họ tên người đăng ký *</label><input type="text" x-model="form.full_name" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]" maxlength="255"><p class="mt-1 text-xs text-red-600" x-show="errors.full_name" x-text="first('full_name')"></p></div>
                            <div><label class="text-sm font-semibold text-gray-700">Số điện thoại *</label><input type="tel" x-model="form.phone" inputmode="tel" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]" placeholder="0901234567"><p class="mt-1 text-xs text-red-600" x-show="errors.phone" x-text="first('phone')"></p></div>
                            <div><label class="text-sm font-semibold text-gray-700">Email nhận tài khoản *</label><input type="email" x-model="form.email" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]"><p class="mt-1 text-xs text-red-600" x-show="errors.email" x-text="first('email')"></p></div>
                            <div><label class="text-sm font-semibold text-gray-700">Tên cơ sở / thương hiệu *</label><input type="text" x-model="form.business_name" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]" maxlength="255"><p class="mt-1 text-xs text-red-600" x-show="errors.business_name" x-text="first('business_name')"></p></div>
                            <div><label class="text-sm font-semibold text-gray-700">Tỉnh / thành phố *</label>
                                <select x-model="form.address_province_code" @change="loadWards()" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]"><option value="">— Chọn —</option><template x-for="pr in provinces" :key="pr.code"><option :value="pr.code" x-text="pr.name"></option></template></select>
                                <p class="mt-1 text-xs text-red-600" x-show="errors.address_province_code" x-text="first('address_province_code')"></p></div>
                            <div><label class="text-sm font-semibold text-gray-700">Phường / xã *</label>
                                <select x-model="form.address_ward_code" :disabled="!wards.length" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]"><option value="">— Chọn —</option><template x-for="w in wards" :key="w.code"><option :value="w.code" x-text="w.name"></option></template></select>
                                <p class="mt-1 text-xs text-red-600" x-show="errors.address_ward_code" x-text="first('address_ward_code')"></p></div>
                            <div class="sm:col-span-2"><label class="text-sm font-semibold text-gray-700">Số nhà, tên đường *</label><input type="text" x-model="form.address_street" class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-[#0B6B3A] focus:outline-none focus:ring-1 focus:ring-[#0B6B3A]" maxlength="255"><p class="mt-1 text-xs text-red-600" x-show="errors.address_street || errors.address" x-text="first('address_street') || first('address')"></p></div>
                        </div>
                        <p class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" x-show="message" x-text="message"></p>
                        <button type="submit" :disabled="loading || !form.plan_id" class="mt-6 w-full rounded-full bg-[#0B6B3A] py-3 text-sm font-bold text-white hover:bg-[#095c31] disabled:opacity-50 sm:w-auto sm:px-10">
                            <span x-show="!loading">Đăng ký</span><span x-show="loading">Đang gửi...</span>
                        </button>
                    </section>
                </form>
            </template>
        </div>
    </main>

    @livewire('bladethemev1::footer')

    <script>
        function partnerOnboarding(init) {
            return {
                plans: [], provinces: [], wards: [], periodOptions: @js(config('subscription.period_options')), trialMonths: 0,
                form: { plan_id: null, periods: 1, landing_addon: false, full_name: '', phone: '', email: '', business_name: '', address_street: '', address_province_code: '', address_ward_code: '' },
                errors: {}, message: '', loading: false, result: null, timer: null, token: null,
                async init() {
                    const get = async (u) => (await fetch(u, { headers: { Accept: 'application/json' } })).json();
                    try {
                        const [plans, provinces] = await Promise.all([get('/api/public/partner-plans'), get('/api/public/partner-address')]);
                        this.plans = plans.data || []; this.trialMonths = (plans.meta && plans.meta.signup_trial_months) || 0; this.provinces = provinces.data || [];
                    } catch (e) { this.message = 'Không tải được danh sách gói. Vui lòng thử lại.'; }
                    const wanted = this.plans.find(p => p.slug === init.goi);
                    this.form.plan_id = wanted ? wanted.id : (this.plans.find(p => p.is_highlighted) || this.plans[0] || {}).id || null;
                    let saved = null; try { saved = localStorage.getItem('partner_purchase_token'); } catch (e) {}
                    const token = init.ma || saved;
                    if (token) { this.token = token; await this.refresh(true); }
                },
                current() { return this.plans.find(p => p.id === this.form.plan_id); },
                total() {
                    const p = this.current(); if (!p) return 0;
                    const o = p.periods.find(x => x.periods === this.form.periods) || p.periods[0];
                    return this.form.landing_addon && o.amount_with_landing_vnd ? o.amount_with_landing_vnd : o.amount_vnd;
                },
                money(v) { return new Intl.NumberFormat('vi-VN').format(v || 0) + 'đ'; },
                dateOf(iso) { return iso ? new Date(iso).toLocaleDateString('vi-VN') : ''; },
                first(k) { return (this.errors[k] || [])[0] || ''; },
                async loadWards() {
                    this.form.address_ward_code = ''; this.wards = [];
                    if (!this.form.address_province_code) return;
                    const r = await (await fetch('/api/public/partner-address?province_code=' + this.form.address_province_code, { headers: { Accept: 'application/json' } })).json();
                    this.wards = r.data || [];
                },
                async submit() {
                    this.loading = true; this.errors = {}; this.message = '';
                    try {
                        const res = await fetch('/api/public/partner-purchase', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ ...this.form, landing_addon: !!this.form.landing_addon }) });
                        const json = await res.json();
                        if (res.status === 422) { this.errors = json.errors || {}; this.message = json.message || 'Vui lòng kiểm tra lại thông tin.'; return; }
                        if (res.status === 429) { this.message = 'Bạn thao tác quá nhanh, vui lòng thử lại sau ít phút.'; return; }
                        if (!res.ok) { this.message = json.message || 'Không gửi được đăng ký, vui lòng thử lại.'; return; }
                        this.token = json.data.purchase_token;
                        try { localStorage.setItem('partner_purchase_token', this.token); } catch (e) {}
                        history.replaceState(null, '', '?ma=' + this.token);
                        this.result = json.data; this.poll();
                    } catch (e) { this.message = 'Lỗi kết nối, vui lòng thử lại.'; } finally { this.loading = false; }
                },
                async refresh(first) {
                    try {
                        const res = await fetch('/api/public/partner-purchase/' + encodeURIComponent(this.token), { headers: { Accept: 'application/json' } });
                        if (res.status === 404) { if (first) { this.reset(true); } return; }
                        if (!res.ok) return;
                        this.result = (await res.json()).data; this.poll();
                    } catch (e) {}
                },
                poll() {
                    clearTimeout(this.timer);
                    if (this.result && ['pending_approval', 'pending_payment', 'paid'].includes(this.result.stage)) this.timer = setTimeout(() => this.refresh(false), 5000);
                },
                reset(silent) {
                    clearTimeout(this.timer); this.result = null; this.token = null;
                    try { localStorage.removeItem('partner_purchase_token'); } catch (e) {}
                    history.replaceState(null, '', location.pathname);
                },
                qrUrl() {
                    const a = this.result && this.result.payment && this.result.payment.payment && this.result.payment.payment.account;
                    if (!a || !a.account_number || !a.bank_bin) return '';
                    return 'https://img.vietqr.io/image/' + a.bank_bin + '-' + a.account_number + '-compact2.png?amount=' + this.result.payment.amount_vnd + '&addInfo=' + encodeURIComponent(a.content) + '&accountName=' + encodeURIComponent(a.account_name || '');
                },
            };
        }
    </script>
@endsection
