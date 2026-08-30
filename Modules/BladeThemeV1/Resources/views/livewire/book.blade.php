<div x-data="{
    selectedSlots: [],
    selectedRoomId: null,
    selectedRoomIsActive: null,
    fullBookingDiscount: 0,
    hasFullDayBooking: false,
    fullDayDates: [], // Thêm để track ngày nào full booking
    showPackageModal: false, // Popup Mua gói nhiều buổi — xem book/_package-plans.blade.php

    resetSelection() {
        document.querySelectorAll('.selectable.active').forEach(el => el.classList.remove('active'));
        this.selectedSlots = [];
        this.selectedRoomId = null;
        this.selectedRoomIsActive = null;
        this.fullBookingDiscount = 0;
        this.hasFullDayBooking = false;
        this.fullDayDates = [];
    },

    toggleSlot(el, slot) {
        if (this.selectedRoomId === null) {
            this.selectedRoomId = slot.roomId;
            this.selectedRoomIsActive = slot.is_activated;
        }

        if (this.selectedRoomId !== slot.roomId) {
            return;
        }

        // Use selectedSlots as source of truth (not el.classList) to avoid DOM lag on quick clicks
        const isSelected = this.selectedSlots.some(
            s => s.timeslotId === slot.timeslotId && s.date === slot.date
        );

        // Mobile (book/_mobile.blade.php) và desktop (book/_desktop-grid.blade.php) render 2 DOM
        // riêng biệt cho CÙNG 1 ô khung giờ (cùng sân/ngày/khung giờ) — bấm chọn ở bên nào thì
        // chỉ el bên đó nhận class active, còn ô song sinh bên kia (ẩn qua CSS lg:hidden/hidden
        // lg:block, không phải bị gỡ khỏi DOM) không được đồng bộ, nên khi đổi kích thước trình
        // duyệt qua breakpoint sẽ thấy ô đã chọn không tô đen dù selectedSlots vẫn đúng. Tìm mọi
        // ô cùng data-room-id/timeslot-id/date (xem _slot-cell.blade.php) để toggle đồng thời,
        // không chỉ riêng el. Chú ý: toàn khối x-data này nằm trong 1 attribute HTML bọc bởi dấu
        // ngoặc kép, nên tuyệt đối không được gõ ký tự ngoặc kép ở bất kỳ đâu trong toàn bộ khối
        // này (kể cả trong comment) — chỉ dùng dấu nháy đơn cho chuỗi.
        const twins = document.querySelectorAll(
            '.selectable[data-room-id=\'' + slot.roomId + '\'][data-timeslot-id=\'' + slot.timeslotId + '\'][data-date=\'' + slot.date + '\']'
        );

        if (isSelected) {
            twins.forEach(twin => twin.classList.remove('active'));
            // Fix: filter by BOTH date AND timeslotId — same timeslot can exist on multiple dates
            this.selectedSlots = this.selectedSlots.filter(
                s => !(s.timeslotId === slot.timeslotId && s.date === slot.date)
            );

            if (this.selectedSlots.length === 0) {
                this.selectedRoomId = null;
                this.selectedRoomIsActive = null;
                this.fullBookingDiscount = 0;
                this.hasFullDayBooking = false;
                this.fullDayDates = [];
            }
        } else {
            twins.forEach(twin => twin.classList.add('active'));
            this.selectedSlots.push(slot);
        }

        this.checkFullBooking();
    },

    checkFullBooking() {
        if (!this.selectedRoomId || this.selectedSlots.length === 0) {
            this.fullBookingDiscount = 0;
            this.hasFullDayBooking = false;
            this.fullDayDates = [];
            return;
        }

        const slotsByDate = {};
        this.selectedSlots.forEach(slot => {
            if (!slotsByDate[slot.date]) {
                slotsByDate[slot.date] = [];
            }
            slotsByDate[slot.date].push(slot);
        });

        const firstSlot = this.selectedSlots[0];
        const totalSlotsInRoom = firstSlot.totalSlotsInRoom;
        const fullBookingDiscountValue = firstSlot.fullBookingDiscountValue;

        let hasFullDay = false;
        this.fullDayDates = [];

        Object.entries(slotsByDate).forEach(([date, slots]) => {
            if (slots.length === totalSlotsInRoom) {
                hasFullDay = true;
                // Chỉ track fullDayDates nếu có cấu hình full_booking_discount
                if (fullBookingDiscountValue) {
                    this.fullDayDates.push(date);
                }
            }
        });

        // Chỉ kích hoạt full booking mode khi có cấu hình giảm giá
        this.hasFullDayBooking = hasFullDay && !!fullBookingDiscountValue;

        if (this.hasFullDayBooking) {
            this.fullBookingDiscount = this.calculateDiscountValue(
                this.totalPrice,
                fullBookingDiscountValue
            );
        } else {
            this.fullBookingDiscount = 0;
        }
    },

    calculateDiscountValue(totalPrice, discountStr) {
        if (!discountStr) return 0;

        if (discountStr.includes('%')) {
            const percentage = parseFloat(discountStr.replace('%', ''));
            return totalPrice * (percentage / 100);
        } else {
            return parseFloat(discountStr.replace(/[.,]/g, ''));
        }
    },

    get totalOriginalPrice() {
        return this.selectedSlots.reduce((sum, s) => sum + (s.basePrice || s.originalPrice || s.price), 0);
    },

    // SỬA: Không tính promo discount cho ngày full booking
    get totalPromoDiscount() {
        return this.selectedSlots.reduce((sum, s) => {
            // Nếu ngày này full booking thì không tính promo discount
            if (this.fullDayDates.includes(s.date)) {
                return sum;
            }
            return sum + (s.promoDiscount || 0);
        }, 0);
    },

    get totalIncreaseAmount() {
        return this.selectedSlots.reduce((sum, s) => {
            return sum + (s.increaseAmount || 0);
        }, 0);
    },

    // Dùng cho dòng Tổng giờ ở book/_pricing.blade.php (thanh tổng tiền) — cộng dồn thời lượng
    // thật của từng khung giờ đã chọn (startTime/endTime), không phải đếm số khung giờ.
    get totalHoursLabel() {
        const mins = this.selectedSlots.reduce((sum, s) => {
            const [sh, sm] = s.startTime.split(':').map(Number);
            const [eh, em] = s.endTime.split(':').map(Number);
            let m = (eh * 60 + em) - (sh * 60 + sm);
            if (m <= 0) m += 24 * 60;
            return sum + m;
        }, 0);
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        return m > 0 ? (h + 'h' + m) : (h + 'h');
    },

    // Rút gọn tên sân giống PHP $shortRoomName() ở book/_timeline.blade.php (chỉ lấy số thứ tự ở
    // cuối tên) — dùng cho khối Chi tiết đặt sân ở book/_pricing.blade.php, hiển thị dạng Sân N gọn
    // hơn thay vì tên chi nhánh đầy đủ đã gửi kèm slot (roomName, xem book/_slot-cell.blade.php).
    shortRoomName(name) {
        const m = String(name || '').match(/(\d+)(\s*\([^)]*\))?\s*$/u);
        return m ? ('Sân ' + m[1] + (m[2] || '')) : name;
    },

    // Đổi dạng giờ 16:00:00 hoặc 16:00 thành 16h00
    formatSlotTime(t) {
        const parts = String(t || '').split(':');
        return parts[0] + 'h' + (parts[1] || '00');
    },

    // Gộp các khung giờ liên tiếp cùng sân/cùng ngày thành 1 dòng khoảng giờ (vd chọn liền
    // 10h00-11h00, 11h00-12h00, ... -> chỉ hiện 1 dòng 10h00-20h00) thay vì liệt kê từng giờ rời
    // rạc — dùng cho khối Chi tiết đặt sân ở book/_pricing.blade.php.
    get groupedSlotDetails() {
        const groups = {};
        const order = [];
        this.selectedSlots.forEach(s => {
            const key = s.roomId + '|' + s.date;
            if (!groups[key]) { groups[key] = []; order.push(key); }
            groups[key].push(s);
        });
        const result = [];
        order.forEach(key => {
            const slots = groups[key].slice().sort((a, b) => a.startTime.localeCompare(b.startTime));
            let current = null;
            slots.forEach(s => {
                if (current && current.endTime === s.startTime) {
                    current.endTime = s.endTime;
                } else {
                    if (current) result.push(current);
                    current = { roomName: s.roomName, startTime: s.startTime, endTime: s.endTime };
                }
            });
            if (current) result.push(current);
        });
        return result;
    },

    get totalPrice() {
        return this.selectedSlots.reduce((sum, s) => {
            // Nếu là ngày full booking, tính lại giá = basePrice + increaseAmount
            if (this.fullDayDates.includes(s.date)) {
                return sum + (s.basePrice + s.increaseAmount);
            }
            return sum + s.price;
        }, 0);
    },

    get discountRate() {
        if (this.hasFullDayBooking || this.selectedSlots.length === 0) {
            return 0;
        }

        const rules = this.selectedSlots[0].bulkDiscountRules;
        if (!rules || rules.length === 0) {
            return 0;
        }

        const count = this.selectedSlots.length;
        // Lấy rule có slots <= count, ưu tiên rule cao nhất
        const matched = rules
            .filter(r => count >= r.slots)
            .sort((a, b) => b.slots - a.slots)[0];

        return matched ? matched.discount / 100 : 0;
    },

    get discount() {
        return this.totalPrice * this.discountRate;
    },

    get totalAfterDiscount() {
        return this.totalPrice - this.discount;
    },

    get totalAfterAllDiscounts() {
        return this.totalAfterDiscount - this.fullBookingDiscount;
    },

    // SỬA: Không hiển thị discount promotions cho ngày full booking
    get promotionSummary() {
        const summary = {
            increases: {},
            discounts: {}
        };

        this.selectedSlots.forEach(slot => {
            // Xử lý TĂNG GIÁ (luôn hiển thị)
            if (slot.increasePromotions && slot.increasePromotions.length > 0) {
                slot.increasePromotions.forEach(promo => {
                    if (!summary.increases[promo.name]) {
                        summary.increases[promo.name] = {
                            type: promo.type,
                            total: 0,
                            count: 0
                        };
                    }
                    summary.increases[promo.name].total += promo.amount || 0;
                    summary.increases[promo.name].count++;
                });
            }

            // Xử lý GIẢM GIÁ (chỉ hiển thị nếu KHÔNG phải ngày full booking)
            if (!this.fullDayDates.includes(slot.date)) {
                if (slot.discountPromotions && slot.discountPromotions.length > 0) {
                    slot.discountPromotions.forEach(promo => {
                        if (!summary.discounts[promo.name]) {
                            summary.discounts[promo.name] = {
                                type: promo.type,
                                total: 0,
                                count: 0
                            };
                        }
                        summary.discounts[promo.name].total += promo.amount || 0;
                        summary.discounts[promo.name].count++;
                    });
                }
            }
        });

        return summary;
    }
}" x-on:book-category-changed.window="resetSelection()"
    x-init="$watch('selectedSlots', (val, oldVal) => {
        if (val.length > 0 && (!oldVal || oldVal.length === 0) && window.innerWidth < 1024) {
            $nextTick(() => {
                const panel = document.querySelector('.book-panel');
                if (panel) panel.scrollIntoView({ behavior: 'auto', block: 'start' });
            });
        }
    });"
    data-room-ids="{{ implode(',', $this->roomIds) }}">
    <div class="w-full mx-auto">
        @include('bladethemev1::livewire.book._header')

        <div id="default-styled-tab-content" wire:loading.class="pointer-events-none">
            {{-- Skeleton (book/_skeleton.blade.php) thay chỗ nội dung thật trong lúc Livewire tải
                 dữ liệu (đổi chi nhánh — loadBranch(), hoặc đổi tab danh mục —
                 setActiveCategoryTab()) — mượt hơn hẳn so với chỉ làm mờ opacity nội dung cũ.
                 Không dùng wire:target giới hạn theo tên method — loadBranch() được gọi gián tiếp
                 qua sự kiện 'load-branch' (Livewire.dispatch), không phải wire:click trực tiếp,
                 nên wire:target khớp theo tên method không nhận diện đúng request đang chạy. --}}
            <div wire:loading.block style="display:none;">
                @include('bladethemev1::livewire.book._skeleton')
            </div>
            <div wire:loading.remove>
            @if(!empty($activeCategoryData))
            @php $category = $activeCategoryData; @endphp
            <div class="relative" id="styled-{{ \Str::slug($category['name']) }}" role="tabpanel"
                aria-labelledby="styled-{{ \Str::slug($category['name']) }}-tab" wire:key="book-category-{{ $category['id'] }}">

                @php
                // $dates chỉ chứa $visibleDaysCount ngày (mặc định 15/31) — không build sẵn cả
                // tháng, tránh render (số sân x số khung giờ x số ngày) ô lịch cùng lúc gây
                // tràn bộ nhớ PHP. Bấm "Xem thêm ngày" gọi loadMoreDates() (round-trip Livewire
                // nhỏ) để tăng dần con số này.
                $dates = $this->getDatesForOneMonth();
                $styleOneRooms = collect($category['products'])->filter(fn($r) => ($r->styles ?? 1) == 1)->values();
                $totalStyleOneRooms = $styleOneRooms->count();
                $today = now()->startOfDay();
                @endphp

                @if($totalStyleOneRooms > 0)
                    {{-- Chú thích trạng thái giờ nằm TRONG book/_timeline.blade.php (không dùng
                         book/_legend.blade.php nữa — legend cũ theo đúng nhãn "Đã Đặt/Còn
                         Trống/Đang chọn/Khuyến mãi" của layout nhiều-ngày cũ, khác nhãn "Trống/Đã
                         đặt/Khoá/Đang chọn" của layout timeline mới).

                         Bảng tính giá CHI TIẾT (Giá cơ bản/Khuyến mãi/Giảm giá đặt nhiều khung
                         giờ/quà tặng/Tổng tiền tạm tính) là book/_pricing.blade.php — ĐÃ TỪNG bị
                         xoá nhầm vì tưởng trùng với thanh CTA đơn giản tự thêm ở
                         book/_timeline.blade.php, nhưng bảng chi tiết này mới là nội dung người
                         dùng thật sự cần (đúng ảnh mẫu). Nên: bỏ thanh CTA đơn giản khỏi
                         _timeline.blade.php (xem ghi chú ở đó), khôi phục lại bảng giá desktop +
                         bottom-sheet mobile ở đây — CHỈ 1 khối tổng tiền/nút đặt sân duy nhất, đúng
                         như trước khi bị xoá nhầm. --}}
                    <div class="book-panel">
                        {{-- Mua gói nhiều buổi cả chi nhánh — nút mở popup nằm ngay trong hàng chú
                             thích của book/_timeline.blade.php (book-tl-legend), không phải hàng
                             riêng ở đây nữa — xem ghi chú tại đó. --}}
                        @include('bladethemev1::livewire.book._timeline')

                        <div class="book-pricing-desktop">
                            @include('bladethemev1::livewire.book._pricing')
                        </div>
                    </div>
                @else
                    <div style="padding:2.5rem 1rem; text-align:center;">
                        <svg style="width:40px;height:40px;color:#d1d5db;margin:0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p style="color:#6b7280;font-size:14px;margin:0;">Không có sân khả dụng cho danh mục này.</p>
                    </div>
                @endif

            </div>
            @endif
            </div>
        </div>

        {{-- Mobile bottom sheet: hiện bảng tính giá chi tiết đầy đủ sau khi user chọn khung giờ
             (đúng ảnh mẫu — Giá cơ bản/Khuyến mãi/Giảm giá đặt nhiều khung giờ/quà tặng/Tổng tiền
             tạm tính, nút "Đặt sân ngay" gọi thẳng saveAndRedirect() thật). Không dùng backdrop
             toàn màn hình — trước đây backdrop phủ inset:0 chặn luôn cả click vào bảng khung giờ
             phía trên sheet, khiến không chọn thêm được khung giờ thứ 2 (chỉ bấm được nút "X" hoặc
             bấm ra ngoài để đóng). Người dùng có thể chọn tiếp trong khi sheet vẫn hiện, đóng bằng
             nút "X". --}}
        <div class="book-bottom-sheet"
             x-show="selectedSlots.length > 0"
             x-transition:enter="sheet-enter"
             x-transition:enter-start="sheet-enter-from"
             x-transition:enter-end="sheet-enter-to"
             x-transition:leave="sheet-leave"
             x-transition:leave-start="sheet-leave-from"
             x-transition:leave-end="sheet-leave-to"
             style="display:none">
            <div class="book-sheet-handle-row">
                <span class="book-sheet-handle"></span>
                <button type="button" class="book-sheet-close" @click="resetSelection()" aria-label="Đóng">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="book-sheet-scroll">
                @include('bladethemev1::livewire.book._pricing')
            </div>
        </div>

        {{-- Popup Mua gói nhiều buổi — đặt Ở GỐC cây x-data (cùng cấp .book-bottom-sheet phía
             trên, KHÔNG lồng trong .book-panel) để position:fixed hoạt động đúng, không bị khối
             cha nào chặn — xem ghi chú đầy đủ trong book/_package-modal.blade.php. --}}
        @include('bladethemev1::livewire.book._package-modal')

        @include('bladethemev1::livewire.book._styles')
    </div>
</div>