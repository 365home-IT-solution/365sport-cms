{{-- ═══════════ CHAT NHÓM (trưởng nhóm + thành viên đã duyệt) ═══════════
     Realtime qua Socket.IO (websocket/server.js, kênh "fundchat:{id}") — CÙNG hạ tầng Node WS đã
     dùng cho slot/hold/chat hỗ trợ khách hàng (resources/js/ws-client.js), nhưng viết JS RIÊNG
     ngay tại đây (không thêm vào bundle chung) vì chỉ trang quỹ nhóm cần. Nhận tin nhắn mới → ép
     Livewire re-render ($wire.$refresh(), giống cách ws-client.js xử lý "roomAvailabilityChanged"
     — đơn giản, không cần tự vá DOM/tự format tên người gửi trùng logic PHP).
     Include từ fund-detail.blade.php (tab "Trò chuyện") — $fund/$messages/$authUserId đến từ
     FundDetail::render(), tự có sẵn trong scope include, không cần truyền tay. --}}
<div class="rounded-xl bg-white border border-gray-100 overflow-hidden"
     x-data="{}"
     x-init="
        if (!window.__WS_PUBLIC_URL) { return; }
        let wsOrigin = window.__WS_PUBLIC_URL, wsPath = '/socket.io/';
        try {
            const parsed = new URL(window.__WS_PUBLIC_URL);
            wsOrigin = parsed.origin;
            wsPath = (parsed.pathname.replace(/\/$/, '') || '') + '/socket.io/';
        } catch (e) {}

        const fundId = {{ (int) $fund->id }};
        let refreshTimer = null;
        const scheduleRefresh = () => {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(() => $wire.$refresh(), 150);
        };

        const connect = () => {
            const socket = window.io(wsOrigin, { path: wsPath, transports: ['websocket', 'polling'] });
            socket.on('connect', () => socket.emit('subscribe:fund-chat', { fund_id: fundId }));
            socket.on('fundchat.message', (payload) => {
                if (payload.fund_id === fundId) { scheduleRefresh(); }
            });
        };

        if (window.io) { connect(); return; }
        const s = document.createElement('script');
        s.src = window.__WS_PUBLIC_URL + '/socket.io/socket.io.js';
        s.onload = connect;
        document.head.appendChild(s);
     ">
    <div x-ref="chatBox" x-init="new MutationObserver(() => { $refs.chatBox.scrollTop = $refs.chatBox.scrollHeight; }).observe($refs.chatBox, { childList: true })"
         class="px-4 pt-4 space-y-2 overflow-y-auto" style="max-height: 400px;">
        @forelse ($messages as $msg)
            @php $isMine = $msg->customer_id === $authUserId; @endphp
            <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $msg->id }}">
                <div class="max-w-[75%] {{ $isMine ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-800' }} rounded-2xl px-3 py-2">
                    @if (! $isMine)
                        <p class="text-xs font-semibold text-emerald-700">{{ $msg->customer->fullname ?? $msg->customer->phone ?? 'Khách hàng' }}</p>
                    @endif
                    <p class="text-sm break-words">{{ $msg->body }}</p>
                    <p class="text-[10px] mt-0.5 {{ $isMine ? 'text-emerald-100' : 'text-gray-400' }} text-right">{{ $msg->created_at->format('H:i') }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 text-center py-6">Chưa có tin nhắn nào — bắt đầu trò chuyện với nhóm!</p>
        @endforelse
    </div>

    <form wire:submit.prevent="sendMessage" class="flex items-center gap-2 p-3 border-t border-gray-50">
        <input type="text" wire:model="messageBody" maxlength="1000" placeholder="Nhập tin nhắn..."
               class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400">
        <button type="submit" wire:loading.attr="disabled" wire:target="sendMessage"
            class="shrink-0 rounded-lg bg-emerald-600 text-white text-sm font-semibold px-4 py-2 hover:bg-emerald-700 disabled:opacity-50">
            Gửi
        </button>
    </form>
</div>
