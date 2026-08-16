{{--
    Mobile/tablet two-panel booking grid (1 sân/lần, carousel prev-next) — desktop (≥lg) dùng
    book/_desktop-grid.blade.php hiển thị tất cả sân cùng lúc thay cho khối này.
    Đổi trục theo yêu cầu (giống desktop + product-detail.blade.php): KHUNG GIỜ đứng dọc bên trái
    (riêng từng sân, vì khung giờ có thể khác nhau giữa các sân), NGÀY nằm ngang ở header trên
    cùng (dùng CHUNG, ngày giống nhau cho mọi sân), cuộn ngang xem thêm (kéo/trượt HOẶC bấm nút
    Trước/Sau) — KHÔNG còn hệ thống phân trang "slotPage" cũ (dựng cho bản khung-giờ-ngang trước
    đây, không cần nữa). Cột khung giờ vẫn hiện ĐẦY ĐỦ không giới hạn chiều cao (như cách cột Ngày
    cũ đã làm) — người dùng cuộn cả trang theo chiều dọc như trước, chỉ chiều ngang (Ngày) mới có
    khung cuộn riêng.
    Included from book.blade.php — inherits:
      $dates, $styleOneRooms, $totalStyleOneRooms, $today, $category
--}}
@php
    // Màu viền theo sân (color_product) cho cột "Khung giờ"/header dùng chung — phải đổi theo sân
    // đang active (bind qua Alpine :style bên dưới, xem book/_desktop-grid.blade.php cho cùng cách làm).
    $roomColorList = $styleOneRooms->map(fn ($r) => $productColors[$r->id]['color'] ?? null)->values()->all();
@endphp
<div class="lg:hidden" x-data="{
    activeRoomIdx: 0, totalRooms: {{ $totalStyleOneRooms }},
    roomColors: {{ \Illuminate\Support\Js::from($roomColorList) }},
    categorySlug: '{{ \Str::slug($category['name']) }}',
    touchStartX: 0,
    changeRoom(dir) {
        const newIdx = this.activeRoomIdx + dir;
        if (newIdx >= 0 && newIdx < this.totalRooms) {
            this.activeRoomIdx = newIdx;
            return;
        }
        // Cross-branch: navigate to neighbouring tab when at the boundary
        const tabs = [...document.querySelectorAll('#default-styled-tab button')];
        const currentTabIdx = tabs.findIndex(t => t.id === 'styled-' + this.categorySlug + '-tab');
        const nextTabIdx = currentTabIdx + (dir > 0 ? 1 : -1);
        if (nextTabIdx >= 0 && nextTabIdx < tabs.length) {
            tabs[nextTabIdx].click();
            window.dispatchEvent(new CustomEvent('book-activate-room', {
                detail: { tabId: tabs[nextTabIdx].id, fromEnd: dir < 0 }
            }));
            return;
        }
        // Vòng lặp: hết sân cuối/đầu của chi nhánh này và không còn tab kế bên để nhảy sang
        // (chỉ 1 tab, hoặc đã ở tab đầu/cuối) — quay lại sân đầu tiên/cuối cùng của chi nhánh.
        if (this.totalRooms > 1) {
            this.activeRoomIdx = dir > 0 ? 0 : this.totalRooms - 1;
        }
    },
    onTouchStart(e) { this.touchStartX = e.touches[0].clientX; },
    // Ngưỡng 25px (giảm từ 40px) — nhạy hơn, chỉ cần vuốt 1 đoạn ngắn là đổi sân.
    onTouchEnd(e) {
        const diff = this.touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 25) { this.changeRoom(diff > 0 ? 1 : -1); }
    }
}"
    @touchstart.passive="onTouchStart($event)"
    @touchend.passive="onTouchEnd($event)"
    @book-activate-room.window="
        if ($event.detail.tabId === 'styled-' + categorySlug + '-tab') {
            activeRoomIdx = $event.detail.fromEnd ? totalRooms - 1 : 0;
        }
    "
