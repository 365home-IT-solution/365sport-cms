{{--
    Desktop (≥lg): đổi trục theo yêu cầu — KHUNG GIỜ đứng dọc (mỗi sân có thể có khung giờ khác
    nhau nên cột này nằm TRONG từng slide, không dùng chung được), NGÀY nằm ngang dùng chung 1
    hàng header DUY NHẤT phía trên carousel (ngày giống nhau cho mọi sân) — chỉ hiện 5 ngày/lần,
    cuộn ngang xem thêm, đồng bộ với mọi sân kể cả sân đang "peek" chưa active.
    Chỉ khối khung giờ của từng sân mới trượt (Swiper): 2 sân hiển thị đầy đủ cùng lúc, cạnh
    nhau, cả 2 đều chọn được khung giờ. Bấm mũi tên Trước/Sau (hoặc vuốt) chỉ trượt 1 sân mỗi
    lần (slidesPerGroup: 1) — không nhảy cả cặp 2 sân.
    Mobile (< lg) vẫn dùng book/_mobile.blade.php (carousel 1 sân kiểu cũ, không đổi).
    Inherits: $dates, $styleOneRooms, $today, $category
--}}
<div class="hidden lg:block book-dt-wrap"
    x-data="{}"
    x-init="$nextTick(() => window.mountBookDtSwiper($el))">

    <div class="book-dt-carousel-head">
        <button type="button" class="book-nav-btn book-dt-nav-prev book-nav-btn-labeled book-dt-nav-btn-lg" aria-label="Sân trước">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            <span>Trước</span>
        </button>
        <div class="book-dt-pagination"></div>
        <button type="button" class="book-nav-btn book-dt-nav-next book-nav-btn-labeled book-dt-nav-btn-lg" aria-label="Sân tiếp">
            <span>Sau</span>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- Header NGÀY dùng chung — đứng yên phía trên carousel (KHÔNG trượt theo sân), chỉ hiện 5
         ngày/lần (xem .book-dt-dates-header-row max-width), cuộn ngang đồng bộ với body của MỌI
         sân (kể cả sân chưa hiện ra) qua Alpine bên dưới. --}}
    <div class="book-dt-shared-header">
        <div class="book-dt-col-header">Khung giờ</div>
        {{-- Nút Trước/Sau nổi ĐÈ LÊN rìa wrapper (position:absolute), KHÔNG phải flex-sibling của
             header-row — nếu là flex-sibling, nút cộng thêm bề rộng vào hàng này khiến nó rộng
             hơn thân bảng bên dưới (không có nút tương ứng) → lệch cột (đúng lỗi đã gặp). Bấm
             scrollBy() trên body sân BẤT KỲ đang có trong swiper (chỉ cần 1 — cơ chế đồng bộ sẵn
             có trong @scroll của .book-dt-slots-scroll sẽ tự lan ra header dùng chung + toàn bộ
             sân khác, xem bên dưới) — không cần biết sân nào đang active. --}}
        <div class="book-dt-dates-header-wrap">
            <button type="button" class="book-dt-date-nav-btn book-dt-date-nav-prev" aria-label="Xem ngày trước"
                @click="$refs.bookDtSwiperEl.querySelector('.book-dt-slots-scroll')?.scrollBy({ left: -430, behavior: 'smooth' })">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div class="book-dt-dates-header-row" x-ref="bookDtDatesHeaderScroll">
                @foreach ($dates as $date)
                    @php $dateShort = \Carbon\Carbon::createFromFormat('d-m-Y', $date['date'])->format('d-m-Y'); @endphp
                    <div class="book-dt-date-th{{ $date['is_today'] ? ' is-today' : '' }}">
                        <div class="font-extrabold">{{ $date['day'] }}</div>
                        <div class="text-[10px] text-gray-500 font-normal">{{ $dateShort }}</div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="book-dt-date-nav-btn book-dt-date-nav-next" aria-label="Xem thêm ngày"
                @click="$refs.bookDtSwiperEl.querySelector('.book-dt-slots-scroll')?.scrollBy({ left: 430, behavior: 'smooth' })">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </div>
    <!-- <p class="text-[11px] text-gray-400 mb-2">Kéo ngang hoặc bấm mũi tên để xem thêm ngày khác</p> -->

    <div class="book-dt-body">
        {{-- Carousel 2 sân/hàng: mỗi slide là tên sân + cột khung giờ RIÊNG (dọc) + lưới ngày
             (ngang, khớp đúng thứ tự với header Ngày dùng chung phía trên). --}}
        <div class="book-dt-carousel-col">
            <div class="swiper book-dt-swiper" x-ref="bookDtSwiperEl">
                <div class="swiper-wrapper">
                    @foreach ($styleOneRooms as $room)
                        @php
                            // Sân đang có khuyến mãi giảm giá (percentage/fixed) hiệu lực ngay bây
                            // giờ ở bất kỳ khung giờ nào — chỉ để hiện icon flash-sale trang trí cạnh
                            // tên sân, không cần khớp chính xác từng ngày/giờ như calculateSlotPrice()
                            // (dùng khi tính giá thật cho từng ô lịch).
                            $roomHasDiscount = $room->roomTimeSlots->contains(
                                fn ($rts) => $rts->promotions->contains(
                                    fn ($p) => in_array($p->type, ['percentage', 'fixed'])
                                        && $p->is_active
                                        && \Carbon\Carbon::parse($p->start_at)->lte(now())
                                        && \Carbon\Carbon::parse($p->end_at)->gte(now())
                                )
                            );

                            // Màu viền theo sân (color_product) — chỉ tô viền cột khung giờ
                            // (book-dt-slots-label-card), không đụng tên sân hay khối ô lịch
                            // (book-dt-slots-card), không đụng .selectable để không phá màu trạng
                            // thái đặt/pending.
                            $roomConfig = $productColors[$room->id] ?? null;
                            $roomBg     = $roomConfig['color'] ?? null;
                        @endphp
                        <div class="swiper-slide">
                            <h3 class="book-room-name book-dt-room-name">
                                {{ $room->name }}
                                @if($roomHasDiscount)
                                    <img src="{{ asset('images/flash-sale.gif') }}" alt="" class="book-room-flash-icon">
                                @endif
                            </h3>

                            <div class="book-dt-slide-row">
                                {{-- Cột Khung giờ (dọc) — riêng cho sân này (khung giờ có thể khác
                                     nhau giữa các sân), cuộn dọc đồng bộ với body của CHÍNH sân này. --}}
                                {{-- width/flex-shrink đặt inline (không chỉ dựa class) — đã gặp trường hợp
                                     class riêng trong <style> nhúng không được Livewire áp đúng cho phần tử
                                     (xem ghi chú tương tự ở Payment/timeslot-grid-table.blade.php), làm lệch
                                     cột so với header Ngày. Inline luôn thắng bất kể cascade. --}}
                                <div class="book-dt-slots-label-card" style="width:90px;flex-shrink:0;{{ $roomBg ? "--room-border-color:{$roomBg};" : '' }}">
                                    <div class="book-dt-slots-label-scroll" x-ref="bookDtLabelScroll{{ $loop->index }}"
                                        @scroll="$refs['bookDtSlotsScroll' + {{ $loop->index }}].scrollTop = $event.target.scrollTop">
                                        @foreach ($room->roomTimeSlots as $roomTimeSlot)
                                            @php
                                                $startTime   = \Carbon\Carbon::parse($roomTimeSlot->timeSlot->start_time);
                                                $endTime     = \Carbon\Carbon::parse($roomTimeSlot->timeSlot->end_time);
                                                $isOvernight = $endTime->isNextDay() || $endTime->lt($startTime);
                                            @endphp
                                            <div class="book-dt-time-row">
                                                <div class="font-extrabold text-[10px]">
                                                    {{ $startTime->format('H:i') }} - {{ $endTime->format('H:i') }}
                                                </div>
                                                @if ($isOvernight)
                                                    <div class="text-[9px] flex items-center gap-0.5" style="color:#1e3a8a">
                                                        <svg class="w-3 h-3 inline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 0 1 .162.819A8.97 8.97 0 0 0 9 6a9 9 0 0 0 9 9 8.97 8.97 0 0 0 3.463-.69.75.75 0 0 1 .981.98 10.503 10.503 0 0 1-9.694 6.46c-5.799 0-10.5-4.7-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 0 1 .818.162Z" clip-rule="evenodd" />
                                                        </svg>
                                                        Qua đêm
                                                    </div>
                                                @else
                                                    <div class="text-[9px] text-gray-500">Trong ngày</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="book-dt-slots-card">
                                    <div class="book-dt-slots-scroll" x-ref="bookDtSlotsScroll{{ $loop->index }}"
                                        @scroll="
                                            $refs['bookDtLabelScroll' + {{ $loop->index }}].scrollTop = $event.target.scrollTop;
                                            $refs.bookDtDatesHeaderScroll.scrollLeft = $event.target.scrollLeft;
                                            $refs.bookDtSwiperEl.querySelectorAll('.book-dt-slots-scroll').forEach(el => { if (el !== $event.target) el.scrollLeft = $event.target.scrollLeft; });
                                        ">
                                        {{-- Render đầy đủ (giá/khuyến mãi) cho MỌI sân, kể cả các sân
                                             "peek" 2 bên chưa active — đã verify chi phí tối đa (12 sân
                                             x 31 ngày, chi nhánh nhiều sân nhất hiện có) an toàn qua
                                             Livewire::test() (~100MB, memory_limit hiện tại 512M). Từng
                                             thử giới hạn chỉ render sân active để tiết kiệm chi phí,
                                             nhưng gây lỗi ô trống khi đổi sân sau khi đã "Xem thêm ngày"
                                             (activeRoomIndex phía server không đồng bộ kịp lúc đổi sân
                                             thuần client-side) — ưu tiên đúng giao diện hơn tối ưu này. --}}
                                        @foreach ($room->roomTimeSlots as $roomTimeSlot)
                                            <div class="book-dt-slots-row">
                                                @foreach ($dates as $date)
                                                    <div class="book-dt-slot-cell">
                                                        @include('bladethemev1::livewire.book._slot-cell', ['room' => $room, 'date' => $date, 'roomTimeSlot' => $roomTimeSlot])
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

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
</div>
{{-- window.mountBookDtSwiper() + Livewire morph.updated hook sống trong public/js/home-sections.js
     (đã load sẵn qua <script src> ở mọi trang dùng Book.php — flash-sale.blade.php,
     search.blade.php, booking-board.blade.php) — KHÔNG đặt <script> ở đây: nội dung file này chỉ
     xuất hiện trong DOM sau khi Livewire morph vào (activeCategoryData rỗng lúc mount đầu), và
     trình duyệt KHÔNG tự thực thi <script> được chèn qua DOM patching kiểu đó (chỉ có directive
     Alpine như x-init mới được Livewire+Alpine tự động re-init trên nội dung mới morph vào). --}}
