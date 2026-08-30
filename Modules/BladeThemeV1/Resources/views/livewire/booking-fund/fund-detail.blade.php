{{-- $embedded (xem FundDetail.php) — khi nhúng trong account-page.blade.php, bỏ min-h-screen/bố
     cục full-bleed (chỉ hợp cho trang riêng /quy-nhom/{code}) — nội dung bên trong (thẻ trắng trên
     nền xám nhạt) vốn đã hợp cả 2 ngữ cảnh nên không cần re-theme màu gì thêm. --}}
<div class="{{ $embedded ? '' : 'min-h-screen bg-gray-50' }}"
     x-data="{}"
     x-init="
        const token = localStorage.getItem('auth_token');
        if (token) $wire.prefillFromAuth(token);
        window.addEventListener('auth-state-changed', () => {
            const t = localStorage.getItem('auth_token');
            $wire.prefillFromAuth(t || '');
        });
     ">
    <div class="{{ $embedded ? 'p-5' : 'mx-auto max-w-xl px-4 py-6 sm:py-8 pb-16' }}">

        @if (! $fund)
            <div class="rounded-xl bg-white border border-gray-100 p-8 text-center">
                <p class="text-gray-700 font-semibold">Không tìm thấy quỹ nhóm này</p>
                <p class="text-gray-400 text-sm mt-1">Kiểm tra lại mã mời — mã gồm 8 ký tự, phân biệt chữ hoa/thường không quan trọng.</p>
                <a href="{{ route('account.page') }}" class="inline-block mt-4 text-sm font-semibold text-emerald-600 hover:underline">Về trang tài khoản</a>
            </div>

        @elseif (! $isAuthUser)
            <div class="rounded-xl bg-white border border-gray-100 p-6 text-center">
                <p class="text-lg font-bold text-gray-900">{{ $fund->title }}</p>
                <p class="text-sm text-gray-500 mt-1">Mục tiêu: {{ number_format($fund->target_amount, 0, ',', '.') }}đ</p>
                <p class="text-sm text-gray-600 mt-4">Đăng nhập để xin tham gia quỹ này.</p>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-auth-modal'))"
                    class="inline-flex items-center gap-2 mt-4 px-6 py-3 rounded-xl text-sm font-semibold bg-[#0B6B3A] text-white hover:opacity-90">
                    Đăng nhập ngay
                </button>
            </div>

        @else
            {{-- ── Header + tiến độ góp quỹ gộp làm 1 thẻ — trước đây tiêu đề/khung giờ nằm trần
                 trên nền trắng (không viền/không nền), trông rời rạc và sơ sài so với các thẻ khác
                 trong trang (bug thẩm mỹ người dùng phản ánh). Gộp lại thành 1 khối card duy nhất
                 với dải màu accent bên trái để phân biệt rõ đây là "tiêu đề" của cả trang. ── --}}
            <div class="rounded-2xl bg-white border border-gray-100 overflow-hidden mb-4">
                <div class="p-4 border-l-4 border-emerald-500">
                    <div class="flex items-start justify-between gap-2">
                        <h1 class="text-lg font-bold text-gray-900 leading-snug">{{ $fund->title }}</h1>
                        @if ($fund->status === 'closed')
                            <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 border border-gray-200">Đã đóng quỹ</span>
                        @else
                            <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200">Đang gom quỹ</span>
                        @endif
                    </div>

                    @if ($fund->slotLabel())
                        <p class="text-sm font-semibold text-emerald-700 mt-1.5 flex items-center gap-1.5">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $fund->slotLabel() }}
                            @if ($fund->category)
                                <span class="text-gray-400 font-normal">· {{ $fund->category->name }}</span>
                            @endif
                        </p>
                    @endif
                    @if ($fund->note)
                        <p class="text-sm text-gray-500 mt-1.5">{{ $fund->note }}</p>
                    @endif
                </div>

                {{-- ── Tiến độ góp quỹ (mọi vai trò đều thấy) ── --}}
                <div class="px-4 pb-4 pt-3 border-t border-gray-50">
                    <div class="flex items-end justify-between mb-2">
                        <span class="text-xl font-bold text-gray-900">{{ number_format($fund->collectedAmount(), 0, ',', '.') }}đ</span>
                        <span class="text-sm text-gray-400 pb-0.5">/ {{ number_format($fund->target_amount, 0, ',', '.') }}đ</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full bg-emerald-500 transition-all" style="width: {{ $fund->progressPercent() }}%;"></div>
                    </div>
                    @if ($isLeader)
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p class="text-xs text-gray-400">Mã mời: <span class="font-mono font-semibold text-gray-700 select-all bg-gray-50 border border-gray-200 rounded px-1.5 py-0.5">{{ $fund->invite_code }}</span></p>
                            @if ($fund->status === 'collecting')
                                <button type="button" wire:click="closeFund" wire:confirm="Đóng quỹ? Sẽ không nhận thêm thành viên/khoản góp mới nữa."
                                    class="shrink-0 text-xs font-semibold text-red-500 hover:text-red-600 hover:underline">Đóng quỹ</button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            @if ($isLeader || ($membership && $membership->status === 'approved'))
                {{-- ═══════════ TAB: Thông tin / Chuyển khoản / Trò chuyện ═══════════
                     Trước đây dồn hết mọi khối (thành viên, khoản góp, QR, chat) vào 1 luồng dọc
                     dài — tách tab cho dễ quản lý (yêu cầu người dùng). Alpine x-data cục bộ CHỈ
                     bọc phần tab (không đụng x-data gốc của component ở div ngoài cùng) — tab mặc
                     định 'info' cho trưởng nhóm (cần duyệt thành viên ngay), 'transfer' cho thành
                     viên (cần thấy QR ngay). --}}
                <div x-data="{ tab: '{{ $isLeader ? 'info' : 'transfer' }}' }">
                    <div class="flex rounded-xl bg-white border border-gray-100 p-1 mb-4 gap-1">
                        @if ($isLeader)
                            <button type="button" @click="tab = 'info'"
                                :class="tab === 'info' ? 'bg-emerald-600 text-white' : 'text-gray-500 hover:bg-gray-50'"
                                class="flex-1 rounded-lg text-sm font-semibold py-2 transition-colors">Thông tin</button>
                        @endif
                        <button type="button" @click="tab = 'transfer'"
                            :class="tab === 'transfer' ? 'bg-emerald-600 text-white' : 'text-gray-500 hover:bg-gray-50'"
                            class="flex-1 rounded-lg text-sm font-semibold py-2 transition-colors">Chuyển khoản</button>
                        <button type="button" @click="tab = 'chat'"
                            :class="tab === 'chat' ? 'bg-emerald-600 text-white' : 'text-gray-500 hover:bg-gray-50'"
                            class="flex-1 rounded-lg text-sm font-semibold py-2 transition-colors">Trò chuyện</button>
                    </div>

                    @if ($isLeader)
                        {{-- ── Tab Thông tin: yêu cầu tham gia + danh sách thành viên ── --}}
                        <div x-show="tab === 'info'" x-cloak>
                            @php $pendingMembers = $members->where('status', 'pending'); @endphp
                            @if ($pendingMembers->isNotEmpty())
                                <div class="rounded-xl bg-white border border-gray-100 p-4 mb-4">
                                    <p class="text-sm font-bold text-gray-900 mb-3">Yêu cầu tham gia ({{ $pendingMembers->count() }})</p>
                                    <div class="space-y-2">
                                        @foreach ($pendingMembers as $m)
                                            <div class="flex items-center justify-between gap-2" wire:key="member-{{ $m->id }}">
                                                <span class="text-sm text-gray-700">{{ $m->customer->fullname ?? $m->customer->phone ?? 'Khách hàng' }}</span>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button type="button" wire:click="decideMember({{ $m->id }}, 'approved')"
                                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100">Duyệt</button>
                                                    <button type="button" wire:click="decideMember({{ $m->id }}, 'rejected')"
                                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gray-50 text-gray-500 border border-gray-200 hover:bg-gray-100">Từ chối</button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @php $approvedMembers = $members->where('status', 'approved'); @endphp
                            <div class="rounded-xl bg-white border border-gray-100 p-4">
                                <p class="text-sm font-bold text-gray-900 mb-3">Thành viên ({{ $approvedMembers->count() }})</p>
                                @forelse ($approvedMembers as $m)
                                    <p class="text-sm text-gray-600 py-1" wire:key="approved-{{ $m->id }}">{{ $m->customer->fullname ?? $m->customer->phone ?? 'Khách hàng' }}</p>
                                @empty
                                    <p class="text-sm text-gray-400">Chưa có thành viên nào.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- ── Tab Chuyển khoản ── --}}
                    <div x-show="tab === 'transfer'" x-cloak>
                        @if ($isLeader)
                            {{-- Trưởng nhóm: xét duyệt từng khoản góp của mọi thành viên --}}
                            <div class="rounded-xl bg-white border border-gray-100 p-4">
                                <p class="text-sm font-bold text-gray-900 mb-3">Các khoản góp</p>
                                @forelse ($contributions as $c)
                                    <div class="border-b border-gray-50 last:border-0 py-3" wire:key="contrib-{{ $c->id }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-gray-900">{{ $c->customer->fullname ?? $c->customer->phone ?? 'Khách hàng' }}</p>
                                                <p class="text-sm text-emerald-600 font-semibold">{{ number_format($c->amount, 0, ',', '.') }}đ</p>
                                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($c->receipt_photo) }}" target="_blank" class="text-xs text-blue-600 hover:underline">Xem ảnh biên lai</a>
                                            </div>
                                            @if ($c->status === 'pending')
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <button type="button" wire:click="decideContribution({{ $c->id }}, 'confirmed')"
                                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100">Xác nhận</button>
                                                    <button type="button" wire:click="decideContribution({{ $c->id }}, 'rejected')"
                                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-gray-50 text-gray-500 border border-gray-200 hover:bg-gray-100">Từ chối</button>
                                                </div>
                                            @else
                                                <span class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full {{ $c->status === 'confirmed' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                                                    {{ $c->status === 'confirmed' ? 'Đã xác nhận' : 'Đã từ chối' }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-400">Chưa có khoản góp nào.</p>
                                @endforelse
                            </div>
                        @else
                            {{-- Thành viên: QR + form gửi biên lai + lịch sử góp của chính mình --}}
                            @if ($fund->status === 'collecting')
                                <div class="rounded-xl bg-white border border-gray-100 p-4 mb-4 text-center">
                                    <p class="text-sm font-bold text-gray-900 mb-3">Quét mã chuyển khoản</p>
                                    <img src="{{ $fund->vietQrImageUrl(max(0, $fund->target_amount - $fund->collectedAmount()), $fund->invite_code) }}"
                                         alt="VietQR" class="mx-auto w-56 h-56 object-contain rounded-lg border border-gray-100">
                                    <p class="text-xs text-gray-500 mt-2">{{ $fund->bank_account_holder }} — {{ $fund->bank_account_number }}</p>
                                </div>

                                <div class="rounded-xl bg-white border border-gray-100 p-4 mb-4">
                                    <p class="text-sm font-bold text-gray-900 mb-3">Đã chuyển? Gửi biên lai</p>
                                    <form wire:submit.prevent="submitContribution" class="space-y-3">
                                        <input type="number" wire:model="contributeAmount" placeholder="Số tiền đã chuyển"
                                               class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
                                        @error('contributeAmount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                        <input type="file" wire:model="receiptPhoto" accept="image/*"
                                               class="w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm">
                                        <div wire:loading wire:target="receiptPhoto" class="text-xs text-gray-400">Đang tải ảnh lên...</div>
                                        @if ($receiptPhoto)
                                            <img src="{{ $receiptPhoto->temporaryUrl() }}" class="h-32 rounded-lg border border-gray-100 object-cover">
                                        @endif
                                        @error('receiptPhoto') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                        <button type="submit" wire:loading.attr="disabled" wire:target="submitContribution"
                                            class="w-full rounded-lg bg-emerald-600 text-white text-sm font-semibold py-2.5 hover:bg-emerald-700 disabled:opacity-50">
                                            <span wire:loading.remove wire:target="submitContribution">Gửi biên lai</span>
                                            <span wire:loading wire:target="submitContribution">Đang gửi...</span>
                                        </button>
                                    </form>
                                </div>
                            @endif

                            <div class="rounded-xl bg-white border border-gray-100 p-4">
                                <p class="text-sm font-bold text-gray-900 mb-3">Các lần góp của bạn</p>
                                @forelse ($contributions as $c)
                                    <div class="flex items-center justify-between border-b border-gray-50 last:border-0 py-2.5" wire:key="mycontrib-{{ $c->id }}">
                                        <span class="text-sm text-gray-700">{{ number_format($c->amount, 0, ',', '.') }}đ</span>
                                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full
                                            {{ $c->status === 'confirmed' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : ($c->status === 'rejected' ? 'bg-red-50 text-red-500 border border-red-200' : 'bg-amber-50 text-amber-600 border border-amber-200') }}">
                                            {{ $c->status === 'confirmed' ? 'Đã xác nhận' : ($c->status === 'rejected' ? 'Bị từ chối' : 'Chờ xác nhận') }}
                                        </span>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-400">Bạn chưa góp lần nào.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>

                    {{-- ── Tab Trò chuyện ── --}}
                    <div x-show="tab === 'chat'" x-cloak>
                        @include('bladethemev1::livewire.booking-fund._chat-panel')
                    </div>
                </div>

            @elseif ($membership && $membership->status === 'pending')
                {{-- ═══════════ ĐANG CHỜ DUYỆT ═══════════ --}}
                <div class="rounded-xl bg-white border border-gray-100 p-6 text-center">
                    <p class="text-sm text-gray-700">Yêu cầu tham gia của bạn đang chờ trưởng nhóm duyệt.</p>
                </div>

            @elseif ($membership && $membership->status === 'rejected')
                {{-- ═══════════ BỊ TỪ CHỐI ═══════════ --}}
                <div class="rounded-xl bg-white border border-gray-100 p-6 text-center">
                    <p class="text-sm text-gray-500">Yêu cầu tham gia của bạn đã bị từ chối.</p>
                </div>

            @else
                {{-- ═══════════ KHÁCH LẠ — CHƯA XIN THAM GIA ═══════════ --}}
                <div class="rounded-xl bg-white border border-gray-100 p-6 text-center">
                    <p class="text-sm text-gray-600 mb-4">Bạn chưa tham gia quỹ này.</p>
                    @if ($fund->status === 'collecting')
                        <button type="button" wire:click="requestJoin" wire:loading.attr="disabled" wire:target="requestJoin"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold bg-[#0B6B3A] text-white hover:opacity-90 disabled:opacity-50">
                            Xin tham gia
                        </button>
                    @else
                        <p class="text-xs text-gray-400">Quỹ này đã đóng, không nhận thêm thành viên.</p>
                    @endif
                </div>
            @endif
        @endif
    </div>
</div>
