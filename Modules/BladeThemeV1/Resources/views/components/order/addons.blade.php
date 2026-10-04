{{--
    Các khoản đi kèm tiền sân của 1 đơn — Dịch vụ / Thuê vật tư / Huấn luyện viên, mỗi nhóm 1 khối
    RIÊNG (không gộp chung vào "dịch vụ"). Dữ liệu từ Order::addonBreakdown(), dùng chung cho mọi
    trang chi tiết đơn phía khách để không trang nào hiển thị lệch/thiếu.
    Tham số: $order (Modules\Payment\Entities\Order).
--}}
@php
    $addons = $order->addonBreakdown();
    $fmtAddon = fn ($v) => number_format((float) $v, 0, ',', '.') . 'đ';
    $addonGroups = [
        ['title' => 'Dịch vụ thêm', 'rows' => $addons['services'], 'total' => $addons['services_total']],
        ['title' => 'Thuê vật tư', 'rows' => $addons['equipment'], 'total' => $addons['equipment_total']],
    ];
@endphp

@if ($addons['services']->isNotEmpty() || $addons['equipment']->isNotEmpty() || $addons['coaches']->isNotEmpty())
<div class="mt-6 space-y-4">
    @foreach ($addonGroups as $group)
        @if ($group['rows']->isNotEmpty())
        <div class="rounded-xl border border-gray-200 p-4">
            <p class="mb-2 text-sm font-semibold text-gray-900">{{ $group['title'] }}</p>
            <div class="space-y-1.5 text-sm">
                @foreach ($group['rows'] as $row)
                    <div class="flex justify-between gap-3">
                        <span class="min-w-0 truncate text-gray-600">{{ $row->service_name }} <span class="text-gray-400">× {{ $row->quantity }}</span></span>
                        <span class="shrink-0 font-medium text-gray-900">{{ $fmtAddon($row->subtotal) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between border-t border-dashed border-gray-200 pt-2 text-sm">
                <span class="text-gray-500">Tổng {{ mb_strtolower($group['title']) }}</span>
                <span class="font-semibold text-gray-900">{{ $fmtAddon($group['total']) }}</span>
            </div>
        </div>
        @endif
    @endforeach

    @if ($addons['coaches']->isNotEmpty())
    <div class="rounded-xl border border-gray-200 p-4">
        <p class="mb-2 text-sm font-semibold text-gray-900">Huấn luyện viên</p>
        <div class="space-y-1.5 text-sm">
            @foreach ($addons['coaches'] as $coachRow)
                <div class="flex justify-between gap-3">
                    <span class="min-w-0 text-gray-600">
                        {{ $coachRow->coach_name }}
                        <span class="text-gray-400">· {{ $coachRow->starts_at?->format('H:i') }}–{{ $coachRow->ends_at?->format('H:i') }} {{ $coachRow->starts_at?->format('d/m') }}</span>
                    </span>
                    <span class="shrink-0 font-medium text-gray-900">{{ $fmtAddon($coachRow->subtotal) }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between border-t border-dashed border-gray-200 pt-2 text-sm">
            <span class="text-gray-500">Tổng tiền HLV</span>
            <span class="font-semibold text-gray-900">{{ $fmtAddon($addons['coaches_total']) }}</span>
        </div>
    </div>
    @endif
</div>
@endif
