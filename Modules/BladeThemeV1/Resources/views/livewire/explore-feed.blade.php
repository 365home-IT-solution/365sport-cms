<div class="mx-auto max-w-2xl px-4 py-4 pb-24 lg:max-w-5xl">
    <h1 class="mb-4 text-center text-xl font-extrabold uppercase tracking-wide lg:text-left lg:text-2xl" style="color:#0B6B3A;">Khám phá</h1>

    {{-- Sticky ngay dưới top-bar/search-bar — trang khám phá kiểu feed dài, cuộn xuống vẫn cần đổi
         tab được ngay chứ không phải cuộn ngược lên trên cùng (mẫu chuẩn của các trang "Explore"). --}}
    <div class="sticky top-0 z-10 -mx-4 mb-4 flex gap-2 overflow-x-auto bg-gray-50/95 px-4 py-2 backdrop-blur-sm lg:static lg:mx-0 lg:bg-transparent lg:px-0 lg:py-0 lg:backdrop-blur-none">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')"
                class="whitespace-nowrap rounded-full border px-4 py-1.5 text-sm font-semibold transition
                    {{ $activeTab === $key ? 'border-[#0B6B3A] bg-[#0B6B3A] text-white' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-5">
        @forelse ($this->filteredItems as $item)
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center gap-3 p-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-gray-100">
                        @if ($item['logo'])
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['logo']) }}" alt="" class="h-full w-full object-contain">
                        @else
                            <x-heroicon-o-building-storefront class="h-5 w-5 text-gray-400" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-gray-900">{{ $item['author'] }}</p>
                        @if ($item['date'])
                            <p class="text-xs text-gray-400">{{ $item['date'] }}</p>
                        @endif
                    </div>
                </div>

                @if ($item['type'] === 'promo')
                    <div class="mx-3 mb-3 flex items-center gap-3 rounded-lg bg-gradient-to-br from-[#0B6B3A] to-[#1FB868] p-4 text-white">
                        <span class="text-3xl leading-none">{{ $item['emoji'] }}</span>
                        <div>
                            <p class="font-extrabold">{{ $item['title'] }}</p>
                            <p class="text-sm text-white/90">{{ $item['summary'] }}</p>
                        </div>
                    </div>
                @else
                    @if ($item['image_url'])
                        <img src="{{ $item['image_url'] }}" alt="" class="max-h-64 w-full object-cover">
                    @endif
                    <div class="px-3 pb-1 pt-2">
                        <p class="font-bold text-gray-900">{{ $item['title'] }}</p>
                        @if ($item['summary'])
                            <p class="mt-1 line-clamp-2 text-sm text-gray-500">{{ $item['summary'] }}</p>
                        @endif
                    </div>
                @endif

                <div class="flex items-center justify-end gap-2 px-3 pb-3 pt-1">
                    <a href="{{ $item['url'] }}"
                       class="rounded-full border border-gray-200 px-4 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        Xem chi tiết
                    </a>
                </div>
            </div>
        @empty
            <p class="py-10 text-center text-sm text-gray-400 lg:col-span-2">Chưa có nội dung nào.</p>
        @endforelse
    </div>
</div>
