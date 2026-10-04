{{--
    Trang xác nhận đặt lịch (giao diện mẫu ALO Booking) — thay cho trang chi tiết sân đầy đủ khi
    khách vừa chọn khung giờ xong (book/_timeline.blade.php -> "TIẾP THEO" -> Book::saveAndRedirect()
    -> ProductDetail::render(), chỉ dùng view này khi $fromBookingPage && bookingStyle===1).

    QUAN TRỌNG: KHÔNG có logic đặt/thanh toán mới ở đây — mọi input bind thẳng vào property Livewire
    THẬT đã có sẵn (buyerName/buyerPhone/note/selectedServices), nút "XÁC NHẬN & THANH TOÁN" gọi
    thẳng confirmBooking() THẬT (Modules/BladeThemeV1/Livewire/ProductDetail.php) — hàm này tự tạo
    Order/OrderItem thật và chuyển sang PayOS (PaymentService::createRoomBookingPaymentData()),
    y hệt luồng "Đặt sân" trên trang chi tiết sân cũ, chỉ khác giao diện bọc ngoài.
--}}
@php
    $branch = $product?->categories?->first();
    $totalMinutes = collect($selectedSlots)->reduce(function ($sum, $slot) {
        [$sh, $sm] = array_map('intval', explode(':', $slot['startTime']));
        [$eh, $em] = array_map('intval', explode(':', $slot['endTime']));
        $mins = ($eh * 60 + $em) - ($sh * 60 + $sm);
        if ($mins <= 0) {
            $mins += 24 * 60;
        }
        return $sum + $mins;
    }, 0);
    $totalHoursLabel = intdiv($totalMinutes, 60) . 'h' . ($totalMinutes % 60 > 0 ? $totalMinutes % 60 : '');
    $firstSlotDate = $selectedSlots[0]['date'] ?? null;
@endphp

