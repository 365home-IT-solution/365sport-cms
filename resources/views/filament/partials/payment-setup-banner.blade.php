@php
    // Nhắc chủ đối tác chưa thiết lập thanh toán: sân của họ không nhận đặt online và chưa bật bán sân được (xem Partner::hasUsablePaymentMethod()).
    $user = auth()->user();
    $partner = ($user && $user->partner_id && ! $user->isSuperAdmin() && $user->isPartnerOwner()) ? \App\Models\Partner::query()->find($user->partner_id) : null;
    $show = $partner && ! $partner->hasUsablePaymentMethod() && ! request()->routeIs('filament.admin.pages.payment-setup');
    $url = null;
    if ($show) {
        try { $url = \App\Filament\Pages\PaymentSetup::getUrl(); } catch (\Throwable $e) { $show = false; }
    }
@endphp
@if ($show)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p><strong>Bạn chưa thiết lập thanh toán.</strong> Khách chưa đặt được sân online và bạn chưa bật bán sân được cho tới khi nhập tài khoản ngân hàng (hoặc khoá PayOS).</p>
        <a href="{{ $url }}" class="shrink-0 rounded-lg bg-amber-500 px-4 py-2 font-semibold text-white hover:bg-amber-600">Thiết lập ngay</a>
    </div>
@endif
