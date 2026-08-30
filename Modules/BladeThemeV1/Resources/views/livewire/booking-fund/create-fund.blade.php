{{-- $embedded (xem CreateFund.php) — nhúng ngay trong khung "Nhóm của tôi" ở account-page.blade.php
     thay vì trang riêng /quy-nhom/tao-moi. KHÔNG re-theme lại từng màu chữ trắng-trên-nền-xanh của
     form (rủi ro sót chỗ) — thay vào đó BỌC form trong 1 khối thẻ xanh bo góc (thay vì full-bleed
     min-h-screen) để mọi màu chữ trắng bên trong vẫn đúng như thiết kế gốc, chỉ khác là nằm gọn
     trong khung panel bên phải của trang tài khoản thay vì chiếm nguyên màn hình. --}}
<div @if(! $embedded) class="min-h-screen" style="background: linear-gradient(160deg, #0B6B3A 0%, #128A4A 45%, #1AA859 100%);" @endif
     x-data="{}"
     x-init="
        const token = localStorage.getItem('auth_token');
        if (token) $wire.prefillFromAuth(token);
        window.addEventListener('auth-state-changed', () => {
            const t = localStorage.getItem('auth_token');
            $wire.prefillFromAuth(t || '');
        });
     ">
    <div class="{{ $embedded ? 'rounded-2xl p-4' : 'mx-auto max-w-xl px-4 py-6 sm:py-8 pb-16' }}"
         @if ($embedded) style="background: linear-gradient(160deg, #0B6B3A 0%, #128A4A 45%, #1AA859 100%);" @endif>
        @unless ($embedded)
        <div class="mb-4 flex items-center gap-3">
            <a href="{{ route('account.page') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h1 class="text-base font-extrabold uppercase tracking-wide text-white sm:text-lg">Tạo quỹ nhóm góp tiền</h1>
        </div>
        @endunless

        @if (! $isAuthUser)
            <div class="rounded-xl bg-white/95 p-6 text-center">
                <p class="text-sm text-gray-600 mb-4">Bạn cần đăng nhập để tạo quỹ nhóm.</p>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-auth-modal'))"
                    class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold bg-[#0B6B3A] text-white hover:opacity-90">
                    Đăng nhập ngay
                </button>
            </div>
        @else
            <div class="rounded-xl bg-white/10 p-4 mb-4 text-sm text-white/90">
                Tiền sẽ được thành viên chuyển <strong>thẳng vào tài khoản ngân hàng bạn nhập bên dưới</strong> — hệ thống chỉ theo dõi tiến độ góp quỹ, không giữ tiền.
            </div>

            <form wire:submit.prevent="create" class="space-y-3">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-white/80">Tên quỹ</label>
                    <input type="text" wire:model="title" placeholder="VD: Đá bóng tối thứ 6"
                           class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                    @error('title') <p class="text-xs text-red-200 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-white/80">Ghi chú (không bắt buộc)</label>
                    <textarea wire:model="note" rows="2" placeholder="VD: Sân ABC, khung giờ 19h-21h"
                              class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white"></textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-white/80">Số tiền mục tiêu</label>
                    <input type="number" wire:model="targetAmount" placeholder="600000"
                           class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                    @error('targetAmount') <p class="text-xs text-red-200 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Chọn chi nhánh/sân/khung giờ dự định — CHỈ để minh bạch cho thành viên biết
                     đang góp cho sân/giờ nào, KHÔNG giữ chỗ thật (xem BookingFund::slotLabel()). --}}
                <div class="rounded-xl bg-white/10 p-3 space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-white/80">Sân dự định đặt (không bắt buộc)</p>

                    <div>
                        <select wire:model.live="categoryId"
                                class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                            <option value="">— Chọn chi nhánh —</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($categoryId)
                        <div>
                            <select wire:model="productId"
                                    class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                                <option value="">— Chọn sân —</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <input type="date" wire:model="slotDate"
                                   class="rounded-xl border-0 px-3 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                            <select wire:model="slotStartTime"
                                    class="rounded-xl border-0 px-2 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                                <option value="">Giờ bắt đầu</option>
                                @foreach (range(0, 47) as $i)
                                    @php $t = sprintf('%02d:%02d', intdiv($i, 2), ($i % 2) * 30); @endphp
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                            <select wire:model="slotEndTime"
                                    class="rounded-xl border-0 px-2 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                                <option value="">Giờ kết thúc</option>
                                @foreach (range(0, 47) as $i)
                                    @php $t = sprintf('%02d:%02d', intdiv($i, 2), ($i % 2) * 30); @endphp
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl bg-white/10 p-3 space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-white/80">Tài khoản ngân hàng nhận tiền (của bạn)</p>

                    <div>
                        <select wire:model="bankBin"
                                class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                            <option value="">— Chọn ngân hàng —</option>
                            @foreach ($banks as $bin => $name)
                                <option value="{{ $bin }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('bankBin') <p class="text-xs text-red-200 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <input type="text" wire:model="bankAccountNumber" placeholder="Số tài khoản"
                               class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                        @error('bankAccountNumber') <p class="text-xs text-red-200 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <input type="text" wire:model="bankAccountHolder" placeholder="Tên chủ tài khoản (không dấu)"
                               class="w-full rounded-xl border-0 px-4 py-3 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-white">
                        @error('bankAccountHolder') <p class="text-xs text-red-200 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="create"
                    class="w-full rounded-xl bg-[#F5A623] py-3.5 text-sm font-extrabold uppercase tracking-wide text-gray-900 hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-50">
                    <span wire:loading.remove wire:target="create">Tạo quỹ</span>
                    <span wire:loading wire:target="create">Đang tạo...</span>
                </button>
            </form>
        @endif
    </div>
</div>
