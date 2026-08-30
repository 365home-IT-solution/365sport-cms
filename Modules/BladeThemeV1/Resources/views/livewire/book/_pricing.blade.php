                <!-- Summary của book.blade -->
                <div class="book-pricing-card book-pricing-summary flex flex-col items-stretch mt-5 gap-0 overflow-hidden">
                    <div class="w-full text-left font-semibold px-2 pt-2">

                        <!-- Giá cơ bản -->
                        <p class="text-base text-primary mb-2 flex items-center gap-1">
                            Giá cơ bản:
                            <span class="font-bold" x-text="totalOriginalPrice.toLocaleString() + ' đ'"></span>
                        </p>

                        <!-- Chi tiết phụ thu -->
                        <template x-if="totalIncreaseAmount > 0">
                            <div class="ml-4 mb-2">
                                <p class="text-sm text-orange-600 font-semibold flex items-center gap-1">
                                    Phụ thu:
                                </p>
                                <template x-for="(promo, name) in promotionSummary.increases" :key="name">
                                    <p class="text-xs text-orange-500 ml-4">
                                        • <span x-text="name"></span>:
                                        <span x-text="'+' + promo.total.toLocaleString() + ' đ'"></span>
                                        <span class="text-gray-500" x-text="'(' + promo.count + ' khung giờ)'"></span>
                                    </p>
                                </template>
                                <p class="text-sm text-orange-600 font-bold ml-4 mt-1 border-t border-orange-200 pt-1">
                                    Tổng phụ thu:
                                    <span x-text="'+' + totalIncreaseAmount.toLocaleString() + ' đ'"></span>
                                </p>
                            </div>
                        </template>

                        <!-- Chi tiết khuyến mãi (CHỈ hiển thị nếu KHÔNG phải full booking) -->
                        <template x-if="totalPromoDiscount > 0 && !hasFullDayBooking">
                            <div class="ml-4 mb-2">
                                <p class="text-sm text-primary font-semibold flex items-center gap-1">
                                    Khuyến mãi:
                                </p>
                                <template x-for="(promo, name) in promotionSummary.discounts" :key="name">
                                    <p class="text-xs text-primary ml-4">
                                        • <span x-text="name"></span>:
                                        <span x-text="'-' + promo.total.toLocaleString() + ' đ'"></span>
                                        <span class="text-gray-500" x-text="'(' + promo.count + ' khung giờ)'"></span>
                                    </p>
                                </template>
                                <p class="text-sm text-primary font-bold ml-4 mt-1 border-t border-primary/30 pt-1">
                                    Tổng khuyến mãi:
                                    <span x-text="'-' + totalPromoDiscount.toLocaleString() + ' đ'"></span>
                                </p>
                            </div>
                        </template>

                        <!-- Giảm giá book nhiều giờ (CHỈ khi KHÔNG phải full booking) -->
                        <template x-if="discount > 0 && !hasFullDayBooking">
                            <p class="text-sm text-primary font-semibold ml-4 mb-2 flex items-center gap-1">
                                Giảm giá đặt <span x-text="selectedSlots.length" class="mx-1"></span> khung giờ
                                (<span x-text="(discountRate * 100).toFixed(0) + '%'"></span>):
                                <span x-text="'-' + discount.toLocaleString() + ' đ'"></span>
                            </p>
                        </template>

                        {{-- Quà tặng (2 Nước Ngọt + 1 Snack) khi đặt 2+ khung giờ — TẠM ẨN theo yêu
                             cầu người dùng, không phải xoá vĩnh viễn logic thật (nếu cần bật lại,
                             khôi phục lại đúng khối này). --}}

                        <!-- Giảm giá Full booking — CHỈ 1 chỗ hiển thị số tiền (fullBookingDiscount
                             luôn > 0 khi hasFullDayBooking true, xem checkFullBooking() ở trên,
                             nên trước đây bị lặp 2 lần cùng 1 số tiền nếu tách riêng 2 khối). -->
                        <template x-if="hasFullDayBooking">
                            <div class="ml-4 mb-2 bg-primary/5 p-3 rounded border border-primary/30">
                                <p class="text-sm text-primary font-semibold mb-1 flex items-center gap-1">
                                    Khuyến mãi:
                                </p>
                                <p class="text-xs text-primary ml-4">
                                    • Giảm giá khi đặt full khung giờ trong ngày:
                                    <span class="font-bold" x-text="'-' + fullBookingDiscount.toLocaleString() + ' đ'"></span>
                                </p>
                            </div>
                        </template>

                        {{-- Ghi chú "Giảm thêm 5%/10%..." TẠM ẨN theo yêu cầu người dùng (chưa cần
                             dùng tới) — không xoá logic discountRate/discount thật, chỉ bỏ dòng
                             hiển thị. --}}
                        <div class="mt-2 text-xs text-gray-600 italic">
                            <template x-if="hasFullDayBooking">
                                <div class="not-italic">
                                    <p class="text-primary font-semibold">
                                        Đã áp dụng ưu đãi đặc biệt cho Full sân
                                    </p>
                                    <p class="text-orange-600 text-xs mt-1">
                                        Không áp dụng cùng các khuyến mãi giảm giá khác
                                    </p>
                                </div>
                            </template>
                        </div>

                    </div>

                    {{-- Thanh "Tổng giờ / Tổng tiền" + nút đặt sân — màu xanh lá + cam theo đúng
                         ảnh mẫu người dùng cung cấp, ĐỒNG BỘ với màu top-bar/nút CTA đã dùng khắp
                         các trang mới trong đợt đổi giao diện này (gradient #0B6B3A→#128A4A, nút
                         cam #F5A623). KHÔNG sửa .book-pricing-card/.book-cta-btn dùng chung (còn
                         dùng ở nơi khác, vd đồng bộ JS với trang chi tiết sân cũ — xem comment
                         "#book-sync-product-detail" trong book/_styles.blade.php) — tạo class
                         riêng .book-price-total-bar/.book-price-cta-btn chỉ dùng ở khối này, full-
                         bleed sát mép dưới card (đặt NGOÀI phần có padding 22px/24px của
                         .book-pricing-card). --}}
                    {{-- Chi tiết đặt sân: liệt kê từng khung giờ đã chọn (roomName lấy từ payload
                         toggleSlot() ở book/_slot-cell.blade.php), có thể thu gọn/mở rộng bằng
                         chevron — đặt trong chính x-data của .book-price-total-bar-wrap nên dùng
                         chung state selectedSlots của book.blade.php, không cần thêm gì ở PHP. --}}
                    <div class="book-price-details" x-data="{ detailsOpen: false }" x-show="selectedSlots.length > 0">
                        <button type="button" class="book-price-details-toggle" @click="detailsOpen = !detailsOpen">
                            <span>Chi tiết đặt sân (<span x-text="selectedSlots.length"></span>)</span>
                            <svg :class="detailsOpen ? 'rotate-180' : ''" class="w-4 h-4 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div class="book-price-details-list" x-show="detailsOpen" x-collapse>
                            <template x-for="(s, i) in groupedSlotDetails" :key="i">
                                <div class="book-price-details-row">
                                    <span x-text="shortRoomName(s.roomName)"></span>
                                    <span x-text="formatSlotTime(s.startTime) + ' - ' + formatSlotTime(s.endTime)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="book-price-total-bar">
                        <span>Tổng giờ: <b x-text="totalHoursLabel"></b></span>
                        <span>Tổng tiền: <b x-text="totalAfterAllDiscounts.toLocaleString() + ' đ'"></b></span>
                    </div>
                    <button @click="$wire.saveAndRedirect(selectedSlots)"
                            :disabled="selectedSlots.length === 0"
                            x-show="selectedRoomIsActive === true || selectedRoomId === null"
                            class="book-price-cta-btn">
                        TIẾP THEO
                    </button>
                </div>