>

    <div class="book-card-outer">

    {{-- ── Room carousel header — trải rộng hết chiều ngang (w-full). Tên sân bên trái, 2 nút
         trượt gộp gần nhau bên phải cùng hàng (thay vì mỗi nút 1 đầu, cách xa nhau như trước). ── --}}
    <div class="book-top-row">
        <div class="book-room-nav-wrap w-full">
            <div class="book-room-titles-wrap">
                @foreach ($styleOneRooms as $ri => $roomTitle)
                @php
                    // Sân đang có khuyến mãi giảm giá (percentage/fixed) hiệu lực ngay bây giờ ở
                    // bất kỳ khung giờ nào — chỉ để hiện icon flash-sale trang trí cạnh tên sân,
                    // không cần khớp chính xác từng ngày/giờ như calculateSlotPrice() (dùng khi tính
                    // giá thật cho từng ô lịch).
                    $roomHasDiscount = $roomTitle->roomTimeSlots->contains(
                        fn ($rts) => $rts->promotions->contains(
                            fn ($p) => in_array($p->type, ['percentage', 'fixed'])
                                && $p->is_active
                                && \Carbon\Carbon::parse($p->start_at)->lte(now())
                                && \Carbon\Carbon::parse($p->end_at)->gte(now())
                        )
                    );
                @endphp
                <div x-show="activeRoomIdx === {{ $ri }}"
                    class="book-room-title-block">
                    <h3 class="book-room-name">
                        {{ $roomTitle->name }}
                        @if($roomHasDiscount)
                            <img src="{{ asset('images/flash-sale.gif') }}" alt="" class="book-room-flash-icon">
                        @endif
                    </h3>
                </div>
                @endforeach
            </div>
            <div class="book-nav-btn-group">
                <button class="book-nav-btn book-nav-btn-labeled" type="button"
                    @click="changeRoom(-1)"
                    aria-label="Sân trước">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span>Trước</span>
                </button>
                <button class="book-nav-btn book-nav-btn-labeled" type="button"
                    @click="changeRoom(1)"
                    aria-label="Sân tiếp">
                    <span>Sau</span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Header NGÀY dùng CHUNG cho mọi sân (ngày giống nhau) — nút Trước/Sau nổi ĐÈ LÊN rìa
         wrapper (position:absolute, không chiếm chỗ flex layout) để không làm header rộng hơn
         lưới ô bên dưới (đúng lỗi lệch cột đã gặp và sửa ở nơi khác — pd-dt-*/tsgrid-*/ta-rc-cal-*).
         scrollBy() thẳng lên lưới của sân ĐANG ACTIVE (activeRoomIdx) — sân khác (đang ẩn) tự nhận
         cùng vị trí cuộn qua @scroll khi tới lượt hiện ra (xem body bên dưới). ── --}}
    <div class="book-grid-header">
        <div class="book-col-header"
            :style="roomColors[activeRoomIdx] ? `--room-border-color:${roomColors[activeRoomIdx]}` : ''">Khung giờ</div>
        <div class="book-dates-header-wrap">
            <div class="book-date-nav-row">
                <button type="button" class="book-date-nav-btn book-date-nav-prev"
                    style="width:22px !important;height:22px !important;min-width:22px !important;min-height:22px !important;"
                    aria-label="Xem ngày trước"
                    @click="$refs['bookSlotsScroll' + activeRoomIdx].scrollBy({ left: -150, behavior: 'smooth' })">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <span class="book-date-nav-hint">Kéo hoặc bấm mũi tên để xem ngày khác</span>
                <button type="button" class="book-date-nav-btn book-date-nav-next"
                    style="width:22px !important;height:22px !important;min-width:22px !important;min-height:22px !important;"
                    aria-label="Xem thêm ngày"
                    @click="$refs['bookSlotsScroll' + activeRoomIdx].scrollBy({ left: 150, behavior: 'smooth' })">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
            <div class="book-slots-header-row" x-ref="bookDatesHeaderScroll">
                @foreach ($dates as $date)
                    @php $mdateShort = \Carbon\Carbon::createFromFormat('d-m-Y', $date['date'])->format('d/m'); @endphp
                    <div class="book-slot-th{{ $date['is_today'] ? ' is-today' : '' }}" style="flex:0 0 calc((100% - 12px) / 5);">
                        <span class="book-slot-time-start">{{ $date['day'] }}</span><br class="book-slot-time-br"><span class="book-slot-time-end">{{ $mdateShort }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Thân: mỗi sân 1 cặp [cột Khung giờ dọc + lưới ô] HOÀN TOÀN TỰ ĐỦ, chỉ hiện đúng sân
         đang active (x-show) — khung giờ khác nhau giữa các sân nên KHÔNG dùng chung 1 cột được
         như cột Ngày ở header trên. ── --}}
    <div class="book-grid-outer">
        @foreach ($styleOneRooms as $ri => $room)
        @php
            $roomConfig = $productColors[$room->id] ?? null;
            $roomBg     = $roomConfig['color'] ?? null;
        @endphp
        <div x-show="activeRoomIdx === {{ $ri }}" x-cloak class="book-room-grid-row">

            {{-- Cột Khung giờ (dọc) — hiện ĐẦY ĐỦ, không giới hạn chiều cao (giống cách cột Ngày
                 cũ đã làm — người dùng cuộn cả trang theo chiều dọc). --}}
            <div class="book-dates-card" style="{{ $roomBg ? "--room-border-color:{$roomBg};" : '' }}">
                @foreach ($room->roomTimeSlots as $roomTimeSlot)
                @php
                    $startTime   = \Carbon\Carbon::parse($roomTimeSlot->timeSlot->start_time);
                    $endTime     = \Carbon\Carbon::parse($roomTimeSlot->timeSlot->end_time);
                    $isOvernight = $endTime->isNextDay() || $endTime->lt($startTime);
                @endphp
                <div class="book-date-row">
                    <span class="book-date-day">{{ $startTime->format('H:i') }}-{{ $endTime->format('H:i') }}</span>
                    @if($isOvernight)
                        <svg class="book-slot-icon" style="color:#1e3a8a" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.7-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162Z" clip-rule="evenodd" />
                        </svg>
                    @else
                        <svg class="book-slot-icon" style="color:#eab308" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 1a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 8 1ZM10.5 8a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM12.95 4.11a.75.75 0 1 0-1.06-1.06l-1.062 1.06a.75.75 0 0 0 1.061 1.062l1.06-1.061ZM15 8a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 15 8ZM11.89 12.95a.75.75 0 0 0 1.06-1.06l-1.06-1.062a.75.75 0 0 0-1.062 1.061l1.061 1.06ZM8 12a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 8 12ZM5.172 11.89a.75.75 0 0 0-1.061-1.062L3.05 11.89a.75.75 0 1 0 1.06 1.06l1.06-1.06ZM4 8a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 4 8ZM4.11 5.172A.75.75 0 0 0 5.173 4.11L4.11 3.05a.75.75 0 1 0-1.06 1.06l1.06 1.06Z" />
                        </svg>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- Lưới ô — cuộn NGANG riêng (ngày), đồng bộ ngược lại header dùng chung ở trên qua
                 @scroll. --}}
            <div class="book-slots-outer">
                <div class="book-slots-card">
                    <div class="book-slots-scroll" x-ref="bookSlotsScroll{{ $ri }}"
                        @scroll="$refs.bookDatesHeaderScroll.scrollLeft = $event.target.scrollLeft">

                    {{-- Render đầy đủ (giá/khuyến mãi) cho MỌI sân, kể cả các sân đang ẩn qua x-show
                         (chưa active) — đã verify chi phí tối đa an toàn qua Livewire::test() (chi nhánh
                         nhiều sân nhất hiện có, 31 ngày, ~100MB, memory_limit hiện tại 512M). Từng thử
                         giới hạn chỉ render sân active để tiết kiệm chi phí, nhưng gây lỗi ô trống khi
                         đổi sân sau khi đã "Xem thêm ngày" (server không đồng bộ kịp sân đang active
                         lúc đổi sân thuần client-side) — ưu tiên đúng giao diện hơn tối ưu này. --}}
                    @foreach ($room->roomTimeSlots as $roomTimeSlot)
                    <div class="book-slots-row">
                        @foreach ($dates as $date)
                        <div class="book-slot-cell" style="flex:0 0 calc((100% - 12px) / 5);">
                            @include('bladethemev1::livewire.book._slot-cell', ['room' => $room, 'date' => $date, 'roomTimeSlot' => $roomTimeSlot])
                        </div>
                        @endforeach
                    </div>
                    @endforeach

                    </div>{{-- end .book-slots-scroll --}}
                </div>
            </div>{{-- end .book-slots-outer --}}
        </div>{{-- end .book-room-grid-row --}}
        @endforeach
    </div>{{-- end .book-grid-outer --}}

    {{-- ── Xem thêm ngày (mỗi lần bấm hiện thêm tối đa 10 ngày kế tiếp, tự ẩn khi đã hiện hết). ── --}}
    @if ($this->visibleDaysCount < \Modules\BladeThemeV1\Livewire\Book::MAX_VISIBLE_DAYS)
        <div class="book-loadmore-row">
            <button type="button" class="book-loadmore-btn" wire:click="loadMoreDates" wire:loading.attr="disabled" wire:target="loadMoreDates">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
                <span>Xem thêm {{ min(\Modules\BladeThemeV1\Livewire\Book::LOAD_MORE_DAYS_STEP, \Modules\BladeThemeV1\Livewire\Book::MAX_VISIBLE_DAYS - $this->visibleDaysCount) }} ngày</span>
            </button>
        </div>
    @endif

    </div>{{-- end .book-card-outer --}}
</div>{{-- end x-data room carousel --}}
