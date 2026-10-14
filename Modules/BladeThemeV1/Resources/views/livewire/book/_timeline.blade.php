{{--
    Bảng đặt lịch kiểu "TRỰC QUAN" theo yêu cầu: 1 HÀNG = 1 SÂN (thay cho _desktop-grid.blade.php
    kiểu "mỗi sân 1 card khung giờ nhiều ngày"), chỉ xem 1 NGÀY tại 1 thời điểm, cột ngang là các
    khung giờ của TẤT CẢ sân gộp lại (hợp các mốc giờ bắt đầu khác nhau giữa các sân — sân nào
    không có khung giờ ở mốc đó thì ô hiện màu khoá, không bấm được).

    QUAN TRỌNG: file này KHÔNG viết lại logic chọn/giá/khuyến mãi/giữ chỗ — mỗi ô tái sử dụng
    NGUYÊN VẸN book/_slot-cell.blade.php (đã có sẵn @click cho toggleSlot khớp đúng state
    "selectedSlots" và saveAndRedirect() của Book.php) — chỉ đổi cách SẮP XẾP các ô đó trên màn
    hình (từ lưới "ngày x giờ" trong từng card riêng sân, sang lưới "giờ x sân" 1 ngày).
    Inherits: $dates, $styleOneRooms, $today, $category (từ book.blade.php).

    CẤU TRÚC (đổi hẳn cách làm sau nhiều lần vá CSS position:sticky không dứt điểm — bug "khung
    giờ đè/che tên sân lúc cuộn ngang" cứ tái diễn dưới nhiều dạng khác nhau dù đã thử nhiều cách:
    đổi màu viền, thêm overscan, bỏ overflow, đổi minmax...). Giải pháp CHUẨN cho bảng "đóng băng
    cột" (giống Google Sheets/Excel Online, và đúng ảnh mẫu tham khảo người dùng cung cấp): TÁCH
    HẲN cột tên sân (.book-tl-labels) ra thành 1 khối HOÀN TOÀN RIÊNG, KHÔNG nằm trong
    .book-tl-scroll (vùng cuộn ngang) — về mặt kỹ thuật, cuộn ngang bên trong .book-tl-scroll
    không thể nào chạm/đè được vào .book-tl-labels vì chúng không chung 1 vùng scroll, khác hẳn
    position:sticky (vẫn nằm CHUNG 1 scroll container, chỉ "giả vờ" đứng yên bằng cách tính toán
    lại vị trí liên tục — dễ vỡ khi kết hợp minmax/zoom/nhiều lớp CSS khác).

    Đồng bộ CHIỀU CAO giữa 2 khối: .book-tl-labels-corner cao bằng .book-tl-time-head (hàng giờ),
    mỗi .book-tl-room-label cao bằng hàng .book-tl-cell tương ứng — CẢ HAI dùng cùng 1 giá trị
    height cố định (không phải min-height co giãn tự do như trước) + white-space:nowrap (không
    word-break) để đảm bảo LUÔN đúng 1 dòng, không bao giờ lệch hàng do 1 bên cao hơn bên kia.
--}}
@php
    // $timeColumns: start_time => end_time — header hiển thị CẢ khoảng "HH:MM-HH:MM" (không chỉ
    // giờ bắt đầu) để khách chọn ô biết rõ khung đó tính từ mấy giờ tới mấy giờ, tránh nhầm ô đang
    // chọn thuộc khung trước hay khung sau mốc giờ hiển thị.
    $timeColumns = collect();
    foreach ($styleOneRooms as $room) {
        foreach ($room->roomTimeSlots as $rts) {
            $timeColumns->put($rts->timeSlot->start_time, $rts->timeSlot->end_time);
        }
    }
    $timeColumns = $timeColumns->sortKeys();
    $colCount    = max($timeColumns->count(), 1);

    // Tên hàng rút gọn: tên chi nhánh đã hiển thị sẵn ở tiêu đề trang, lặp lại trong MỖI hàng vừa
    // dài dòng vừa dễ tràn/cắt chữ trên màn hình hẹp. Dữ liệu thật không theo 1 pattern cố định
    // (có nơi "{chi nhánh} - Sân N", có nơi "Sân {loại} {tên} N" không dấu gạch ngang) nên KHÔNG
    // tách theo dấu câu — luôn lấy SỐ THỨ TỰ Ở CUỐI TÊN (kèm hậu tố dạng "(Cao cấp)" nếu có), ghép
    // thành "Sân {N}" bất kể phần đầu tên viết thế nào. Nếu tên không kết thúc bằng số (dữ liệu lạ,
    // không đoán được số sân) thì fallback về nguyên tên gốc (bị cắt bằng ... nếu quá dài, xem CSS
    // text-overflow: ellipsis — chấp nhận cắt hiển thị cho trường hợp hiếm này, đổi lấy việc KHÔNG
    // BAO GIỜ làm lệch hàng giữa cột tên sân và bảng khung giờ).
    $shortRoomName = function ($room) {
        if (preg_match('/(\d+)(\s*\([^)]*\))?\s*$/u', $room->name, $m)) {
            return 'Sân ' . $m[1] . ($m[2] ?? '');
        }
        return $room->name;
    };
