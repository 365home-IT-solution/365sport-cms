                {{-- ── Shared legend + (desktop) nút đặt sân nhanh kèm giá tạm tính ── --}}
                {{-- flex-wrap justify-start (thay vì grid grid-cols-2): grid-cols-2 dùng cột 1fr
                     nên luôn giãn hết chiều ngang container — flex-wrap để các mục tự xuống dòng,
                     canh về đầu hàng ở mobile (lg:justify-between để chừa chỗ cho nút đặt sân
                     nhanh bên phải ở desktop). px-2.5 lg:px-0: ở mobile, tiêu đề sân
                     (.book-top-row) và bảng lịch (.book-grid-header) bên dưới đều có
                     padding: 0 10px (book/_styles.blade.php) — hàng chú thích này vốn không có
                     padding nên bị lệch ra ngoài 10px so với 2 khối đó, thêm px-2.5 (10px) để
                     thẳng hàng; bỏ ở desktop (lg:px-0) vì bản desktop dùng layout khác, không có
                     độ lệch này. --}}
                <div class="flex flex-wrap lg:flex-nowrap justify-start lg:justify-between items-center gap-x-8 gap-y-3 mb-4 px-2.5 lg:px-0">
                    <div class="flex flex-wrap justify-start items-center gap-x-4 lg:gap-x-8 gap-y-3 text-sm font-medium">
                        <div class="flex items-center gap-1">
                            <span class="w-4 h-4 bg-primary rounded"></span> Đã Đặt
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="w-4 h-4 border border-[#DDDDDD] rounded bg-white"></span> Còn Trống
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="w-4 h-4 bg-gray-900 rounded"></span> Đang chọn
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="selectable-mini promo-mini w-4 h-4 rounded"></span> Khuyến mãi
                        </div>
                        {{-- Trạng thái "held" (TimeslotHoldService, xem echo-client.js) — admin đang giữ ô
                             này để tư vấn/lên đơn cho 1 khách khác, tự bỏ khoá sau tối đa 5 phút. Trước
                             đây không có mục chú thích nào cho màu này (chỉ có icon khoá nhỏ + tooltip khi
                             hover — vô dụng trên di động), khách không hiểu vì sao ô đang trống lại không
                             bấm được (bug đã gặp: người dùng phản ánh "icon khoá chưa rõ ràng"). --}}
                        <div class="flex items-center gap-1">
                            <span class="w-4 h-4 rounded" style="background:#f59e0b;"></span> Đang tư vấn
                        </div>
                    </div>

                    {{-- Giá + nút đặt sân gộp làm 1 (thay cho bảng tóm tắt giá đầy đủ bên dưới
                         bảng đặt sân — đã ẩn hẳn ở desktop, xem .book-pricing-desktop trong
                         _styles.blade.php) — chỉ HIỆN RÕ khi đã chọn khung giờ, cùng điều kiện với
                         nút "Đặt sân ngay" gốc trong _pricing.blade.php (selectedRoomIsActive
                         === true || selectedRoomId === null). ml-auto đảm bảo luôn nằm cuối hàng
                         (bên phải) dù hàng chú thích trạng thái có xuống dòng hay không. Cố tình
                         KHÔNG dùng x-show (display:none ⇄ flex) — nút vẫn luôn nằm trong layout
                         (chỉ đổi opacity + pointer-events), chừa sẵn chỗ cố định nên khi xuất
                         hiện/biến mất không đẩy hàng chú thích trạng thái xô lệch/xuống dòng. Giá
                         tiền tự cập nhật theo totalAfterAllDiscounts mỗi khi chọn thêm/bớt khung
                         giờ (Alpine reactivity, không cần xử lý thêm). --}}
                    <button @click="$wire.saveAndRedirect(selectedSlots)" :disabled="selectedSlots.length === 0"
                        class="ml-auto shrink-0 transition-opacity duration-300 book-cta-btn book-legend-cta-btn"
                        :class="selectedSlots.length > 0 && (selectedRoomIsActive === true || selectedRoomId === null) ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                        <span x-text="totalAfterAllDiscounts.toLocaleString() + ' đ'"></span> | Đặt sân
                    </button>
                </div>
