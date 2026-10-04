@extends('bladethemev1::layouts.master')

@section('title', 'Tra cứu đặt sân - ' . $order->order_code)

@section('content')
    <x-bladethemev1::home.top-bar />
    <x-bladethemev1::home.search-bar />
    @livewire('bladethemev1::drawer-menu')

    <main class="bg-gray-50/70 border-t border-gray-100">
    <div class="max-w-screen-xl md:px-8 px-3 mx-auto py-6 md:py-10">
        <div class="max-w-[700px] min-h-[500px] w-full mx-auto">
            <div id="result" class="pb-5 relative">

                {{-- Thông báo khi vừa thanh toán cọc thành công --}}
                @if(session('deposit_success'))
                <div class="mb-4 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3">
                    <span class="text-2xl">💰</span>
                    <div>
                        <p class="font-semibold text-amber-800">Thanh toán cọc thành công!</p>
                        <p class="text-sm text-amber-700 mt-0.5">Vui lòng thanh toán số tiền còn lại khi nhận sân hoặc
                            qua link bên dưới.</p>
                    </div>
                </div>
                @endif

                {{-- Kết quả huỷ đơn (xem BladeThemeV1Controller::cancelBooking()) --}}
                @if(session('cancel_success') !== null)
                <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 flex items-start gap-3">
                    <span class="text-2xl">✅</span>
                    <div>
                        <p class="font-semibold text-green-800">Đã huỷ đơn thành công.</p>
                        <p class="text-sm text-green-700 mt-0.5">
                            @if (session('cancel_success') > 0)
                                Số tiền hoàn: <strong>{{ number_format(session('cancel_success'), 0, ',', '.') }}đ</strong> —
                                chúng tôi sẽ liên hệ chuyển khoản trong thời gian sớm nhất.
                            @else
                                Đơn chưa phát sinh khoản hoàn nào.
                            @endif
                        </p>
                    </div>
                </div>
                @endif
                @if(session('cancel_error'))
                <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3">
                    <span class="text-2xl">⚠️</span>
                    <p class="text-sm text-red-700">{{ session('cancel_error') }}</p>
                </div>
                @endif

                @php
                // Order-level data
                $branchName = $order->category ? $order->category->name : 'Chi nhánh';
                $create_at = $order->created_at ? \Carbon\Carbon::parse($order->created_at)->format('d/m/Y H:i') :
                'N/A';

                $statusLabels = [
                'pending' => 'Chờ thanh toán',
                'paid' => 'Đã thanh toán',
                'deposit' => 'Đã thanh toán cọc',
                'failed' => 'Thanh toán thất bại',
                'cancelled_payment' => 'Hủy thanh toán',
                ];
                $statusVietnamese = $statusLabels[$order->status] ?? $order->status;
                $isDepositPaid = $order->status === 'deposit';
                $isFullyPaid = $order->status === 'paid';
                $hasDeposit = $order->deposit_percent !== null;
                $buyerName = $order->buyer_name ?? 'Khách hàng';

                // Helper for product info (using first item's product for order-level defaults)
                $firstItem = $order->items->first();
                $firstProduct = $firstItem ? $firstItem->product : null;
                $checkinDate = $firstItem && $firstItem->checkin_date ? \Carbon\Carbon::parse($firstItem->checkin_date) : null;
                $branchAddress = $firstProduct ? $firstProduct->address : 'Địa chỉ chi nhánh không xác định';
                // Cột map_url đã bị xoá khỏi bảng products (migration 2026_06_04_000002) — ưu tiên
                // dùng toạ độ latitude/longitude (nếu có) để ghim đúng vị trí thay vì chỉ tìm theo
                // địa chỉ text, cùng logic với product-detail.blade.php.
                $branchHasCoords = $firstProduct && !empty($firstProduct->latitude) && !empty($firstProduct->longitude);
                $branchMapQuery = $branchHasCoords ? $firstProduct->latitude . ',' . $firstProduct->longitude : $branchAddress;
                // Dạng "dir" (api=1) thay vì "search" để bấm vào là app Maps vào thẳng chế độ chỉ
                // đường (tự lấy vị trí hiện tại của người dùng làm điểm xuất phát).
                $mapUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($branchMapQuery);
                $hotline = $firstProduct ? $firstProduct->hotline : '';

                $targetUrl = route('booking.detail', ['code' => $order->order_code]);
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=10&data=" .
                urlencode($targetUrl);

                // Ảnh mặc định khi sân chưa có "Ảnh bìa" — dùng SVG nhúng thẳng (data URI) thay vì
                // gọi via.placeholder.com: domain đó không load được trên nhiều mạng VN, làm hiện ra
                // chữ "Room" (alt text) thay vì ảnh khi <img> lỗi tải.
                $noImageUrl = 'data:image/svg+xml;base64,' . base64_encode(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#e5e7eb"/><text x="50" y="55" font-size="13" text-anchor="middle" fill="#9ca3af" font-family="Arial, sans-serif">Sân</text></svg>'
                );

                // Gộp các order_item CÙNG 1 sân + CÙNG 1 ngày thành 1 thẻ duy nhất (thay vì mỗi
                // khung giờ 1 giờ lại vẽ lại NGUYÊN 1 thẻ đầy đủ ảnh/badge/timeline riêng — đặt 2-3
                // khung giờ liên tiếp cho cùng 1 sân trước đây hiện lặp lại rất rối mắt). Hiển thị
                // gộp: 1 ảnh, 1 tên sân, khung giờ SỚM NHẤT → MUỘN NHẤT trong nhóm.
                $groupedBookingItems = $isPackageBooking
                    // Dùng gói tính mỗi order_item là đúng 1 buổi, vì vậy phải hiện đủ từng
                    // khung thay vì gộp thành một khoảng giờ làm khách tưởng bị thiếu buổi.
                    ? $order->items
                        ->whereNotNull('product_id')
                        ->sortBy('checkin_date')
                        ->values()
                    : $order->items->groupBy(function ($item) {
                        $date = $item->checkin_date ? \Carbon\Carbon::parse($item->checkin_date)->format('Y-m-d') : 'unknown';
                        return $item->product_id . '|' . $date;
                    })
                    ->map(function ($group) {
                        $first = $group->first();
                        return (object) [
                            'product'      => $first->product,
                            'name'         => $first->name,
                            'guest_count'  => $first->guest_count ?? 1,
                            'checkin_date' => $group->min('checkin_date'),
                            'checkout_date' => $group->max('checkout_date'),
                        ];
                    })
                    ->sortBy('checkin_date')
                    ->values();
                @endphp

                {{-- Luồng "chuyển khoản ngân hàng thủ công" (payment_method='bank_transfer') — chỉ
                     hiện khi đơn CHƯA thanh toán đủ và CHƯA huỷ. 3 trạng thái con:
                     1) Chưa upload ảnh biên lai → hiện QR + form upload.
                     2) Đã upload, bank_transfer_status='pending' → hiện "đang chờ admin xác nhận".
                     3) bank_transfer_status='rejected' → hiện lý do bị từ chối + cho upload lại. --}}
                @if ($order->payment_method === 'bank_transfer' && ! $order->isCancelled() && ! in_array($order->status, ['paid', 'refunded']))
                    @php
                        $bankPartner = $order->category?->partner ?? \App\Models\Partner::find($order->partner_id);
                        $bankAmount  = $order->status === 'deposit' ? $order->depositDueAmount() : (int) $order->full_amount;
                        $bankQrUrl   = $bankPartner?->vietQrImageUrl($bankAmount, $order->order_code);
                        // Partner::bankTransferMode() — ĐIỂM DUY NHẤT quyết định 'sepay' (hệ thống
                        // tự đối chiếu giao dịch, không cần khách upload gì) hay 'manual' (bắt buộc
                        // upload ảnh, admin duyệt tay), theo đúng lựa chọn admin đã chốt ở
                        // "Phương thức thanh toán áp dụng" — KHÔNG còn trộn 2 luồng như trước (trước
                        // đây SePay vẫn kèm link ẩn "chưa thấy xác nhận, tải ảnh" phía dưới).
                        $isSepayAuto = $bankPartner?->bankTransferMode() === 'sepay';
                    @endphp
                    <div class="bg-white mb-5 rounded-2xl border border-amber-200 shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
                        <div class="px-6 py-4 border-b border-amber-100 bg-amber-50/60">
                            <p class="text-sm font-semibold text-amber-800">💳 Thanh toán bằng chuyển khoản ngân hàng</p>
                        </div>
                        <div class="p-6">
                            @if ($order->bank_transfer_status === 'pending')
                                <div class="flex items-start gap-3 p-4 rounded-xl bg-blue-50 border border-blue-200">
                                    <span class="text-2xl">⏳</span>
                                    <div>
                                        <p class="text-sm font-semibold text-blue-800">Đã nhận ảnh biên lai — đang chờ xác nhận</p>
                                        <p class="text-xs text-blue-600 mt-1">Nhân viên sẽ kiểm tra và xác nhận thanh toán trong thời gian sớm nhất. Trang này sẽ tự cập nhật khi đơn được duyệt.</p>
                                    </div>
                                </div>
                            @else
                                @if ($order->bank_transfer_status === 'rejected')
                                    <div class="flex items-start gap-3 p-4 mb-4 rounded-xl bg-red-50 border border-red-200">
                                        <span class="text-2xl">⚠️</span>
                                        <div>
                                            <p class="text-sm font-semibold text-red-700">Ảnh biên lai trước đó chưa hợp lệ</p>
                                            <p class="text-xs text-red-600 mt-1">Vui lòng kiểm tra lại giao dịch và upload ảnh biên lai khác, hoặc liên hệ chi nhánh để được hỗ trợ.</p>
                                        </div>
                                    </div>
                                @endif

                                @if ($bankQrUrl)
                                    <div class="flex flex-col sm:flex-row gap-6 items-center sm:items-start">
                                        <img src="{{ $bankQrUrl }}" alt="QR chuyển khoản" class="w-52 h-52 rounded-xl border border-gray-200 shrink-0">
                                        <div class="flex-1 space-y-1.5 text-sm">
                                            <p><span class="text-gray-500">Ngân hàng:</span> <span class="font-semibold">{{ $bankPartner->bank_name }}</span></p>
                                            <p><span class="text-gray-500">Số tài khoản:</span> <span class="font-semibold">{{ $bankPartner->bank_account_number }}</span></p>
                                            <p><span class="text-gray-500">Chủ tài khoản:</span> <span class="font-semibold">{{ $bankPartner->bank_account_holder }}</span></p>
                                            <p><span class="text-gray-500">Số tiền:</span> <span class="font-semibold" style="color:#B45309;">{{ number_format($bankAmount, 0, ',', '.') }}đ</span></p>
                                            <p><span class="text-gray-500">Nội dung CK:</span> <span class="font-semibold">{{ $order->order_code }}</span></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-3">* Vui lòng chuyển khoản ĐÚNG số tiền và nội dung để được xác nhận nhanh nhất.</p>
                                @else
                                    <p class="text-sm text-gray-500">Chi nhánh chưa cấu hình đầy đủ thông tin ngân hàng — vui lòng liên hệ trực tiếp để được hỗ trợ thanh toán.</p>
                                @endif

                                @if ($isSepayAuto)
                                    {{-- Chế độ "SePay tự động" — CHỈ hiện thông báo tự động, KHÔNG có
                                         form upload ảnh nào cả (không trộn 2 luồng). --}}
                                    <div class="mt-5 pt-5 border-t border-gray-100">
                                        <div class="flex items-start gap-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200">
                                            <span class="text-xl">⚡</span>
                                            <div>
                                                <p class="text-sm font-semibold text-emerald-800">Hệ thống sẽ tự động xác nhận sau khi bạn chuyển khoản</p>
                                                <p class="text-xs text-emerald-600 mt-0.5">Chuyển ĐÚNG số tiền và nội dung CK ở trên — trang này sẽ tự cập nhật, không cần làm gì thêm.</p>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    {{-- Chế độ "Xác nhận qua ảnh" — CHỈ hiện form upload bắt buộc. --}}
                                    <div class="mt-5 pt-5 border-t border-gray-100">
                                        @include('bladethemev1::pages.partials.bank-transfer-upload-form', ['order' => $order, 'required' => true])
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endif

                <div
                    class="bg-white mb-5 rounded-2xl border border-gray-200 shadow-[0_8px_30px_rgb(0,0,0,0.04)] overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-start bg-gray-50/50">
                        <div>
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Mã đặt sân</p>
                            <div class="flex items-center gap-2">
                                <span class="text-xl font-semibold tracking-tight text-gray-900">{{ $order->order_code }}</span>
                            </div>
                            <span class="text-base font-normal italic tracking-tight text-gray-600">Dear: {{ $buyerName }}</span>
                        </div>
                        <div class="flex flex-col items-end">
                            @if($order->isCancelled())
                            <span class="inline-flex items-center gap-1 px-3 py-2 rounded-full text-gray-600 text-xs font-medium border border-gray-300 bg-gray-100">
                                    🚫 Đã huỷ
                                </span>
                            @elseif($isDepositPaid)
                            <span class="inline-flex items-center gap-1 px-3 py-2 rounded-full text-amber-700 text-xs font-medium border border-amber-200" style="background-color: rgba(255,251,235);">
                                    💰 Đã cọc {{ $order->deposit_percent }}% — Chờ thanh toán còn lại
                                </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-3 py-2 rounded-full text-green-700 text-xs font-medium border border-green-100" style="background-color: rgba(243 250 247);">
                                    <i class="fa fa-check-circle"></i>
                                    {{ ucfirst($statusVietnamese) }}
                                </span>
                            @endif
                            <span class="text-sm text-gray-400 mt-1">Đặt ngày {{ $create_at }}</span>

                            {{-- Khách tự huỷ đơn/xin hoàn tiền — chỉ hiện khi còn huỷ được (xem
                                 Order::canBeCancelledByCustomer(): đã thanh toán, chưa huỷ, chưa
                                 tới giờ nhận sân). --}}
                            @if($order->canBeCancelledByCustomer())
                            <div x-data="{ confirming: false }" class="mt-2">
                                <button type="button" @click="confirming = true"
                                    class="text-xs font-medium text-red-600 hover:text-red-700 underline underline-offset-2">
                                    Huỷ đơn / xin hoàn tiền
                                </button>
                                <div x-show="confirming" x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                                    <div @click.outside="confirming = false" class="bg-white rounded-2xl p-5 max-w-sm w-full shadow-xl">
                                        <p class="font-semibold text-gray-900 mb-1">Xác nhận huỷ đơn?</p>
                                        <p class="text-sm text-gray-600 mb-4">
                                            Bạn sẽ được hoàn <strong>{{ number_format($order->computeCustomerCancelRefundAmount(), 0, ',', '.') }}đ</strong>
                                            (nhận lại đúng 70% số tiền đã thanh toán; 30% còn lại là phí huỷ và xử lý). Thao tác này không thể hoàn tác.
                                        </p>
                                        <form method="POST" action="{{ route('booking.cancel', ['code' => $order->order_code]) }}" class="flex gap-2">
                                            @csrf
                                            <button type="button" @click="confirming = false"
                                                class="flex-1 px-3 py-2 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                Đóng
                                            </button>
                                            <button type="submit"
                                                class="flex-1 px-3 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                                Xác nhận huỷ
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="p-6">
                        @if ($isPackagePurchase)
                        {{-- Đơn mua gói nhiều buổi — không có khung giờ thật để hiện. CHỈ
                             hiện thẻ "đã kích hoạt" khi $customerPackage THỰC SỰ tồn tại (được tạo
                             qua CustomerPackage::activateFromOrder() sau khi PayOS báo paid HOẶC
                             admin xác nhận chuyển khoản — xem BankTransferConfirmationService).
                             Trước đây hiện "đã được kích hoạt!" ngay cả khi đơn còn "Chờ thanh
                             toán" (chuyển khoản ngân hàng, chưa được admin duyệt) — bug đã gặp:
                             khách tưởng nhầm gói đã dùng được trong khi CustomerPackage chưa hề
                             tồn tại. --}}
                        @if ($customerPackage)
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 flex items-start gap-3">
                            <span class="text-3xl">🎫</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-lg font-semibold text-emerald-800">
                                    Gói "{{ $customerPackage->name }}" đã được kích hoạt!
                                </p>
                                <p class="text-sm text-emerald-700 mt-1">
                                    Còn <strong>{{ $customerPackage->sessions_remaining }}</strong>/{{ $customerPackage->sessions_total }} buổi
                                    cho sân <strong>{{ $customerPackage->product?->name }}</strong>.
                                    @if ($customerPackage->expires_at)
                                        Hết hạn {{ $customerPackage->expires_at->format('d/m/Y') }}.
                                    @endif
                                </p>
                                <p class="text-xs text-emerald-600 mt-2">
                                    Số điện thoại <strong>{{ $customerPackage->buyer_phone }}</strong> — nhập đúng số này khi đặt sân để tự động dùng gói thay vì trả tiền.
                                </p>
                            </div>
                        </div>
                        @else
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-start gap-3">
                            <span class="text-3xl">⏳</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-lg font-semibold text-amber-800">
                                    Gói "{{ $order->description }}" đang chờ xác nhận thanh toán
                                </p>
                                <p class="text-sm text-amber-700 mt-1">
                                    Gói sẽ được kích hoạt tự động ngay sau khi chúng tôi xác nhận đã nhận được tiền chuyển khoản của bạn.
                                </p>
                            </div>
                        </div>
                        @endif
                        @else
                        @foreach($groupedBookingItems as $item)
                        @php
                        $itemProduct = $item->product;
                        $checkIn = $item->checkin_date ? \Carbon\Carbon::parse($item->checkin_date)->format('d/m/Y H:i') : 'N/A';
                        $checkOut = $item->checkout_date ? \Carbon\Carbon::parse($item->checkout_date)->format('d/m/Y H:i') : 'N/A';
                        $thumbnailUrl = $itemProduct && $itemProduct->getFirstMedia('Ảnh bìa') ?
                        $itemProduct->getFirstMedia('Ảnh bìa')->getUrl() : $noImageUrl;
                        $itemGuestCount = $item->guest_count;
                        @endphp

                        <div class="@if(!$loop->first) mt-6 pt-6 border-t border-gray-100 @endif">
                            <div class="sm:flex block justify-between items-center">
                                <div class="flex gap-5 mb-4 md:mb-0">
                                    <div
                                        class="w-20 h-20 rounded-lg bg-gray-100 shrink-0 overflow-hidden border border-gray-100">
                                        <img src="{{ $thumbnailUrl }}" alt="Sân" class="w-full h-full object-cover"
                                            onerror="this.onerror=null;this.src='{{ $noImageUrl }}';">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-semibold text-gray-900 truncate">{{ $item->name }}</h3>
                                        <div class="flex items-center gap-4 mt-3">
                                            <div
                                                class="flex items-center gap-1 text-xs font-medium text-gray-600 bg-gray-100 px-2 py-1 rounded-md">
                                                <i class="fa fa-users"></i>
                                                {{ $itemGuestCount }} Người
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Timeline -->
                            <div class="mt-8 px-1 flex items-center justify-between relative">
                                <div class="absolute top-1/2 left-0 w-full h-px bg-gray-100" style="z-index: -10;">
                                </div>
                                <div class="flex flex-col gap-1 pr-4 bg-white">
                                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Nhận sân</span>
                                    <div class="flex items-center gap-2">
                                        <x-filament::icon-button icon="heroicon-m-arrow-right-end-on-rectangle"
                                            color="black" size="xs" />
                                        <span class="text-sm sm:text-base text-center sm:text-left font-semibold text-gray-900">{{ $checkIn }}</span>
                                    </div>
                                </div>
                                <div
                                    class="w-8 h-8 rounded-full bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-400 bg-white">
                                    <x-filament::icon-button icon="heroicon-m-arrow-long-right" color="black"
                                        size="sm" />
                                </div>
                                <div class="flex flex-col gap-1 pl-4 items-end bg-white">
                                    <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Trả sân</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm sm:text-base text-center sm:text-left font-semibold text-gray-900">{{ $checkOut }}</span>
                                        <x-filament::icon-button icon="heroicon-m-arrow-right-start-on-rectangle"
                                            color="black" size="xs" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach

                        {{-- Khung địa chỉ --}}
                        <div
                            class="mt-4 bg-slate-50/80 rounded-xl border border-dashed border-gray-300 p-4 flex sm:flex-row gap-4 sm:items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 bg-white rounded-lg border border-gray-200 flex items-center justify-center text-gray-700 shadow-sm shrink-0">
                                    <x-filament::icon-button icon="heroicon-m-map-pin" color="black" size="sm" />
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500 truncate mt-0.5">Địa chỉ sân</p>
                                    <span class="text-sm font-semibold text-gray-900">{{ $branchAddress }}</span>
                                </div>
                            </div>
                            <a href="{{ $mapUrl }}"
                                target="_blank"
                                class="shrink-0 text-xs font-semibold text-blue-600 underline hover:text-blue-800 transition-colors whitespace-nowrap">
                                Xem bản đồ
                            </a>
                        </div>

                        @include('bladethemev1::components.order.addons', ['order' => $order])
                        @endif {{-- /$isPackagePurchase --}}

                        <div style="padding-top: 1.5rem" class="mt-8 border-t border-gray-100 space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Nội quy</span>
                                <span class="font-medium text-gray-900">
                                    <a class="text-primary underline font-semibold" href="{{ url('noi-quy-va-quy-dinh') }}">Xem nội quy tại đây</a>
                                </span>
                            </div>
                            @if($hasDeposit)
                            @php
                            $fullAmt2 = (int)($order->full_amount ?? $order->amount);
                            $paidAmt2 = (int)$order->amount;
                            $remaining2 = $fullAmt2 - $paidAmt2;
                            $pct2 = (int)$order->deposit_percent;
                            @endphp
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Tổng tiền sân</span>
                                <span class="font-semibold text-gray-900">{{ number_format($fullAmt2, 0, ',', '.') }}đ</span>
                            </div>
                            @if($isDepositPaid)
                            <div class="flex justify-between text-sm">
                                <span class="text-amber-700 font-semibold">Đã cọc ({{ $pct2 }}%)</span>
                                <span class="font-bold text-amber-700">{{ number_format($paidAmt2, 0, ',', '.') }}đ ✓</span>
                            </div>
                            <div class="flex justify-between text-base pt-1 border-t border-dashed border-amber-200">
                                <span class="font-semibold text-red-600">Còn lại cần thanh toán</span>
                                <span class="font-bold text-red-600">{{ number_format($remaining2, 0, ',', '.') }}đ</span>
                            </div>
                            @else
                            <div class="flex justify-between text-sm">
                                <span class="text-amber-700 font-semibold">Đã cọc ({{ $pct2 }}%)</span>
                                <span class="font-bold text-amber-700">{{ number_format($paidAmt2, 0, ',', '.') }}đ ✓</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-green-700 font-semibold">Đã thanh toán đủ</span>
                                <span class="font-bold text-green-700">✓ Hoàn tất</span>
                            </div>
                            @endif
                            @elseif($isPackageBooking)
                            <div class="flex justify-between text-base pt-2">
                                <span class="font-semibold text-gray-900">Thanh toán bằng gói</span>
                                <span class="font-bold text-emerald-700 tracking-tight">
                                    {{ $packageRedemptions->count() }} buổi{{ $redeemedPackage ? ' — ' . $redeemedPackage->name : '' }}
                                </span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Số tiền cần thanh toán</span>
                                <span class="font-semibold text-emerald-700">0đ</span>
                            </div>
                            @else
                            <div class="flex justify-between text-base pt-2">
                                <span class="font-semibold text-gray-900">Tổng thanh toán</span>
                                <span class="font-bold text-gray-900 tracking-tight">{{ number_format($order->amount, 0, ',', '.') . 'đ' }}</span>
                            </div>
                            @endif

                            @if($isDepositPaid)
                            {{-- KHỐI THANH TOÁN TIỀN CÒN LẠI --}}
                            <div id="remaining-payment-block" class="rounded-xl border-2 border-amber-300 p-4 mt-3"
                                style="background:#fffbeb;" x-data="{
                                         loading: false,
                                         checkoutUrl: '{{ $order->remaining_checkout_url ?? '' }}',
                                         error: '',
                                         async createPayment() {
                                             if (this.checkoutUrl) { window.location.href = this.checkoutUrl; return; }
                                             this.loading = true;
                                             this.error = '';
                                             try {
                                                 const res = await fetch('{{ route('payment.remaining.create', $order->order_code) }}', {
                                                     method: 'POST',
                                                     headers: {
                                                         'Content-Type': 'application/json',
                                                         'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]') ? document.querySelector('meta[name=csrf-token]').content : ''
                                                     },
                                                 });
                                                 const json = await res.json();
                                                 if (json.error === 0 && json.checkoutUrl) {
                                                     this.checkoutUrl = json.checkoutUrl;
                                                     window.location.href = json.checkoutUrl;
                                                 } else {
                                                     this.error = json.message || 'Không thể tạo link thanh toán.';
                                                 }
                                             } catch(e) {
                                                 this.error = 'Lỗi kết nối, vui lòng thử lại.';
                                             }
                                             this.loading = false;
                                         }
                                     }" x-init="
                                         @if(request()->query('remaining_paid') == 1)
                                             // Trang quay lại sau khi trả tiền còn lại, reload để cập nhật trạng thái
                                             setTimeout(() => window.location.reload(), 2000);
                                         @endif
                                     ">
                                <p class="font-bold text-amber-800 text-sm mb-1 flex items-center gap-1">
                                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    Thanh toán phần còn lại để hoàn tất đặt sân
                                </p>
                                <p class="text-xs text-amber-700 mb-3">
                                    Số tiền cần thanh toán:
                                    <strong class="text-base">{{ number_format($remaining2, 0, ',', '.') }}đ</strong>
                                </p>
                                <button @click="createPayment()"
                                            :disabled="loading"
                                            class="w-full py-3 rounded-lg font-bold text-white text-sm flex items-center justify-center gap-2 transition-opacity disabled:opacity-60"
                                            style="background:#4e6b4c;">
                                        <span x-show="!loading">💳 Thanh toán qua QR PayOS</span>
                                        <span x-show="loading" class="flex items-center gap-2">
                                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            Đang tạo link...
                                        </span>
                                    </button>
                                <p x-show="error" x-text="error" class="text-red-600 text-xs mt-2 font-medium"
                                    style="display:none;"></p>
                                <p class="text-[11px] text-amber-600 mt-2 text-center italic">Sau khi thanh toán thành
                                    công, đơn sẽ được xác nhận ngay.</p>
                            </div>
                            @elseif($isFullyPaid && $hasDeposit)
                            <div style="background-color: rgb(235 245 255 / 0.5);"
                                class="text-green-700 rounded-lg p-3 flex gap-3 items-start mt-2">
                                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-xs leading-relaxed">Bạn đã thanh toán đủ (cọc + còn lại). Chúc quý khách có trải nghiệm tuyệt vời tại 365Home!</p>
                            </div>
                            @else
                            <div style="background-color: rgb(235 245 255 / 0.5);"
                                class="text-blue-700 rounded-lg p-3 flex gap-3 items-start mt-2">
                                <x-filament::icon-button icon="heroicon-c-information-circle" color="white" size="sm" />
                                <p class="text-xs leading-relaxed">Bạn đã thanh toán 100%. Vui lòng check-in đúng giờ
                                    khi đến nhận sân. Chúc quý khách có trải nghiệm tuyệt vời tại 365Home!
                                </p>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-gray-50 px-6 py-4 flex gap-3 border-t border-gray-200">
                        <a href="{{ $mapUrl }}" target="_blank"
                            class="flex-1 bg-white border border-gray-200 text-red-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-900 font-medium py-2.5 rounded-lg text-sm transition-all shadow-sm flex items-center justify-center gap-2">
                            <x-filament::icon-button icon="heroicon-m-map-pin" x-on:click="open = !open"
                                :tooltip="trans('menu::menu-builder.items.expand')" color="red"
                                class="transition duration-200 ease-in-out" x-bind:class="{ 'rotate-90': open }"
                                size="sm" />
                            <button class="hover:underline font-semibold">Định vị Google Map</button>
                        </a>
                    </div>

                    @php
                    $hotlineRaw = $hotline ?? '';
                    $hotlineNumbers = array_filter(array_map('trim', explode('-', $hotlineRaw)));
                    $hotlineLabels = ['Liên hệ Zalo', 'Liên hệ phone'];
                    @endphp
                    <div class="bg-gray-50 px-6 pb-2 block sm:flex gap-3">
                        @foreach($hotlineNumbers as $idx => $number)
                        <a href="tel:{{ $number }}" style="background-color: #4e6b4c;"
                            class="flex-1 text-white m-1 font-medium py-2.5 rounded-lg text-sm transition-all shadow-sm flex items-center justify-center gap-2">
                            <x-filament::icon-button icon="heroicon-m-chat-bubble-left" x-on:click="open = !open"
                                :tooltip="trans('menu::menu-builder.items.expand')" color="white"
                                class="transition duration-200 ease-in-out" x-bind:class="{ 'rotate-90': open }"
                                size="xs" />
                            {{ $hotlineLabels[$idx] ?? 'Liên hệ'
                            }}<span class="text-white font-bold ml-2">{{ $number }}</span>
                        </a>
                        @endforeach
                    </div>
                    <div class="bg-gray-50 px-6 pb-4 block sm:flex gap-3">
                        <a href="/" style="background-color: #4e6b4c;"
                            class="flex-1 text-white font-medium py-2.5 rounded-lg text-sm transition-all shadow-sm flex items-center justify-center gap-2">
                            <x-filament::icon-button icon="heroicon-m-home" x-on:click="open = !open"
                                :tooltip="trans('menu::menu-builder.items.expand')" color="white"
                                class="transition duration-200 ease-in-out" x-bind:class="{ 'rotate-90': open }"
                                size="xs" />
                            <span class="text-white font-bold ml-2">Trở về trang chủ</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>

    @livewire('bladethemev1::footer')
    @livewire('bladethemev1::contact-link')
    @livewire('bladethemev1::notification')
@endsection