@endphp

<div class="book-tl-wrap"
    x-data="{
        tlDateIdx: 0,
        // Chỉ mount vào DOM những NGÀY người dùng thực sự đã xem qua (bấm Trước/Sau), thay vì
        // toàn bộ dates cùng lúc (trước đây gây quá tải: 15 ngày x tất cả sân x tất cả khung giờ
        // = hàng trăm phần tử DOM tồn tại sẵn dù ẩn, khiến điện thoại thật render lỗi/vỡ chữ ở
        // cột sticky do phải tính toán vị trí cho quá nhiều phần tử cùng lúc - đã xác nhận qua
        // Inspect Element thật, không phải công cụ giả lập). Đã push vào mảng này thì KHÔNG BAO
        // GIỜ gỡ ra nữa nên vẫn giữ nguyên trạng thái đã chọn khi quay lại ngày cũ.
        visitedDays: [0],
        goToDay(idx) {
            this.tlDateIdx = idx;
            if (!this.visitedDays.includes(idx)) this.visitedDays.push(idx);
        },
        // Độ rộng mỗi cột khung giờ (px) — kéo thanh trượt để thu/phóng, đọc lại từ
        // --book-tl-col-w trên .book-tl-scroll (xem :style bên dưới).
        colWidth: 34,
        totalMinutes() {
            return (this.selectedSlots || []).reduce((sum, s) => {
                const [sh, sm] = s.startTime.split(':').map(Number);
                const [eh, em] = s.endTime.split(':').map(Number);
                let mins = (eh * 60 + em) - (sh * 60 + sm);
                if (mins <= 0) mins += 24 * 60;
                return sum + mins;
            }, 0);
        },
        totalHoursLabel() {
            const mins = this.totalMinutes();
            const h = Math.floor(mins / 60);
            const m = mins % 60;
            return m > 0 ? (h + 'h' + m) : (h + 'h');
        },
    }">
    <div class="book-tl-legend">
        <span class="book-tl-legend-item"><span class="book-tl-swatch book-tl-swatch-free"></span>Trống</span>
        <span class="book-tl-legend-item"><span class="book-tl-swatch book-tl-swatch-booked"></span>Đã đặt</span>
        <span class="book-tl-legend-item"><span class="book-tl-swatch book-tl-swatch-locked"></span>Khoá</span>
        {{-- Khung giờ đã qua (past-date/past-time, book/_styles.blade.php) — tô đỏ riêng biệt với
             "Đã đặt" để khách không nhầm là sân đông khách, chỉ là giờ đã trôi qua không đặt được
             nữa (xem giải thích đầy đủ ở book/_styles.blade.php). --}}
        <span class="book-tl-legend-item"><span class="book-tl-swatch book-tl-swatch-expired"></span>Hết giờ</span>
        <span class="book-tl-legend-item"><span class="book-tl-swatch book-tl-swatch-expired-booked"></span>Hết giờ (đã có đơn)</span>
        <span class="book-tl-legend-item"><span class="book-tl-swatch book-tl-swatch-selected"></span>Đang chọn</span>
        {{-- Trước đây ô có khuyến mãi (class "promo" của book/_slot-cell.blade.php, viền lóe màu
             chạy quanh ô) không có chú thích nào giải thích — người dùng không biết dựa vào đâu để
             nhận ra ô nào đang khuyến mãi (bug đã gặp: hỏi "sao biết khung nào khuyến mãi"). Thêm
             mục chú thích riêng, dùng cùng animation borderFlow/viền gradient với ô thật để nhận
             diện đúng 1-1. --}}
        <span class="book-tl-legend-item"><span class="book-tl-swatch book-tl-swatch-promo"></span>Khuyến mãi</span>

        {{-- Mua gói nhiều buổi — đặt NGAY TRONG hàng chú thích (yêu cầu người dùng: cùng hàng với
             Trống/Đã đặt/Khoá..., đẩy về cuối hàng bên phải bằng margin-left:auto trên nút, xem
             book/_package-plans.blade.php), KHÔNG còn là 1 hàng riêng phía trên bảng lịch nữa. --}}
        @include('bladethemev1::livewire.book._package-plans')
    </div>

    <div class="book-tl-datenav">
        <button type="button" class="book-tl-nav-btn" @click="goToDay(Math.max(0, tlDateIdx - 1))" :disabled="tlDateIdx === 0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Trước
        </button>

        <span class="book-tl-date-label">
            @foreach ($dates as $idx => $date)
                @php $dateShort = \Carbon\Carbon::createFromFormat('d-m-Y', $date['date'])->format('d-m-Y'); @endphp
                <span x-show="tlDateIdx === {{ $idx }}">{{ $date['day'] }}, {{ $dateShort }}</span>
            @endforeach
        </span>

        <button type="button" class="book-tl-nav-btn"
            @click="goToDay(Math.min({{ count($dates) - 1 }}, tlDateIdx + 1))"
            :disabled="tlDateIdx === {{ count($dates) - 1 }}">
            Sau
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    <div class="book-tl-zoom-row">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="book-tl-zoom-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0a7.5 7.5 0 10-10.6 0 7.5 7.5 0 0010.6 0zM10.5 7.5v6m-3-3h6"/></svg>
        <input type="range" min="20" max="60" step="2" x-model.number="colWidth" class="book-tl-zoom-slider" aria-label="Thu phóng độ rộng khung giờ">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="book-tl-zoom-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0a7.5 7.5 0 10-10.6 0 7.5 7.5 0 0010.6 0zM8 10.5h5"/></svg>
    </div>

    {{-- .book-tl-body: flex ngang 2 khối ĐỘC LẬP — .book-tl-labels (cột tên sân, đứng yên tuyệt
         đối, không cuộn) và .book-tl-scroll (chỉ chứa khung giờ, tự cuộn ngang riêng). Xem giải
         thích đầy đủ ở đầu file. --}}
    <div class="book-tl-body">
        <div class="book-tl-labels">
            <div class="book-tl-labels-corner"></div>
            @foreach ($styleOneRooms as $room)
                <div class="book-tl-room-label" title="{{ $room->name }}">{{ $shortRoomName($room) }}</div>
            @endforeach
        </div>

        <div class="book-tl-scroll" :style="'--book-tl-col-w: ' + colWidth + 'px'">
            <div class="book-tl-header-grid" style="--book-tl-cols: {{ $colCount }};">
                @foreach ($timeColumns as $time => $endTime)
                    {{-- Nhãn giờ đặt NGAY TRÊN VẠCH chia giữa 2 ô (kiểu trục thời gian lịch) thay
                         vì nằm giữa lòng 1 ô — vạch đó chính là ranh giới bắt đầu khung giờ này,
                         trực quan hơn khi phải suy luận 1 nhãn "căn giữa ô" là tính từ mấy giờ tới
                         mấy giờ. --}}
                    <div class="book-tl-time-head"><span>{{ \Carbon\Carbon::parse($time)->format('H:i') }}</span></div>
                @endforeach
            </div>

            @foreach ($dates as $idx => $date)
                {{-- Chỉ dựng DOM ngày này khi đã "visited" (xem goToDay() ở x-data phía trên) —
                     lazy mount, không phải x-if bình thường (x-if gỡ/dựng lại MỖI LẦN đổi ngày,
                     mất trạng thái active). visitedDays chỉ CỘNG THÊM chứ không bao giờ bớt, nên 1
                     khi ngày này đã dựng lần đầu, nó tồn tại vĩnh viễn trong DOM giống x-show
                     thuần trước đây — khác biệt duy nhất: 14 ngày CHƯA XEM sẽ không tồn tại trong
                     DOM cho tới khi người dùng thực sự bấm tới đó. --}}
                <template x-if="visitedDays.includes({{ $idx }})">
                <div class="book-tl-day-grid" x-show="tlDateIdx === {{ $idx }}" style="--book-tl-cols: {{ $colCount }};">
                    @foreach ($styleOneRooms as $room)
                        @php
                            $slotsByTime = $room->roomTimeSlots->keyBy(fn ($rts) => $rts->timeSlot->start_time);
                        @endphp
                        @foreach ($timeColumns as $time => $endTime)
                            <div class="book-tl-cell">
                                @if ($slotsByTime->has($time))
                                    @include('bladethemev1::livewire.book._slot-cell', ['room' => $room, 'date' => $date, 'roomTimeSlot' => $slotsByTime->get($time)])
                                @else
                                    <div class="book-tl-locked" title="Ngoài giờ hoạt động"></div>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
                </template>
            @endforeach
        </div>
    </div>

    {{-- Nút "Xem thêm ngày" đã ẩn khỏi giao diện theo yêu cầu người dùng — chỉ ẩn hiển thị, KHÔNG
         xoá logic thật trong Book.php (thuộc tính visibleDaysCount và hàm loadMoreDates() vẫn còn
         nguyên trong Book.php, chỉ cần dựng lại khối if hiển thị nút này ở đây nếu sau này cần
         dùng lại). --}}

    {{-- Bảng tính giá chi tiết + nút đặt sân đã ở book/_pricing.blade.php (bảng giá desktop +
         bottom-sheet mobile, được include lại ở book.blade.php) — bỏ thanh CTA rút gọn từng thêm
         ở đây để tránh 2 khối tổng tiền/nút đặt sân cùng hiện lúc chọn khung giờ. --}}
