{{--
    Banner quảng cáo full-width dưới thanh tìm kiếm (giống khối "ALOBO ACADEMY" ở app tham khảo).
    Chưa có cơ chế quản trị banner riêng cho theme này nên dùng logo + tên site hiện có
    (GeneralSettings) dựng thành 1 banner tĩnh, gradient xanh khớp bảng màu trang chủ, có thêm vài
    icon môn thể thao trang trí cho đỡ trống (không có ảnh minh hoạ thật như bản mẫu) — không phải
    slide/carousel thật; có thể thay bằng @livewire('bladethemev1::slide-component') sau nếu cấu
    hình banner CMS cho theme này.
--}}
@php
    $generalSettings = new \App\Settings\GeneralSettings();
    $siteName = config('app.name', 'ALO Booking');
    $logo = $generalSettings->brand_logo ?? null;
@endphp

<div class="mx-auto max-w-7xl px-4 py-4 sm:px-6">
    <div class="relative overflow-hidden rounded-2xl px-6 py-8 sm:px-10 sm:py-10"
         style="background: linear-gradient(120deg, #0B6B3A 0%, #16924F 45%, #1FB868 100%);">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 22px 22px;"></div>

        {{-- Icon môn thể thao trang trí, mờ, giống các vận động viên minh hoạ ở banner mẫu --}}
        <span class="pointer-events-none absolute -bottom-4 right-16 hidden text-7xl opacity-15 sm:block">🏸</span>
        <span class="pointer-events-none absolute -top-6 right-48 hidden text-6xl opacity-15 sm:block">🎾</span>
        <span class="pointer-events-none absolute bottom-2 right-2 hidden text-8xl opacity-10 md:block">⚽</span>

        <div class="relative flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                @if ($logo)
                    <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($logo) }}" alt="{{ $siteName }}" class="h-10 w-10 rounded-full bg-white object-contain p-1 sm:h-14 sm:w-14">
                @endif
                <div>
                    <span class="mb-1 inline-block rounded-full bg-red-500 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white">
                        Ưu đãi mới
                    </span>
                    <p class="text-xl font-extrabold uppercase leading-tight tracking-wide text-white sm:text-3xl">{{ $siteName }}</p>
                    <p class="text-sm font-medium text-white/90 sm:text-base">Đặt sân thể thao nhanh chóng, ưu đãi mỗi ngày</p>
                </div>
            </div>

            {{-- Toàn bộ lọc/duyệt sân đã gộp về trang bản đồ /ban-do — không còn route('product.search')
                 = /s nữa ở bất kỳ đâu (xem comment đầu components/home/search-bar.blade.php). --}}
            <a href="{{ route('map.page') }}"
               class="relative shrink-0 rounded-full bg-white px-6 py-2.5 text-sm font-bold text-[#0B6B3A] shadow-sm transition hover:bg-white/90">
                Đặt sân ngay
            </a>
        </div>
    </div>
</div>
