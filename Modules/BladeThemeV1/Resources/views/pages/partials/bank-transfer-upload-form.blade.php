{{--
    Form upload ảnh biên lai chuyển khoản — dùng chung cho 2 tình huống ở booking-detail.blade.php:
    (1) đối tác KHÔNG có SePay tự động → bắt buộc ($required = true), (2) đối tác CÓ SePay tự động
    → chỉ là backup tuỳ chọn, ẩn sau 1 link "chưa thấy xác nhận" ($required = false).

    Dùng Alpine để: (a) ẩn hẳn input file thật (mỗi trình duyệt style nút "Chọn tệp" mặc định khác
    nhau, không đồng bộ được màu qua CSS ::file-selector-button một cách đáng tin cậy), thay bằng 1
    nút bấm tự vẽ; (b) xem trước ảnh vừa chọn bằng URL.createObjectURL thay vì chỉ hiện tên file;
    (c) nút submit luôn hiện rõ ràng, chỉ bật màu/enable sau khi đã chọn ảnh.

    Props: Order $order, bool $required.
--}}
<form
    x-data="{ fileName: null, previewUrl: null, uploading: false,
        onFile(e) {
            const f = e.target.files[0];
            if (! f) { this.fileName = null; this.previewUrl = null; return; }
            this.fileName = f.name;
            this.previewUrl = URL.createObjectURL(f);
        } }"
    action="{{ route('booking.upload-payment-proof', ['code' => $order->order_code]) }}"
    method="POST" enctype="multipart/form-data"
    @submit="uploading = true">
    @csrf
    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">
        {{ $required ? 'Đã chuyển khoản xong? Tải ảnh biên lai lên đây' : 'Tải ảnh biên lai để admin hỗ trợ xác nhận thủ công' }}
    </label>

    <input x-ref="fileInput" type="file" name="payment_proof" accept="image/*" @if($required) required @endif
        class="hidden" @change="onFile($event)" />

    <div x-show="! previewUrl" x-cloak>
        <button type="button" @click="$refs.fileInput.click()"
            class="w-full h-24 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50/50 flex flex-col items-center justify-center gap-1 text-amber-700 hover:bg-amber-50 transition-colors">
            <i class="fa fa-camera text-lg"></i>
            <span class="text-sm font-semibold">Chọn ảnh biên lai chuyển khoản</span>
        </button>
    </div>

    <div x-show="previewUrl" x-cloak class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50">
        <img :src="previewUrl" class="w-16 h-16 rounded-lg object-cover border border-gray-200 shrink-0" alt="Xem trước biên lai">
        <span class="flex-1 min-w-0 text-sm text-gray-700 truncate" x-text="fileName"></span>
        <button type="button" @click="$refs.fileInput.click()" class="text-xs font-semibold text-amber-700 hover:underline shrink-0">Đổi ảnh</button>
    </div>

    @error('payment_proof')
        <p class="text-red-600 text-xs mt-1.5">{{ $message }}</p>
    @enderror

    <button type="submit" :disabled="! previewUrl || uploading"
        :class="(! previewUrl || uploading) ? 'opacity-50 cursor-not-allowed' : 'hover:opacity-90'"
        class="mt-3 w-full h-12 rounded-xl text-white text-sm font-bold shadow-sm transition-opacity"
        style="background-color:#B45309;">
        <span x-show="! uploading">✅ {{ $required ? 'Xác nhận đã chuyển khoản' : 'Gửi ảnh hỗ trợ xác nhận' }}</span>
        <span x-show="uploading" x-cloak>Đang gửi...</span>
    </button>
</form>