<div class="min-h-screen" style="background: linear-gradient(160deg, #0B6B3A 0%, #128A4A 45%, #1AA859 100%);"
     x-data="{}"
     x-init="
        const token = localStorage.getItem('auth_token');
        if (token) $wire.prefillFromAuth(token);
        window.addEventListener('auth-state-changed', () => {
            const t = localStorage.getItem('auth_token');
            $wire.prefillFromAuth(t || '');
        });
     ">
    <div class="mx-auto max-w-3xl px-4 py-4 sm:py-6 pb-28">
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ url()->previous() }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h1 class="text-base font-extrabold uppercase tracking-wide text-white sm:text-lg">Xác nhận đặt lịch</h1>
        </div>

        {{-- Thông tin sân --}}
        <div class="mb-3 rounded-xl bg-white/10 p-4 text-white">
            <p class="mb-2 flex items-center gap-1.5 text-sm font-bold text-amber-300">
                <x-heroicon-o-map class="h-4 w-4" /> Thông tin sân
            </p>
            <p class="text-sm">Tên CLB: <span class="font-bold">{{ $branch->name ?? $product?->name }}</span></p>
            @if ($product?->address)
                <p class="mt-1 text-sm">Địa chỉ: <span class="font-semibold">{{ $product->address }}</span></p>
            @endif
        </div>

        {{-- Thông tin lịch đặt --}}
        <div class="mb-3 rounded-xl bg-white/10 p-4 text-white">
            <p class="mb-2 flex items-center gap-1.5 text-sm font-bold text-amber-300">
                <x-heroicon-o-ticket class="h-4 w-4" /> Thông tin lịch đặt
            </p>

            @if ($firstSlotDate)
                <p class="text-sm">Ngày: <span class="font-bold">{{ \Carbon\Carbon::parse($firstSlotDate)->format('d/m/Y') }}</span></p>
            @endif

            @if (empty($selectedSlots))
                <p class="mt-2 text-sm italic text-white/70">Chưa chọn khung giờ nào.</p>
            @else
                <div class="mt-2 space-y-1">
                    @foreach ($selectedSlots as $slot)
                        <p class="text-sm">
                            - {{ $slot['roomName'] ?? $product?->name }}: {{ substr($slot['startTime'], 0, 5) }} - {{ substr($slot['endTime'], 0, 5) }}
                            @if (! $usePackage)
                                | <span class="font-bold text-amber-300">{{ number_format((float) $slot['price'], 0, ',', '.') }} đ</span>
                            @else
                                | <span class="font-bold text-emerald-200">Dùng 1 buổi trong gói</span>
                            @endif
                        </p>
                    @endforeach
                </div>
            @endif

            <p class="mt-3 text-sm">Tổng giờ: <span class="font-bold">{{ $totalHoursLabel }}</span></p>
        </div>

        @php
            // Ba nguồn dữ liệu độc lập trên UI: dịch vụ thuần, dụng cụ kho và HLV theo chi nhánh.
            $serviceItems = $additionalServices->filter(fn ($item) => is_null($item->warehouse_item_id));
            $equipmentItems = $additionalServices->filter(fn ($item) => ! is_null($item->warehouse_item_id));
            $selectedCoach = $coaches?->firstWhere('id', $selectedCoachId);
        @endphp

        {{-- 1. Dịch vụ thuần — không gắn vật tư kho. Chi nhánh không có gì để chọn thì ẩn hẳn. --}}
        @if ($serviceItems->isNotEmpty())
        <div x-data="{ openServices: false }" class="mb-3">
            <button type="button" @click="openServices = !openServices"
                    class="w-full rounded-xl border border-white/30 bg-white/5 py-3 text-center text-sm font-bold text-white hover:bg-white/15">
                <span x-show="!openServices">+ Thêm dịch vụ</span>
                <span x-show="openServices" x-cloak>− Ẩn danh sách dịch vụ</span>
            </button>

            <div x-show="openServices" x-cloak class="mt-2 rounded-xl bg-white p-3">
                @foreach ($serviceItems as $service)
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0" wire:key="svc-{{ $service->id }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $service->name }}</p>
                            <p class="text-xs text-gray-500">{{ number_format((float) $service->price, 0, ',', '.') }} đ</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" wire:click="decrementService({{ $service->id }})"
                                    class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-600 hover:bg-gray-50">−</button>
                            <span class="w-5 text-center text-sm font-semibold">{{ $selectedServices[$service->id] ?? 0 }}</span>
                            <button type="button" wire:click="incrementService({{ $service->id }})"
                                    class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-600 hover:bg-gray-50">+</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 2. Thuê vật tư — chỉ các mục gắn warehouse_item, có giới hạn tồn kho riêng --}}
        @if ($equipmentItems->isNotEmpty())
        <div x-data="{ openEquipment: false }" class="mb-3">
            <button type="button" @click="openEquipment = !openEquipment"
                    class="w-full rounded-xl border border-white/30 bg-white/5 py-3 text-center text-sm font-bold text-white hover:bg-white/15">
                <span x-show="!openEquipment">+ Thuê vật tư</span>
                <span x-show="openEquipment" x-cloak>− Ẩn danh sách vật tư</span>
            </button>

            <div x-show="openEquipment" x-cloak class="mt-2 rounded-xl bg-white p-3">
                @foreach ($equipmentItems as $equipment)
                    @php
                        $available = $equipment->warehouseItem?->quantity_reserve ?? 0;
                        $qty = $selectedEquipment[$equipment->id] ?? 0;
                    @endphp
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2 last:border-0" wire:key="equipment-{{ $equipment->id }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $equipment->name }}</p>
                            <p class="text-xs text-gray-500">{{ number_format((float) $equipment->price, 0, ',', '.') }} đ · Còn {{ $available }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" wire:click="decrementEquipment({{ $equipment->id }})" class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-600">−</button>
                            <span class="w-5 text-center text-sm font-semibold">{{ $qty }}</span>
                            <button type="button" wire:click="incrementEquipment({{ $equipment->id }})" @disabled($available <= $qty)
                                    class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-300 text-gray-600 disabled:cursor-not-allowed disabled:opacity-40">+</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- 3. Thuê HLV — module Coach riêng, chỉ xuất hiện trong booking sân của đúng chi nhánh --}}
        @if ($coaches && $coaches->isNotEmpty())
        <div x-data="{ openCoaches: false, detail: null }" class="mb-3">
            <button type="button" @click="openCoaches = !openCoaches"
                    class="w-full rounded-xl border border-white/30 bg-white/5 py-3 text-center text-sm font-bold text-white hover:bg-white/15">
                <span x-show="!openCoaches">+ Thuê huấn luyện viên</span>
                <span x-show="openCoaches" x-cloak>− Ẩn danh sách huấn luyện viên</span>
            </button>

            <div x-show="openCoaches" x-cloak class="mt-2 rounded-xl bg-white p-3">
                @foreach ($coaches as $coach)
                    @php
                        // Chữ cái đại diện: bỏ tiền tố kiểu "[DEMO] " để không hiện ra ký tự "[".
                        $coachInitial = mb_strtoupper(mb_substr(trim(preg_replace('/^\[[^\]]*\]\s*/u', '', $coach->name)) ?: $coach->name, 0, 1));
                    @endphp
                    <div class="flex items-center gap-3 border-b border-gray-100 py-3 last:border-0" wire:key="coach-{{ $coach->id }}">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-100 font-bold text-emerald-700">
                            @if($coach->photo)<img src="{{ asset('storage/' . $coach->photo) }}" class="h-full w-full object-cover" alt="{{ $coach->name }}">@else{{ $coachInitial }}@endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-gray-900">{{ $coach->name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ $coach->specialties ?: 'Huấn luyện theo yêu cầu' }}</p>
                            <p class="text-xs font-semibold text-emerald-700">{{ number_format($coach->hourly_rate, 0, ',', '.') }} đ / giờ</p>
                        </div>
                        <div class="flex shrink-0 flex-col gap-1.5">
                            <button type="button" @click="detail = {{ json_encode($coach->only(['name', 'photo', 'specialties', 'certifications', 'bio', 'years_experience', 'hourly_rate'])) }}"
                                    class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Chi tiết</button>
                            @if($selectedCoachId === $coach->id)
                                <button type="button" wire:click="removeCoach" class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-bold text-red-600">Bỏ chọn</button>
                            @else
                                <button type="button" wire:click="selectCoach({{ $coach->id }})" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white">Chọn</button>
                            @endif
                        </div>
                    </div>
                @endforeach
                @error('coach')<p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
            </div>

            <div x-show="detail" x-cloak class="fixed inset-0 z-[10050] flex items-center justify-center bg-black/60 p-4" @click.self="detail=null">
                <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
                    <div class="flex items-start justify-between gap-3"><h3 class="text-xl font-extrabold text-gray-900" x-text="detail?.name"></h3><button type="button" @click="detail=null" class="text-2xl text-gray-400">×</button></div>
                    <p class="mt-3 text-sm font-semibold text-emerald-700" x-text="new Intl.NumberFormat('vi-VN').format(detail?.hourly_rate || 0) + ' đ / giờ'"></p>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="font-bold text-gray-700">Chuyên môn</dt><dd class="text-gray-600" x-text="detail?.specialties || 'Chưa cập nhật'"></dd></div>
                        <div><dt class="font-bold text-gray-700">Chứng chỉ</dt><dd class="text-gray-600" x-text="detail?.certifications || 'Chưa cập nhật'"></dd></div>
                        <div><dt class="font-bold text-gray-700">Kinh nghiệm</dt><dd class="text-gray-600" x-text="(detail?.years_experience || 0) + ' năm'"></dd></div>
                        <div><dt class="font-bold text-gray-700">Giới thiệu</dt><dd class="whitespace-pre-line text-gray-600" x-text="detail?.bio || 'Chưa cập nhật'"></dd></div>
                    </dl>
                </div>
            </div>
        </div>

        @endif

        {{-- Chi tiết thanh toán — GOM mọi khoản (sân, giảm giá, phụ thu, dịch vụ, vật tư, HLV) về
             ĐÚNG 1 chỗ, cùng thứ tự với card "Tổng thanh toán" bên admin (total-amount-card.blade.php).
             Tiền sân lấy PHẦN CÒN LẠI của $totalAmount sau khi trừ các khoản khác (cộng lại giảm
             giá) để tổng các dòng LUÔN khớp đúng số sẽ thu, kể cả ca "Đặt full ngày" (bỏ khuyến mãi
             khung giờ) — xem ProductDetail::updatedSelectedSlots(). --}}
        @php
            $fmt = fn ($v) => number_format((float) $v, 0, ',', '.');
            $pickedServices = collect($selectedServices ?? [])
                ->map(fn ($qty, $id) => ['item' => $serviceItems->firstWhere('id', $id), 'qty' => (int) $qty])
                ->filter(fn ($r) => $r['item'] && $r['qty'] > 0)->values();
            $pickedEquipment = collect($selectedEquipment ?? [])
                ->map(fn ($qty, $id) => ['item' => $equipmentItems->firstWhere('id', $id), 'qty' => (int) $qty])
                ->filter(fn ($r) => $r['item'] && $r['qty'] > 0)->values();
            $addonsTotal = $pickedServices->merge($pickedEquipment)->sum(fn ($r) => (float) $r['item']->price * $r['qty']);
            $coachTotal = $selectedCoach ? $this->selectedCoachTotal() : 0;
            $guestFee = (float) ($this->extraFee ?? 0);
            $discountLines = collect([
                'Đặt full ngày' => (float) ($this->fullBookingDiscount ?? 0),
                'Giảm giá theo số khung giờ' => (float) ($bulkDiscountAmount ?? 0),
                'Giảm giá khách quen' => (float) ($this->loyaltyDiscountAmount ?? 0),
                'Mã giảm giá' => (float) ($this->couponDiscountAmount ?? 0),
            ])->filter(fn ($v) => $v > 0);
            $courtTotal = max(0, (float) $totalAmount - $addonsTotal - $coachTotal - $guestFee + $discountLines->sum());
        @endphp
        @if (! empty($selectedSlots))
        <div class="mb-3 rounded-xl bg-white p-4 text-sm text-gray-700 shadow-sm">
            <p class="mb-2 flex items-center gap-1.5 font-bold text-gray-900">
                <x-heroicon-o-receipt-percent class="h-4 w-4 text-emerald-600" /> Chi tiết thanh toán
            </p>
            <div class="space-y-1.5">
                <div class="flex justify-between gap-3">
                    <span>Tiền sân ({{ count($selectedSlots) }} khung giờ)</span>
                    @if ($usePackage)
                        <span class="font-semibold text-emerald-700">{{ count($selectedSlots) }} buổi trong gói</span>
                    @else
                        <span class="font-semibold text-gray-900">{{ $fmt($courtTotal) }} đ</span>
                    @endif
                </div>
                @unless ($usePackage)
                    @foreach ($discountLines as $label => $amount)
                        <div class="flex justify-between gap-3 text-emerald-700">
                            <span>{{ $label }}</span><span class="font-semibold">- {{ $fmt($amount) }} đ</span>
                        </div>
                    @endforeach
                    @if ($guestFee > 0)
                        <div class="flex justify-between gap-3">
                            <span>Phụ thu khách thêm</span><span class="font-semibold text-gray-900">+ {{ $fmt($guestFee) }} đ</span>
                        </div>
                    @endif
                @endunless
                @foreach ($pickedServices as $row)
                    <div class="flex justify-between gap-3">
                        <span class="min-w-0 truncate">{{ $row['item']->name }} <span class="text-gray-400">× {{ $row['qty'] }}</span></span>
                        <span class="shrink-0 font-semibold text-gray-900">+ {{ $fmt($row['item']->price * $row['qty']) }} đ</span>
                    </div>
                @endforeach
                @foreach ($pickedEquipment as $row)
                    <div class="flex justify-between gap-3">
                        <span class="min-w-0 truncate">Thuê {{ $row['item']->name }} <span class="text-gray-400">× {{ $row['qty'] }}</span></span>
                        <span class="shrink-0 font-semibold text-gray-900">+ {{ $fmt($row['item']->price * $row['qty']) }} đ</span>
                    </div>
                @endforeach
                @if ($selectedCoach)
                    <div class="flex justify-between gap-3">
                        <span class="min-w-0 truncate">HLV {{ $selectedCoach->name }} <span class="text-gray-400">· {{ $fmt($selectedCoach->hourly_rate) }} đ/giờ × {{ $totalHoursLabel }}</span></span>
                        <span class="shrink-0 font-semibold text-gray-900">+ {{ $fmt($coachTotal) }} đ</span>
                    </div>
                @endif
            </div>
            <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3">
                <span class="font-bold text-gray-900">Tổng thanh toán</span>
                @if ($usePackage)
                    <span class="text-base font-extrabold text-emerald-700">{{ count($selectedSlots) }} buổi trong gói</span>
                @else
                    <span class="text-base font-extrabold text-emerald-700">{{ $fmt($totalAmount) }} đ</span>
                @endif
            </div>
        </div>
        @endif

        {{-- Thông tin khách hàng --}}
        <div class="mb-2">
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-white/80">Tên của bạn</label>
            <input type="text" wire:model="buyerName" placeholder="Nhập họ tên"
                   class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
        </div>

        <div class="mb-2">
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-white/80">Số điện thoại</label>
            {{-- .live.debounce: nhập xong SĐT phải tự tính lại "Tổng tiền" NGAY trên màn này (xem
                 updatedBuyerPhone() trong ProductDetail.php) — giảm giá khách quen
                 (getLoyaltyDiscountRate()) chỉ áp dụng được SAU khi biết SĐT, trước đây field này
                 dùng wire:model thường (deferred) nên tổng tiền hiện ở đây/thanh CTA đáy màn vẫn là
                 số CHƯA trừ giảm giá cho tới lúc bấm "Xác nhận & thanh toán" — khách bị thu tiền
                 khác số vừa nhìn thấy ngay trước đó, dù số tiền thu mới là số ĐÚNG. --}}
            <input type="tel" wire:model.live.debounce.500ms="buyerPhone" placeholder="Nhập số điện thoại"
                   class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
        </div>

        <div class="mb-3">
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-white/80">Ghi chú cho chủ sân</label>
            <textarea wire:model="note" rows="2" placeholder="Nhập ghi chú (không bắt buộc)"
                      class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white"></textarea>
        </div>

        {{-- Trả bằng gói nhiều buổi đã mua — ĐÃ có sẵn logic backend từ trước (ProductDetail::
             getAvailablePackage()/confirmBooking(), gate ĐÚNG bookingStyle===1 tức là luồng đặt
             theo khung giờ dùng CHÍNH màn hình này), nhưng trước đây chỉ có UI toggle ở
             infomation-book-room.blade.php (style=2, đặt theo ngày) — màn hình style=1 thật sự
             đang dùng lại KHÔNG có ô "Dùng gói" nào để bấm, dù backend đã hỗ trợ đầy đủ. buyerPhone
             tự điền từ tài khoản đăng nhập qua prefillFromAuth() (x-init phía trên) nên khối này tự
             hiện ra ngay khi khách đã đăng nhập, không cần gõ lại số điện thoại. --}}
        @php $availablePackage = $this->getAvailablePackage(); @endphp
        @if ($availablePackage)
        <div class="mb-3 rounded-xl bg-emerald-400/15 border-2 border-emerald-300/60 p-3.5">
            <label class="flex items-start gap-2.5 cursor-pointer select-none">
                <input type="checkbox" wire:model.live="usePackage"
                    class="mt-0.5 h-4 w-4 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer shrink-0" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-white">
                        Dùng gói "{{ $availablePackage->name }}" — còn {{ $availablePackage->sessions_remaining }} buổi
                    </p>
                    <p class="text-xs text-emerald-100 mt-0.5">
                        Mỗi khung giờ đã chọn trừ đúng 1 buổi trong gói, không cần thanh toán thêm.
                        @if ($availablePackage->category_id)
                            Áp dụng cho mọi sân tại {{ $availablePackage->category?->name }}.
                        @endif
                        @if ($availablePackage->expires_at)
                            Hết hạn {{ $availablePackage->expires_at->format('d/m/Y') }}.
                        @endif
                    </p>
                    @if ($usePackage && count($selectedSlots) > $availablePackage->sessions_remaining)
                        <p class="text-xs text-red-200 font-semibold mt-1">
                            ⚠ Bạn đang chọn {{ count($selectedSlots) }} khung giờ nhưng gói chỉ còn {{ $availablePackage->sessions_remaining }} buổi — bỏ bớt khung giờ hoặc bỏ chọn dùng gói.
                        </p>
                    @endif
                </div>
            </label>
        </div>
        @endif

        {{-- Chọn phương thức thanh toán — CÙNG điều kiện/logic với infomation-book-room.blade.php
             (sân đặt theo NGÀY, style=2) nhưng đây là luồng sân đặt theo KHUNG GIỜ (style=1, dùng
             component riêng này) — bug đã gặp: chỉ thêm ở 1 trong 2 luồng khiến người dùng không
             thấy lựa chọn khi test đúng luồng style=1 (Sân BOSS, đặt theo giờ). Chỉ hiện khi ĐÚNG
             đối tác của sân này đã cấu hình đủ ngân hàng (Partner::hasBankTransferInfo()). --}}
        {{-- Phương thức thanh toán KHÔNG cho khách tự chọn nữa — do ADMIN quyết định qua
             Partner::effectivePaymentMethod() (cùng logic với infomation-book-room.blade.php). --}}
        @php $bookingEffectiveMethod = $this->product->partner?->effectivePaymentMethod() ?? 'payos'; @endphp
        {{-- Ẩn banner "chuyển khoản ngân hàng" khi khách chọn Dùng gói — không có khoản tiền nào
             cần chuyển khoản trong trường hợp này. --}}
        @if ($bookingEffectiveMethod === 'bank_transfer' && ! $usePackage)
        <div class="mb-3 rounded-xl border-2 border-white/30 bg-white/10 px-4 py-2.5 flex items-start gap-2.5">
            <svg class="w-4 h-4 text-white shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8h6m-5 4h4m1 8H8a2 2 0 01-2-2V6a2 2 0 012-2h5.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V18a2 2 0 01-2 2z" /></svg>
            <p class="text-sm text-white">Thanh toán bằng <span class="font-semibold">chuyển khoản ngân hàng</span> — bạn sẽ nhận mã QR sau khi xác nhận.</p>
        </div>
        @endif

        <div class="mb-3 rounded-xl border-2 border-dashed border-amber-300 bg-amber-400/10 px-4 py-2.5 text-xs text-amber-200">
            ⚠️ Lưu ý: vui lòng kiểm tra kỹ khung giờ trước khi xác nhận. Sau khi thanh toán, sân sẽ được giữ chỗ cho bạn.
        </div>

        @if ($bookingConfirmError)
            <div class="mb-3 flex items-start gap-2 rounded-xl border border-red-300 bg-red-50 px-3 py-2.5">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                <p class="text-sm font-medium text-red-700">{{ $bookingConfirmError }}</p>
            </div>
        @endif
    </div>

    {{-- Thanh tổng tiền + nút xác nhận CỐ ĐỊNH ĐÁY MÀN HÌNH — tái dùng đúng kiểu thanh CTA của
         trang chọn khung giờ (book/_timeline.blade.php: .book-tl-cta-bar) để luôn thấy tổng tiền +
         nút thanh toán kể cả khi cuộn qua danh sách dịch vụ/ghi chú dài, đồng bộ trải nghiệm mobile
         giữa 2 bước liên tiếp trong luồng đặt sân. Trượt lên từ dưới lúc vào trang (x-show + Alpine
         transition) giống hiệu ứng thanh CTA của book/_timeline.blade.php, thay vì hiện tĩnh. --}}
    <div x-data="{ show: false }" x-init="setTimeout(() => show = true, 50)"
         x-show="show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         class="sticky bottom-0 left-0 right-0 z-20 shadow-[0_-4px_16px_rgba(0,0,0,.12)]">
        <div class="flex items-center justify-between px-4 py-2.5 sm:px-6 text-sm font-semibold text-white" style="background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);">
            <span>Tổng giờ: <b>{{ $totalHoursLabel }}</b></span>
            @if ($usePackage)
                <span>Thanh toán: <b>{{ count($selectedSlots) }} buổi trong gói</b></span>
            @else
                <span>Tổng tiền: <b>{{ number_format((float) $totalAmount, 0, ',', '.') }} đ</b></span>
            @endif
        </div>
        <button type="button" wire:click="confirmBooking" wire:loading.attr="disabled" wire:target="confirmBooking"
                @if (empty($selectedSlots)) disabled @endif
                class="flex w-full items-center justify-center gap-2 bg-[#F5A623] py-3.5 sm:py-4 text-sm font-extrabold uppercase tracking-wide text-gray-900 hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-50">
            <svg wire:loading wire:target="confirmBooking" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span wire:loading.remove wire:target="confirmBooking">{{ $usePackage ? 'Xác nhận dùng gói' : 'Xác nhận & thanh toán' }}</span>
            <span wire:loading wire:target="confirmBooking">Đang xử lý...</span>
        </button>
    </div>
</div>
