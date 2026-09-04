{{-- Popup Mua gói nhiều buổi — dùng <dialog> NGUYÊN SINH của trình duyệt (showModal()) thay vì tự
     dựng bằng position:fixed. Đã thử 2 cách trước đó (đặt lồng trong .book-panel, rồi x-teleport
     ra <body>, rồi đặt ở gốc x-data của Book component) — CẢ 3 đều vẫn bị co nhỏ dính góc trên-trái
     giống hệt nhau, chứng tỏ có 1 khối cha nào đó (rất có thể ở booking-board.blade.php/layout
     chung, ngoài tầm kiểm soát của riêng book.blade.php) đang có transform/filter/contain phá vỡ
     position:fixed — dò từng file CSS tốn quá nhiều thời gian mà không chắc tìm hết.

     <dialog>.showModal() render ở "top layer" của trình duyệt — 1 tầng HOÀN TOÀN TÁCH BIỆT khỏi
     cây stacking context thông thường của trang, MIỄN NHIỄM tuyệt đối với mọi transform/overflow/
     z-index của bất kỳ khối cha nào, dù nó có nằm sâu bao nhiêu lớp đi nữa. Trình duyệt TỰ ĐỘNG
     căn giữa màn hình + tự vẽ ::backdrop mờ phía sau, không cần tự dựng div overlay/flex-center
     tay như trước (giảm hẳn 1 lớp code có thể sai). --}}