</div>

@once
    <style>
        {{-- Khi vừa chọn khung giờ đầu tiên (bottom-sheet giá hiện ra), book.blade.php tự cuộn
             trang tới .book-panel (scrollIntoView) để bảng khung giờ nằm gọn dưới header, không
             còn cảnh nửa bị header xanh cố định che, nửa lộ ra kỳ cục lúc người dùng tự cuộn tay.
             scroll-margin-top bù đúng chiều cao top-bar (~56px) + search-bar (~56px) cố định phía
             trên (2 khối này sticky/fixed toàn site, không nằm trong luồng cuộn của trang) — nếu
             không có dòng này, scrollIntoView({block:'start'}) sẽ đưa mép trên .book-panel đúng
             sát y=0 của viewport, tức là NGAY DƯỚI header cố định, bị header đè lên. --}}
        .book-panel { scroll-margin-top: 130px; }
        .book-tl-wrap { padding: 0 .75rem 1rem; }
        .book-tl-legend { display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: center; margin-bottom: .75rem; font-size: .8rem; font-weight: 600; color: #374151; }
        .book-tl-legend-item { display: inline-flex; align-items: center; gap: .4rem; }
        .book-tl-swatch { width: 14px; height: 14px; border-radius: 4px; display: inline-block; border: 1px solid #d1d5db; }
        .book-tl-swatch-free { background: #fff; }
        /* Đúng màu ô .selectable.booked thật (var(--color-primary), xem book/_styles.blade.php) —
           trước đây chú thích vẽ đỏ trong khi ô thật màu xanh, lại gần giống "Hết giờ" (đỏ nhạt). */
        .book-tl-swatch-booked { background: var(--color-primary, #15803d); border-color: var(--color-primary, #15803d); }
        .book-tl-swatch-locked { background: #9ca3af; border-color: #9ca3af; }
        .book-tl-swatch-expired { background: #f3f4f6; border-color: #e5e7eb; }
        .book-tl-swatch-expired-booked { background: #fecaca; border-color: #ef4444; }
        .book-tl-swatch-selected { background: #86efac; border-color: #16a34a; }
        .book-tl-swatch-promo {
            background: linear-gradient(270deg, #ff0000, #ff9900, #33ff00, #00ffff, #3300ff, #ff00cc, #ff0000);
            background-size: 300% 300%;
            animation: swatchBorderFlow 10s linear infinite;
            border-color: transparent;
        }
        @keyframes swatchBorderFlow {
            0% { background-position: 0% 50%; }
            100% { background-position: 300% 50%; }
        }

        .book-tl-datenav { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-bottom: .75rem; }
        .book-tl-nav-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .4rem .75rem; border-radius: 9999px; border: 1px solid #e5e7eb; background: #fff; font-size: .8rem; font-weight: 700; color: #0B6B3A; }
        .book-tl-nav-btn svg { width: 14px; height: 14px; }
        .book-tl-nav-btn:disabled { opacity: .4; }
        .book-tl-date-label { font-weight: 800; color: #111827; min-width: 160px; text-align: center; }

        {{-- Thanh thu/phóng độ rộng khung giờ — kéo để đổi biến --book-tl-col-w (đặt trên
             .book-tl-scroll qua :style, xem markup phía trên). --}}
        .book-tl-zoom-row { display: flex; align-items: center; gap: .5rem; margin-bottom: .5rem; }
        .book-tl-zoom-icon { width: 15px; height: 15px; color: #9ca3af; flex-shrink: 0; }
        .book-tl-zoom-slider { flex: 1; max-width: 220px; accent-color: #0B6B3A; height: 4px; cursor: pointer; }

        {{-- .book-tl-body: khung bao ngoài chung cho cả 2 khối (viền + bo góc dùng chung, che bởi
             overflow:hidden để bo góc đẹp dù .book-tl-scroll cuộn bên trong). --}}
        .book-tl-body { display: flex; align-items: flex-start; border: 1px solid #e5e7eb; border-radius: .75rem; overflow: hidden; background: #fff; }

        {{-- Cột tên sân — KHÔNG nằm trong vùng cuộn ngang (.book-tl-scroll) nữa, đứng yên tuyệt
             đối 100% chắc chắn khi cuộn, không cần position:sticky/z-index/overscan gì cả — cuộn
             ngang bên phải không thể nào chạm tới được vì đây là 2 phần tử flex riêng biệt.
             white-space:nowrap (không word-break) + text-overflow:ellipsis: LUÔN đúng 1 dòng, để
             chiều cao mỗi hàng khớp TUYỆT ĐỐI với hàng tương ứng bên .book-tl-cell (xem giải thích
             đầy đủ ở đầu file) — không bao giờ lệch hàng do label bị xuống dòng còn cell thì không. --}}
        .book-tl-labels { flex-shrink: 0; width: 96px; display: flex; flex-direction: column; border-right: 3px solid #64748b; }
        .book-tl-labels-corner { height: 1.5rem; background: #e0f2fe; border-bottom: 1px solid #bae6fd; }
        .book-tl-room-label { height: 34px; display: flex; align-items: center; padding: 0 .5rem; font-size: .74rem; font-weight: 800; color: #111827; border-top: 1px solid #e5e7eb; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .book-tl-scroll { flex: 1; min-width: 0; overflow-x: auto; }
        {{-- Trước đây dùng bề rộng PX CỐ ĐỊNH (không phải minmax) vì lo 2 grid (header/lưới-ngày)
             tự co giãn lệch nhau theo nội dung riêng — nhưng hệ quả là bảng LUÔN giữ đúng 1 độ
             rộng dù khung hiển thị còn dư chỗ (kéo zoom nhỏ lại chỉ để trống 1 khoảng trắng lớn
             bên phải, không lấp đầy khung — bug đã gặp). Đổi sang minmax(--book-tl-col-w, 1fr):
             khi khung còn dư chỗ, cột tự GIÃN ĐỀU lấp đầy (1fr thắng); khi khung không đủ chỗ
             (nhiều cột/zoom to), cột giữ đúng mức tối thiểu --book-tl-col-w và bảng mới tự nới
             rộng ra ngoài khung, sinh thanh cuộn ngang.

             VẪN AN TOÀN với bug cũ (nhãn giờ trôi lệch) vì: KHÔNG có "min-width: max-content" —
             cả 2 grid đều lấy ĐÚNG 1 giá trị "available width" TỪ CÙNG 1 CHA CHUNG (.book-tl-
             scroll), cùng số cột, cùng minmax mỗi cột → input để tính "1fr" HỆT NHAU ở cả 2 grid,
             nên luôn ra cùng kết quả, không còn lệch theo nội dung riêng từng grid như trước. --}}
        .book-tl-header-grid, .book-tl-day-grid {
            display: grid;
            grid-template-columns: repeat(var(--book-tl-cols), minmax(var(--book-tl-col-w, 34px), 1fr));
        }
        {{-- Nhãn giờ nằm NGAY TRÊN VẠCH chia giữa 2 ô (kiểu trục thời gian) — dùng span position:
             absolute, left:0 (mép trái của ô = vạch chia với ô trước), transform translateX(-50%)
             để tâm chữ trùng đúng vạch, không phải "căn giữa trong lòng ô" như trước. overflow:
             visible ở .book-tl-time-head để chữ được phép tràn sang ô 2 bên (không bị cắt). --}}
        .book-tl-time-head { position: relative; height: 1.5rem; background: #e0f2fe; border-bottom: 1px solid #bae6fd; border-left: 1px solid #bae6fd; overflow: visible; }
        .book-tl-time-head span { position: absolute; left: 0; top: 50%; transform: translate(-50%, -50%); font-size: .64rem; font-weight: 700; color: #0369a1; white-space: nowrap; background: #e0f2fe; padding: 0 .15rem; }
        {{-- Chiều cao pill thu nhỏ theo cả 2 chiều (không chỉ chiều ngang qua thanh trượt) — 34px
             khớp đúng .book-tl-room-label để 2 cột luôn thẳng hàng. --}}
        .book-tl-cell { height: 34px; border-top: 1px solid #e5e7eb; border-left: 1px solid #f1f5f9; display: flex; min-width: 0; overflow: hidden; }
        .book-tl-cell:first-child { padding-left: 4px; }
        {{-- height: auto !important — .selectable DÙNG CHUNG (book/_styles.blade.php) có sẵn
             "height: 36px"/"height: 32px" cứng cho các bảng cũ (không !important), nếu không ghi
             đè tường minh, giá trị đó vẫn thắng dù selector .book-tl-cell .selectable cụ thể hơn
             (specificity chỉ thắng khi CÙNG thuộc tính được set — "height" tôi không set thì kế
             thừa từ rule khác), làm ô cao hơn 34px của .book-tl-cell, phá đồng bộ hàng với cột tên
             sân bên trái. --}}
        .book-tl-cell .selectable { flex: 1; margin: 1.5px; border-radius: 5px; height: auto !important; min-width: 0; overflow: hidden; }
        .book-tl-locked { flex: 1; margin: 1.5px; border-radius: 5px; background: #d1d5db; }

        {{-- Ô 34px quá nhỏ để thấy hiệu ứng viền lóe gốc (.selectable.promo::before, book/
             _styles.blade.php) — hiệu ứng đó dùng filter:blur(5px) TRÀN RA NGOÀI viền ô mới nhìn
             thấy được, nhưng .book-tl-cell VÀ .book-tl-cell .selectable đều overflow:hidden (cần
             để bo góc/không vỡ layout hàng), chặn đứng hết phần tràn đó — ô promo cứ y hệt ô
             thường (bug đã gặp: hỏi "khuyến mãi đâu"/"sao lại như vậy" dù class="promo" đã đúng).
             Đè thêm viền THẬT + nền nhạt ngay trên chính ô (không cần tràn ra ngoài) — luôn hiển
             thị bất kể overflow của cha. --}}
        {{-- CHỈ tô viền màu (gradient hồng-xanh giống swatch chú thích "Khuyến mãi"), nền BÊN
             TRONG vẫn trắng — không tô kín cả ô như bản trước (đã bị người dùng chỉnh lại: "ý tôi
             là màu ở border chứ không tô cả ô"). Kỹ thuật 2 lớp gradient (padding-box giữ nền
             trắng bên trong / border-box tô viền) thay vì border-color thường vì CSS không cho
             border-color nhận trực tiếp 1 gradient. --}}
        {{-- ::before gốc (viền lóe rainbow, z-index:-10) thực ra PAINT TRÊN "background" của
             chính .selectable trong thứ tự vẽ CSS (z-index âm vẫn cao hơn nền phần tử gốc, chỉ
             thấp hơn nội dung trong luồng) — nếu không tắt hẳn nó, ::before phủ kín đè lên đúng
             nền trắng+viền vừa set bên dưới, nhìn như tô đặc cả ô thay vì chỉ viền (bug đã gặp:
             "hoàn toàn chưa giống như tôi nói" — mong muốn CHỈ viền màu, nền trong vẫn trắng). --}}
        .book-tl-cell .selectable.promo::before,
        .book-tl-cell .selectable.promo::after { content: none !important; }
        /* Viền khuyến mãi chỉ dành cho ô còn trống. Nếu ô đồng thời đã đặt/đang chờ/bị khóa/
           hết giờ thì màu trạng thái phải thắng; selector promo cũ có độ ưu tiên cao hơn
           .selectable.booked nên đã phủ nền trắng lên đúng ô 17:00 đã đặt. */
        .book-tl-cell .selectable.promo:not(.booked):not(.pending):not(.blocked):not(.held):not(.past-date):not(.past-time):not(.active) {
            border: 2px solid transparent !important;
            background: linear-gradient(#fff, #fff) padding-box,
                        linear-gradient(135deg, #f472b6, #60a5fa) border-box !important;
        }

        /* "Đang chọn" phải màu xanh lá theo chú thích (book-tl-swatch-selected) — CSS gốc
           (book/_styles.blade.php, .selectable.active::after) tô màu xám (--color-tickGray).
           Cần selector cụ thể hơn (3 lớp thay vì 2) để thắng !important gốc theo specificity. */
        .book-tl-wrap .book-tl-day-grid .book-tl-cell .selectable.active,
        .book-tl-wrap .book-tl-day-grid .book-tl-cell .selectable.active::after {
            background-color: #22c55e !important;
            border-color: #16a34a !important;
        }
        {{-- Ô vừa khuyến mãi vừa ĐANG CHỌN: rule ".selectable.promo" ở trên set "background"
             (shorthand — kéo theo cả background-IMAGE là 2 lớp gradient) đè lên trên
             "background-color" xanh lá của rule ".active" phía trên (2 thuộc tính CSS khác nhau,
             active không ghi đè được background-image dù thắng specificity ở background-color) —
             kết quả ô chọn rồi vẫn hiện viền hồng-xanh + nền trắng thay vì xanh lá đặc như mọi ô
             đang chọn khác (bug đã gặp: "tại sao khi chọn lại như vậy"). Cần 1 rule RIÊNG, cụ thể
             hơn CẢ HAI rule trên, ép hẳn về xanh lá đặc khi đã chọn — ưu tiên trạng thái "đang
             chọn" hơn viền khuyến mãi (thông tin giảm giá đã có sẵn ở bảng tính tiền bên dưới). --}}
        .book-tl-wrap .book-tl-day-grid .book-tl-cell .selectable.promo.active {
            background: #111827 !important;
            border: 2px solid #111827 !important;
        }
    </style>
@endonce
