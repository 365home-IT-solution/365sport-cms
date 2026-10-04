{{--
    Trang chi tiết sân: 3 khối RIÊNG — Dịch vụ thêm (selectedServices), Thuê vật tư (selectedEquipment,
    giới hạn tồn kho) và Huấn luyện viên (selectedCoachId) — cùng nguồn dữ liệu/cùng method Livewire
    với trang xác nhận đặt lịch (booking-checkout.blade.php). Khối nào chi nhánh không có thì ẩn hẳn.
--}}
@php
    $pdAllAddons = $additionalServices ?? collect();
    $pdServices = $pdAllAddons->filter(fn ($s) => ! $s->warehouseItem)->values();
    $pdEquipment = $pdAllAddons->filter(fn ($s) => (bool) $s->warehouseItem)->values();
    $pdCoaches = $coaches ?? collect();
    $pdMoney = fn ($v) => number_format((float) $v, 0, ',', '.') . "\u{00A0}đ";
    $pdSections = [
        ['key' => 'services', 'title' => 'Dịch vụ thêm', 'rows' => $pdServices, 'picked' => $selectedServices ?? [], 'inc' => 'incrementService', 'dec' => 'decrementService', 'error' => 'services'],
        ['key' => 'equipment', 'title' => 'Thuê vật tư', 'rows' => $pdEquipment, 'picked' => $selectedEquipment ?? [], 'inc' => 'incrementEquipment', 'dec' => 'decrementEquipment', 'error' => 'equipment'],
    ];
@endphp

@foreach ($pdSections as $section)
    @if ($section['rows']->isNotEmpty())
    <div class="space-y-4 {{ $loop->first ? '' : 'mt-8' }}" x-data="{ showAll: false }">
        <h2 class="text-[22px] font-semibold text-[#222222]">{{ $section['title'] }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
            @foreach ($section['rows'] as $index => $service)
                @php
                    $qty = (int) ($section['picked'][$service->id] ?? 0);
                    $availableQty = $section['key'] === 'equipment' ? (int) ($service->warehouseItem?->quantity_reserve ?? 0) : null;
                    $isOutOfStock = $availableQty !== null && $availableQty <= 0;
                @endphp
                <div wire:key="pd-{{ $section['key'] }}-{{ $service->id }}" class="flex items-start gap-3 py-3"
                     @if ($index >= 4) x-show="showAll" x-cloak @endif>
                    <div class="shrink-0 h-9 w-9 flex items-center justify-center text-[#222222]">
                        @if (!empty($service->image))
                            <div wire:ignore><img src="{{ asset('storage/' . $service->image) }}" alt="{{ $service->name }}" class="h-9 w-9 object-contain"></div>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-9 w-9"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="text-base text-[#222222] truncate">{{ $service->name }}</p>
                        <p class="text-sm text-[#717171] mt-0.5">{{ $pdMoney($service->price) }} / lần</p>
                        @if ($availableQty !== null)
                            <p class="text-xs mt-0.5 {{ $isOutOfStock ? 'text-red-500' : 'text-[#717171]' }}">
                                {{ $isOutOfStock ? 'Hết vật tư cho thuê' : 'Còn ' . $availableQty . ' sản phẩm' }}
                            </p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" wire:click="{{ $section['dec'] }}({{ $service->id }})" @disabled($qty <= 0)
                                class="h-7 w-7 rounded-full border flex items-center justify-center transition-colors {{ $qty > 0 ? 'border-primary text-primary hover:bg-primary hover:text-white' : 'border-[#B0B0B0] text-[#B0B0B0] cursor-not-allowed' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                        <span class="text-base tabular-nums w-5 text-center text-[#717171]">{{ $qty }}</span>
                        @php $pdIncDisabled = $availableQty !== null && $qty >= $availableQty; @endphp
                        <button type="button" wire:click="{{ $section['inc'] }}({{ $service->id }})" @disabled($pdIncDisabled)
                                class="h-7 w-7 rounded-full border flex items-center justify-center transition-colors {{ $pdIncDisabled ? 'border-[#B0B0B0] text-[#B0B0B0] cursor-not-allowed' : 'border-primary text-primary hover:bg-primary hover:text-white' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        @error($section['error'])<p class="text-sm text-red-500">{{ $message }}</p>@enderror

        @if ($section['rows']->count() > 4)
            <button type="button" @click="showAll = !showAll"
                    class="inline-flex items-center justify-center gap-1.5 rounded-lg px-5 h-10 text-sm font-semibold border border-primary text-primary hover:bg-primary hover:text-white transition-colors">
                <span x-text="showAll ? 'Thu gọn' : 'Hiển thị tất cả {{ $section['rows']->count() }} mục'"></span>
            </button>
        @endif
    </div>
    @endif
@endforeach

{{-- Huấn luyện viên — chỉ HLV của đúng chi nhánh, đã loại HLV có lịch trùng khung giờ đang chọn ở sân khác. --}}
@if ($pdCoaches->isNotEmpty())
<div class="space-y-4 {{ $pdServices->isNotEmpty() || $pdEquipment->isNotEmpty() ? 'mt-8' : '' }}" x-data="{ detail: null }">
    <h2 class="text-[22px] font-semibold text-[#222222]">Huấn luyện viên</h2>
    <p class="text-sm text-[#717171]">Tính theo giờ, đi kèm đúng các khung giờ sân bạn chọn.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
        @foreach ($pdCoaches as $coach)
            @php $pdInitial = mb_strtoupper(mb_substr(trim(preg_replace('/^\[[^\]]*\]\s*/u', '', $coach->name)) ?: $coach->name, 0, 1)); @endphp
            <div wire:key="pd-coach-{{ $coach->id }}" class="flex items-center gap-3 py-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-100 font-bold text-emerald-700">
                    @if ($coach->photo)<img src="{{ asset('storage/' . $coach->photo) }}" class="h-full w-full object-cover" alt="{{ $coach->name }}">@else{{ $pdInitial }}@endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-base text-[#222222]">{{ $coach->name }}</p>
                    <p class="truncate text-xs text-[#717171]">{{ $coach->specialties ?: 'Huấn luyện theo yêu cầu' }}</p>
                    <p class="text-sm text-[#717171]">{{ $pdMoney($coach->hourly_rate) }} / giờ</p>
                </div>
                <div class="flex shrink-0 flex-col gap-1.5">
                    <button type="button" @click="detail = {{ json_encode($coach->only(['name', 'specialties', 'certifications', 'bio', 'years_experience', 'hourly_rate'])) }}"
                            class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600">Chi tiết</button>
                    @if ((int) $selectedCoachId === (int) $coach->id)
                        <button type="button" wire:click="removeCoach" class="rounded-lg bg-red-50 px-2.5 py-1 text-xs font-bold text-red-600">Bỏ chọn</button>
                    @else
                        <button type="button" wire:click="selectCoach({{ $coach->id }})" class="rounded-lg border border-primary px-2.5 py-1 text-xs font-bold text-primary hover:bg-primary hover:text-white">Chọn</button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @error('coach')<p class="text-sm text-red-500">{{ $message }}</p>@enderror

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