@php $sellablePackagePlans = $this->getAvailablePackagePlans(); @endphp
@if ($sellablePackagePlans->isNotEmpty())
    {{-- Đánh dấu phiên bản đang chạy — kiểm tra Console (F12) thấy đúng dòng này nghĩa là trình
         duyệt đã tải bản mới. --}}
    <script>console.log('[book-package-modal] build 2026-08-24-native-dialog-3');</script>

    <style>
        #book-package-dialog {
            border: none;
            border-radius: 16px;
            padding: 0;
            width: 100%;
            /* To hơn bản cũ (420px) — đủ chỗ hiện tên NHIỀU sân trên 1 dòng mà không bị bóp chữ,
               theo đúng yêu cầu "popup to hơn, hiển thị thông tin rõ hơn". */
            max-width: 560px;
            max-height: 92vh;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        }
        /* Trình duyệt tự vẽ, chỉ cần khai màu — không cần tự dựng div overlay nữa. */
        #book-package-dialog::backdrop {
            background: rgba(0, 0, 0, .45);
        }
    </style>

    {{-- x-effect chạy lại mỗi khi showPackageModal đổi giá trị (kể cả đổi từ nơi khác, VD nút
         "Mua gói nhiều buổi" ở book/_package-plans.blade.php) — gọi ĐÚNG method gốc của <dialog>
         thay vì chỉ toggle CSS display, để trình duyệt tự quản lý top layer/focus-trap/ESC đúng
         chuẩn. @close (sự kiện gốc <dialog>, tự bắn khi bấm ESC hoặc form method="dialog") đồng bộ
         ngược lại state Alpine, tránh 2 nguồn sự thật lệch nhau. --}}
    <dialog id="book-package-dialog"
            x-ref="packageDialog"
            x-effect="$refs.packageDialog.open !== showPackageModal && (showPackageModal ? $refs.packageDialog.showModal() : $refs.packageDialog.close())"
            @close="showPackageModal = false"
            @click="if ($event.target === $refs.packageDialog) { showPackageModal = false; }">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:16px; border-bottom:1px solid #F0F0F0;">
            <p style="font-size:16px; font-weight:700; color:#222222; margin:0;">Mua gói nhiều buổi</p>
            <button type="button" @click="showPackageModal = false"
                style="width:28px; height:28px; border-radius:999px; border:none; background:#F7F7F7; color:#717171; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Bỏ overflow-y:auto cố định (trước đây luôn hiện thanh cuộn dù nội dung ngắn, do
             max-height tính cứng calc(85vh - 60px) nhỏ hơn cần thiết) — max-height rộng hơn hẳn
             (92vh) nên hầu như không bao giờ cần cuộn nữa với vài gói thông thường; overflow-y:auto
             CHỈ còn là lưới an toàn cho trường hợp hiếm gặp (rất nhiều gói cùng lúc), không tự
             hiện thanh cuộn khi nội dung vừa đủ. --}}
        <div style="padding:20px; max-height:calc(92vh - 64px); overflow-y:auto;">
            @if ($isAuthUser)
                <p style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:#059669; margin:0 0 8px;">
                    <svg style="width:14px; height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Đã tự điền từ tài khoản đăng nhập
                </p>
            @endif
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px;">
                <input type="text" wire:model.defer="buyerName" placeholder="Họ và tên"
                       style="border:1px solid #DDDDDD; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
                <input type="tel" wire:model.defer="buyerPhone" placeholder="Số điện thoại"
                       style="border:1px solid #DDDDDD; border-radius:8px; padding:8px 10px; font-size:13px; box-sizing:border-box;">
            </div>
            @error('buyerPhone')
                <p style="color:#dc2626; font-size:12px; margin:-8px 0 12px;">{{ $message }}</p>
            @enderror

            <div style="display:flex; flex-direction:column; gap:10px;">
                @foreach ($sellablePackagePlans as $plan)
                    {{-- Tên các sân áp dụng — 3 phạm vi loại trừ nhau, xem PackagePlan::scopeType()
                         (Modules\BladeThemeV1\Livewire\Book::getAvailablePackagePlans()). TRƯỚC ĐÂY
                         chỉ có 2 nhánh (cả chi nhánh / 1 sân riêng qua $plan->product), thiếu hẳn
                         nhánh "một số sân cụ thể" (product_id null, product_ids có danh sách) nên
                         rơi vào else và hiện "Chỉ dùng cho sân" TRỐNG KHÔNG (bug thật đã gặp — không
                         liệt kê được sân nào vì $plan->product cũng null nốt).
                         $plan->products() ở đây trả về Eloquent Builder (không phải Relation thật,
                         xem PackagePlan::products()) nên PHẢI ->get()/->pluck() tường minh, không
                         eager-load được qua ->load()/with(). --}}
                    @php
                        $scopeNames = match ($plan->scopeType()) {
                            'product' => array_filter([$plan->product?->name]),
                            'selected_products' => $plan->products()->pluck('name')->all(),
                            'branch' => [],
                        };
                    @endphp
                    <div style="border:1px solid #DDDDDD; border-radius:12px; padding:14px;">
                        <p style="font-size:15px; font-weight:600; color:#222222; margin:0;">{{ $plan->name }}</p>
                        @if ($plan->scopeType() === 'branch')
                            <p style="font-size:12px; font-weight:500; margin:4px 0 0; color:#2563eb;">
                                Dùng cho mọi sân tại {{ $plan->category?->name }}
                            </p>
                        @else
                            {{-- Hiện DẠNG LIST (mỗi sân 1 "chip" nổi bật) thay vì gộp chung 1 dòng
                                 text nối dấu phẩy — dễ nhận diện hơn khi gói áp dụng cho NHIỀU sân
                                 cùng lúc (phạm vi "Một số sân trong chi nhánh"). --}}
                            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:4px; margin:6px 0 0;">
                                <span style="font-size:11px; font-weight:500; color:#9CA3AF;">Chỉ dùng cho:</span>
                                @forelse ($scopeNames as $name)
                                    <span style="display:inline-flex; align-items:center; padding:2px 9px; border-radius:999px; background:#EFF6FF; color:#2563EB; font-size:11.5px; font-weight:600; white-space:nowrap;">{{ $name }}</span>
                                @empty
                                    <span style="font-size:11px; color:#9CA3AF;">—</span>
                                @endforelse
                            </div>
                        @endif
                        <p style="font-size:13px; color:#717171; margin:6px 0 10px;">
                            {{ $plan->sessions_count }} buổi — {{ number_format($plan->price, 0, ',', '.') }}đ
                            @if ($plan->savingsPercent() > 0)
                                <span style="color:#059669; font-weight:600;">(tiết kiệm {{ $plan->savingsPercent() }}%)</span>
                            @endif
                            @if ($plan->validity_days)
                                · Hạn dùng {{ $plan->validity_days }} ngày
                            @endif
                        </p>
                        <button type="button" wire:click="buyPackage({{ $plan->id }})"
                            wire:loading.attr="disabled" wire:target="buyPackage({{ $plan->id }})"
                            style="width:100%; padding:10px; border-radius:8px; border:none; background:#0B6B3A; color:#fff; font-size:14px; font-weight:600; cursor:pointer;">
                            <span wire:loading.remove wire:target="buyPackage({{ $plan->id }})">Mua gói</span>
                            <span wire:loading wire:target="buyPackage({{ $plan->id }})">Đang xử lý...</span>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </dialog>
@endif
