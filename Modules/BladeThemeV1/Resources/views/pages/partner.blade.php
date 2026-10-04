@extends('bladethemev1::layouts.master')

<x-bladethemev1::seo :seoData="$seoData" />

@section('content')
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')

    {{-- Trang giới thiệu/thu hút chủ sân đăng ký làm đối tác (kiểu landing page B2B, tham khảo bố
         cục datlich.alobo.vn: hero -> tính năng -> lợi ích -> quy trình -> form đăng ký) — chỉ là
         nội dung QUẢNG CÁO tĩnh, không đụng tới logic đặt sân/giá/khuyến mãi thật. Phần duy nhất có
         backend thật là form cuối trang (@livewire('bladethemev1::form-contact', ...), dùng đúng
         component render/lưu submission có sẵn đang phục vụ trang Liên hệ — xem
         BladeThemeV1Controller::partnerPage()). Scope màu xanh lá thương hiệu (#0B6B3A) cục bộ
         giống trang Tin tức (pages/posts.blade.php) — không đổi --color-primary toàn site. --}}
    <div class="partner-brand-scope" style="--color-primary: #0B6B3A;">

        {{-- Hero --}}
        <section class="relative overflow-hidden px-4 py-16 text-center sm:px-6 sm:py-24" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 55%, #1AA859 100%);">
            <div class="mx-auto max-w-3xl">
                <span class="inline-block rounded-full bg-white/15 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-white">Dành cho chủ sân thể thao</span>
                <h1 class="mt-4 text-3xl font-extrabold text-white sm:text-5xl">
                    Trở thành đối tác của {{ config('app.name') }}
                </h1>
                <p class="mx-auto mt-4 max-w-xl text-sm text-white/90 sm:text-base">
                    Đưa sân thể thao của bạn lên nền tảng đặt lịch trực tuyến — tiếp cận thêm khách
                    hàng mới, quản lý lịch đặt và doanh thu ngay trên 1 trang quản trị duy nhất.
                </p>
                <a href="#partner-form"
                   class="mt-8 inline-block rounded-full bg-[#F5A623] px-8 py-3 text-sm font-extrabold uppercase text-white shadow-lg transition hover:brightness-95">
                    Đăng ký làm đối tác ngay
                </a>
            </div>
        </section>

        {{-- Tính năng chính --}}
        <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <div class="mb-10 text-center">
                <h2 class="text-2xl font-extrabold text-gray-900 sm:text-3xl">Nền tảng quản lý sân toàn diện</h2>
                <p class="mt-2 text-sm text-gray-500">Mọi công cụ bạn cần để vận hành sân thể thao chuyên nghiệp hơn.</p>
            </div>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $features = [
                        ['icon' => 'calendar-days', 'title' => 'Lịch đặt sân thời gian thực', 'desc' => 'Theo dõi khung giờ trống/đã đặt/đang giữ chỗ ngay trên 1 bảng lịch trực quan, tránh trùng lịch.'],
                        ['icon' => 'currency-dollar', 'title' => 'Giá linh hoạt theo khung giờ', 'desc' => 'Tự thiết lập mức giá riêng cho từng khung giờ, từng sân — giờ vàng, giờ khuya, cuối tuần...'],
                        ['icon' => 'gift', 'title' => 'Khuyến mãi tự động áp dụng', 'desc' => 'Gắn ưu đãi giảm giá/tăng giá cho khung giờ cụ thể, hệ thống tự tính đúng giá khi khách đặt.'],
                        ['icon' => 'map-pin', 'title' => 'Hiện diện trên bản đồ tìm sân', 'desc' => 'Sân của bạn xuất hiện trên trang bản đồ và kết quả tìm kiếm gần vị trí khách hàng.'],
                        ['icon' => 'chart-bar', 'title' => 'Trang quản trị tập trung', 'desc' => 'Theo dõi đơn đặt, khách hàng, doanh thu của tất cả chi nhánh trên cùng 1 trang quản trị.'],
                        ['icon' => 'building-storefront', 'title' => 'Không giới hạn chi nhánh', 'desc' => 'Quản lý nhiều sân/nhiều chi nhánh cùng lúc, mỗi nơi tự thiết lập giá và khung giờ riêng.'],
                    ];
                @endphp
                @foreach ($features as $f)
                    <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:shadow-md">
                        <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-full bg-[#0B6B3A]/10 text-[#0B6B3A]">
                            <x-dynamic-component :component="'heroicon-o-' . $f['icon']" class="h-6 w-6" />
                        </div>
                        <h3 class="text-base font-bold text-gray-900">{{ $f['title'] }}</h3>
                        <p class="mt-1.5 text-sm text-gray-500">{{ $f['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Lợi ích --}}
        <section class="bg-gray-50 px-4 py-14 sm:px-6">
            <div class="mx-auto max-w-6xl">
                <div class="mb-10 text-center">
                    <h2 class="text-2xl font-extrabold text-gray-900 sm:text-3xl">Vì sao nên hợp tác cùng chúng tôi?</h2>
                </div>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @php
                        $benefits = [
                            ['title' => 'Tăng doanh thu', 'desc' => 'Tiếp cận thêm khách hàng mới đang tìm sân quanh khu vực của bạn.'],
                            ['title' => 'Vận hành chuyên nghiệp', 'desc' => 'Giảm hẳn tình trạng đặt trùng lịch, thất lạc thông tin khách hàng.'],
                            ['title' => 'Chủ động điều chỉnh giá', 'desc' => 'Tự set giá/khuyến mãi theo khung giờ, thay đổi bất kỳ lúc nào.'],
                            ['title' => 'Miễn phí thiết lập', 'desc' => 'Được hỗ trợ tạo chi nhánh, cấu hình sân và khung giờ ban đầu.'],
                        ];
                    @endphp
                    @foreach ($benefits as $b)
                        <div class="rounded-2xl bg-white p-6 text-center shadow-sm">
                            <p class="text-base font-extrabold text-[#0B6B3A]">{{ $b['title'] }}</p>
                            <p class="mt-1.5 text-sm text-gray-500">{{ $b['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Quy trình hợp tác --}}
        <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6">
            <div class="mb-10 text-center">
                <h2 class="text-2xl font-extrabold text-gray-900 sm:text-3xl">Quy trình hợp tác đơn giản</h2>
            </div>
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
                @php
                    $steps = [
                        ['n' => '1', 'title' => 'Đăng ký thông tin', 'desc' => 'Điền thông tin sân/chi nhánh của bạn vào biểu mẫu bên dưới.'],
                        ['n' => '2', 'title' => 'Được tư vấn & thiết lập', 'desc' => 'Đội ngũ liên hệ tư vấn, hỗ trợ tạo chi nhánh, sân và khung giờ.'],
                        ['n' => '3', 'title' => 'Bắt đầu nhận đặt lịch', 'desc' => 'Sân của bạn xuất hiện trên nền tảng, sẵn sàng nhận khách đặt lịch.'],
                    ];
                @endphp
                @foreach ($steps as $s)
                    <div class="text-center">
                        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full text-lg font-extrabold text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #1AA859 100%);">
                            {{ $s['n'] }}
                        </div>
                        <p class="text-sm font-bold text-gray-900">{{ $s['title'] }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $s['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Bảng giá phần mềm/website cho đối tác — lấy TRỰC TIẾP từ dữ liệu admin đã cấu hình
             (App\Models\PartnerPlan, trang "Gói đối tác" + "Tính năng theo gói") thay vì mảng viết
             cứng như trước (bug đã gặp: sửa gói ở admin không phản ánh đúng ra đây). Không gắn với
             hệ thống billing/subscription thật nào trong CMS (module chưa có tính năng thu phí đối
             tác) — có ghi chú rõ "liên hệ để được báo giá chính xác" để không cam kết sai con số. --}}
        <section class="mx-auto max-w-6xl px-4 py-14 sm:px-6">
            <div class="mb-10 text-center">
                <h2 class="text-2xl font-extrabold text-gray-900 sm:text-3xl">Cung cấp trọn gói phần mềm &amp; website</h2>
                <p class="mx-auto mt-2 max-w-2xl text-sm text-gray-500">
                    Ngoài việc đưa sân của bạn lên nền tảng đặt lịch, chúng tôi còn cung cấp trọn gói
                    phần mềm quản lý và website riêng theo thương hiệu của bạn — chọn gói phù hợp,
                    càng nhiều tính năng thì chi phí càng cao.
                </p>
            </div>
            <div class="grid grid-cols-1 gap-6 {{ $partnerPlans->count() > 1 ? 'lg:grid-cols-3' : 'mx-auto max-w-md' }}">
                @forelse ($partnerPlans as $plan)
                    @php
                        // Gói SAU kế thừa toàn bộ tính năng gói TRƯỚC (theo sort_order) — thay vì
                        // liệt kê lại từ đầu (dài dòng, dễ đọc nhầm là gói cao hơn ít tính năng
                        // hơn), chỉ hiện 1 dòng "Tất cả tính năng gói {tên gói trước}" (tô nổi bật)
                        // + PHẦN CHÊNH LỆCH thật sự mới có ở gói này. extra_features (tự gõ tay,
                        // không phải khoá module thật) LUÔN hiện đủ, không tính vào phần kế thừa vì
                        // đây là text tự do, không có gì đảm bảo trùng khớp giữa các gói.
                        $previousPlan = $loop->index > 0 ? $partnerPlans[$loop->index - 1] : null;
                        $newFeatureKeys = $previousPlan
                            ? array_diff($plan->features ?? [], $previousPlan->features ?? [])
                            : ($plan->features ?? []);
                    @endphp
                    <div class="relative flex flex-col rounded-2xl border bg-white p-7 transition duration-300 hover:-translate-y-1 {{ $plan->is_highlighted ? 'border-[#0B6B3A] shadow-xl ring-2 ring-[#0B6B3A] hover:shadow-2xl' : 'border-gray-200 shadow-sm hover:shadow-lg' }}">
                        {{-- Dành 1 khoảng cao CỐ ĐỊNH (h-7) TRÊN CẢ 3 CARD (kể cả không nổi bật) —
                             chỉ card nổi bật mới có nội dung hiện ra trong đó, 2 card kia để trắng
                             trơn (không viền/không nền, không "chiếm ô" nhìn thấy được) — mục đích
                             DUY NHẤT là giữ tên gói/mô tả/giá luôn thẳng hàng ngang nhau giữa 3
                             card (bug đã gặp: "các thẻ bị lệch nhau" khi chỉ card giữa có thêm
                             đệm riêng). --}}
                        <div class="mb-3 flex h-7 items-center justify-center">
                            @if ($plan->is_highlighted)
                                <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#F5A623] px-4 py-1.5 text-[11px] font-extrabold uppercase tracking-wide text-white shadow-md">
                                    ⭐ Phổ biến nhất
                                </span>
                            @endif
                        </div>

                        <p class="text-lg font-extrabold text-gray-900">{{ $plan->name }}</p>
                        @if ($plan->description)
                            <p class="mt-1.5 min-h-[2.5rem] text-xs leading-relaxed text-gray-500">{{ $plan->description }}</p>
                        @endif

                        <p class="mt-4 flex items-baseline gap-1 border-b border-gray-100 pb-5">
                            <span class="text-3xl font-extrabold tracking-tight text-[#0B6B3A]">{{ number_format($plan->price, 0, ',', '.') }}đ</span>
                            <span class="text-sm font-medium text-gray-400">/tháng</span>
                        </p>

                        <ul class="mt-5 flex-1 space-y-3 text-sm text-gray-600">
                            @if ($previousPlan)
                                <li class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#0B6B3A]/10 to-[#0B6B3A]/5 px-3 py-2 font-bold text-[#0B6B3A]">
                                    <x-heroicon-s-sparkles class="h-4 w-4 shrink-0" />
                                    <span>Tất cả tính năng gói {{ $previousPlan->name }}</span>
                                </li>
                            @endif

                            <li class="flex items-start gap-2.5">
                                <x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-[#0B6B3A]" />
                                <span>{{ $plan->max_branches ? 'Quản lý tối đa ' . $plan->max_branches . ' chi nhánh' : 'Không giới hạn số chi nhánh' }}</span>
                            </li>

                            @foreach ($newFeatureKeys as $featureKey)
                                <li class="flex items-start gap-2.5">
                                    <x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-[#0B6B3A]" />
                                    <span>{{ \App\Filament\Resources\PartnerPlanResource::FEATURE_OPTIONS[$featureKey] ?? $featureKey }}</span>
                                </li>
                            @endforeach
                            {{-- Dòng tự gõ thêm ở admin (PartnerPlan::extra_features) — thuần quảng
                                 cáo, không gắn module thật nào, admin tự liệt kê (vd Tư vấn 1-1,
                                 Xây dựng website riêng...). --}}
                            @foreach ($plan->extra_features ?? [] as $extraFeature)
                                <li class="flex items-start gap-2.5">
                                    <x-heroicon-o-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-[#0B6B3A]" />
                                    <span>{{ $extraFeature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ route('partner-onboarding.page', ['goi' => $plan->slug]) }}"
                           class="mt-7 block rounded-full py-3 text-center text-sm font-bold shadow-sm transition {{ $plan->is_highlighted ? 'bg-[#0B6B3A] text-white hover:bg-[#095c31] hover:shadow-md' : 'border-2 border-[#0B6B3A] text-[#0B6B3A] hover:bg-[#0B6B3A] hover:text-white' }}">
                            Chọn gói này
                        </a>
                    </div>
                @empty
                    <p class="col-span-full text-center text-sm text-gray-400">Hiện chưa có gói dịch vụ nào đang mở bán.</p>
                @endforelse
            </div>
            <p class="mt-6 text-center text-xs text-gray-400">
                Giá tham khảo, có thể thay đổi tuỳ theo quy mô và yêu cầu thực tế — vui lòng liên hệ để được tư vấn và báo giá chính xác nhất.
            </p>
        </section>

        {{-- Form đăng ký --}}
        <section id="partner-form" class="scroll-mt-32 bg-gray-50 px-4 py-14 sm:px-6">
            <div class="mx-auto mb-8 max-w-xl rounded-2xl bg-[#0B6B3A] p-6 text-center text-white shadow-lg sm:p-8">
                <p class="text-xl font-extrabold">Đăng ký ngay — nhận tài khoản qua email</p>
                <p class="mt-1 text-sm text-white/80">Chọn gói, điền thông tin cơ sở; lần đầu đăng ký được duyệt và tặng dùng thử.</p>
                <a href="{{ route('partner-onboarding.page') }}" class="mt-4 inline-flex rounded-full bg-white px-8 py-2.5 text-sm font-bold text-[#0B6B3A] hover:bg-white/90">Đăng ký làm đối tác</a>
                <p class="mt-5 text-xs text-white/70">Cần tư vấn thêm? Để lại thông tin ở biểu mẫu bên dưới, chúng tôi sẽ liên hệ.</p>
            </div>
            <div class="mx-auto max-w-xl rounded-2xl bg-white p-6 shadow-lg sm:p-10">
                @if ($partnerForm)
                    @livewire('bladethemev1::form-contact', ['form' => $partnerForm], key('partner-form'))
                @else
                    <p class="text-center text-sm text-gray-400">Biểu mẫu đăng ký hiện chưa khả dụng, vui lòng thử lại sau.</p>
                @endif
            </div>
        </section>
    </div>

    @livewire('bladethemev1::footer')

    <style>
        .partner-brand-scope .bgButton,
        .partner-brand-scope .paginate {
            color: #0B6B3A !important;
            border-color: #0B6B3A !important;
        }
        .partner-brand-scope button[type="submit"] {
            background-color: #0B6B3A !important;
        }
    </style>
@endsection
