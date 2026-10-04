{{--
    Nhãn "đang đăng nhập với tư cách" cạnh menu tài khoản: đối tác + vai trò (+ tình trạng gói dịch vụ). Giúp biết ngay tài khoản
    đang xem dữ liệu của đối tác nào, quyền gì. Ẩn ở màn hình nhỏ (đủ chỗ cho nút chi nhánh/menu tài khoản).
--}}
@php
    $user = auth()->user();
    $role = $user?->isSuperAdmin() ? 'Super Admin' : ($user?->hasRole('partner') ? 'Chủ đối tác' : 'Nhân viên');
    $partner = $user?->partner_id ? \App\Models\Partner::withTrashed()->find($user->partner_id) : null;
    $sub = $partner && ! $user->isSuperAdmin() ? \App\Support\SubscriptionGate::stateFor($user)['sub'] : null;
    $state = $sub?->state();
    $days = $sub?->daysLeft();
    $color = match ($state) { 'active' => 'text-success-600', 'trial' => 'text-info-600', 'expired', 'cancelled' => 'text-danger-600', default => 'text-gray-500' };
@endphp

@if ($user)
    <div class="hidden xl:flex flex-col items-end leading-tight pr-2 text-right max-w-[220px]">
        <span class="text-xs font-semibold text-gray-950 dark:text-white truncate">{{ $partner?->name ?? config('app.name') }}</span>
        <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
            {{ $role }}@if ($sub)
                · <span class="{{ $color }}">{{ \App\Models\PartnerSubscription::STATES[$state] }}@if ($days !== null && $days >= 0 && in_array($state, ['trial', 'active'], true)) · còn {{ $days }} ngày @endif</span>
            @endif
        </span>
    </div>
@endif
