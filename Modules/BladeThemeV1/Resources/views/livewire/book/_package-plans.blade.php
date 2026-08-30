{{-- Mua gói nhiều buổi — nút nhỏ mở popup (nội dung popup thật nằm ở book/_package-modal.blade.php,
     include Ở GỐC TRANG chứ KHÔNG include ngay đây — xem ghi chú tại đó lý do). KHÔNG nhét trực
     tiếp vào luồng trang (bản trước dùng class .book-cta-btn (vốn width:100% cho CTA mobile) bên
     trong hàng flex làm vỡ layout — chữ bị bóp xuống 1 từ/dòng, nút đè chồng lên nội dung, xem bug
     thực tế đã gặp). Chỉ hiện khi chi nhánh có ít nhất 1 gói đang bán (is_active=true — riêng 1
     sân hoặc cả chi nhánh, xem Book::getAvailablePackagePlans()). --}}
@if ($this->getAvailablePackagePlans()->isNotEmpty())
    {{-- Tự điền họ tên/SĐT nếu khách ĐÃ đăng nhập — cùng cơ chế token localStorage với
         infomation-book-room.blade.php (ProductDetail), trước đây thiếu ở đây khiến khách đăng
         nhập rồi vẫn phải gõ lại từ đầu mỗi lần mở popup mua gói. --}}
    <div x-data="{}" x-init="const token = localStorage.getItem('auth_token');
    if (token) $wire.prefillFromAuth(token);
    window.addEventListener('auth-state-changed', () => {
        const t = localStorage.getItem('auth_token');
        $wire.prefillFromAuth(t || '');
    });"></div>

    {{-- margin-left:auto đẩy nút về cuối hàng chú thích (bên phải), giống cách nút "Đặt sân" ở
         book/_legend.blade.php dùng ml-auto trong cùng 1 hàng flex. --}}
    <button type="button" @click="showPackageModal = true"
        style="display:inline-flex; align-items:center; gap:6px; margin-left:auto; padding:6px 12px; border:1px solid #DDDDDD; border-radius:999px; background:#fff; font-size:12.5px; font-weight:600; color:#222222; cursor:pointer; white-space:nowrap;">
        <svg style="width:15px; height:15px; color:#0B6B3A; flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Mua gói nhiều buổi
    </button>
@endif