@once
    <style>
        {{-- .book-pricing-card (dùng chung, không sửa) có padding: 22px 24px bao quanh MỌI phần tử
             con — bù margin âm đúng bằng padding đó để 2 khối này tràn sát ra tận mép card thay vì
             bị đệm khoảng trắng 2 bên như nội dung phía trên. --}}
        .book-price-details { border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; overflow: hidden; }
        .book-price-details-toggle {
            display: flex; align-items: center; justify-content: space-between; width: 100%;
            padding: .55rem .75rem; background: #f8fafc; border: 0; cursor: pointer;
            font-size: .82rem; font-weight: 700; color: #111827;
        }
        .book-price-details-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: .4rem .75rem; font-size: .78rem; color: #374151;
            border-top: 1px solid #f1f5f9;
        }
        .book-price-details-row:first-child { border-top: 1px solid #e5e7eb; }
        .book-price-total-bar {
            display: flex; align-items: center; justify-content: space-between;
            padding: .75rem 1.25rem; font-size: .85rem; font-weight: 600; color: #fff;
            background: linear-gradient(135deg, #0B6B3A 0%, #128A4A 100%);
            margin: 0 -24px;
        }
        .book-price-total-bar b { font-size: 1rem; font-weight: 800; }
        {{-- KHÔNG set "width: 100%" — với margin âm 2 bên để tràn sát mép card, width cố định
             (kể cả 100%) chỉ DỊCH CHUYỂN phần tử sang trái theo margin-left âm, KHÔNG tự nới thêm
             để lấp margin-right âm (vì width đã bị khoá, không phải "auto" — hành vi chuẩn của
             CSS box model với width cố định + margin âm). Kết quả: mép phải kết thúc sớm hơn thật
             24px, để lại 1 dải trắng hẹp bên phải nút (bug đã gặp). Bỏ hẳn "width", để trình duyệt
             tự tính "auto" (mặc định của display:block) — lúc đó độ rộng MỚI thật sự = độ rộng
             containing block trừ đúng margin âm, lấp đầy cả 2 bên. --}}
        .book-price-cta-btn {
            display: block; padding: .9rem; border: 0;
            background: #F5A623; color: #1a1a1a; font-weight: 800; font-size: .95rem;
            letter-spacing: .02em; cursor: pointer;
            margin: 0 -24px -22px; border-radius: 0 0 20px 20px;
        }
        .book-price-cta-btn:hover:not(:disabled) { filter: brightness(0.96); }
        .book-price-cta-btn:disabled { opacity: .45; cursor: not-allowed; }

        {{-- QUAN TRỌNG: -24px chỉ đúng ở NGỮ CẢNH DESKTOP (.book-pricing-card có padding 22px
             24px, xem book/_styles.blade.php). Trong bottom-sheet mobile, cùng class
             .book-pricing-card lại bị chính book/_styles.blade.php override
             (".book-sheet-scroll .book-pricing-card") về "padding: 4px 0 0" — KHÔNG còn padding
             ngang nữa. Áp margin -24px y hệt vào đó sẽ kéo lố ra NGOÀI cả mép của
             .book-sheet-scroll (đang có padding 18px riêng), bị cắt chữ (bug đã gặp — "Tổng giờ"
             mất chữ "T" ở đầu). Trong sheet, card đã sát mép ngang sẵn (padding:0) nên KHÔNG cần
             bù margin âm nữa. --}}
        {{-- KHÔNG kéo margin âm xuống đáy nữa — .book-bottom-sheet có padding-bottom:
             env(safe-area-inset-bottom) để né vùng cử chỉ hệ thống (home indicator) của điện
             thoại, nhưng margin âm -18px trước đó kéo nút "TIẾP THEO" tràn qua cả phần đệm đó,
             khiến nút nằm sát/gần bị che bởi vùng an toàn (bug đã gặp — ảnh thực tế trên iPhone).
             Chấp nhận còn 1 khoảng trắng nhỏ (đúng bằng padding gốc 18px của .book-sheet-scroll)
             phía dưới nút, đổi lấy việc nút không bao giờ bị đè bởi vùng an toàn thiết bị. --}}
        .book-sheet-scroll .book-price-total-bar { margin: 0; }
        .book-sheet-scroll .book-price-cta-btn { margin: 0; border-radius: 12px; }
    </style>
@endonce
