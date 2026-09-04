<!DOCTYPE html>
<html lang="vi">
<head>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 18px; text-align: center; margin: 0 0 4px; text-transform: uppercase; }
        .subtitle { text-align: center; color: #6b7280; margin: 0 0 20px; }
        .meta-table { width: 100%; margin-bottom: 16px; }
        .meta-table td { padding: 2px 0; vertical-align: top; }
        .meta-label { color: #6b7280; width: 130px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th, table.items td { border: 1px solid #d1d5db; padding: 6px 8px; font-size: 11px; }
        table.items th { background: #f3f4f6; text-align: left; }
        table.items td.num { text-align: right; }
        table.items tfoot td { font-weight: bold; background: #f9fafb; }
        .totals { width: 100%; margin-top: 16px; }
        .totals td { padding: 3px 0; }
        .totals .label { color: #6b7280; }
        .totals .value { text-align: right; font-weight: bold; }
        .footer-note { margin-top: 24px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <h1>Hoá đơn tổng hợp — tài khoản doanh nghiệp</h1>
    <p class="subtitle">{{ $account->partner?->name ?? '—' }}</p>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Công ty:</td>
            <td><strong>{{ $account->company_name }}</strong></td>
            <td class="meta-label">Mã số thuế:</td>
            <td>{{ $account->tax_code ?? '—' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Người liên hệ:</td>
            <td>{{ $account->contact_name }} — {{ $account->contact_phone }}</td>
            <td class="meta-label">Email:</td>
            <td>{{ $account->contact_email ?? '—' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Địa chỉ:</td>
            <td colspan="3">{{ $account->billing_address ?? '—' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Kỳ thanh toán:</td>
            <td colspan="3">{{ $periodFrom->format('d/m/Y') }} — {{ $periodTo->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Hạn mức/tháng:</td>
            <td colspan="3">{{ rtrim(rtrim(number_format((float) $account->monthly_hour_quota, 2, ',', '.'), '0'), ',') }} giờ</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Mã đơn</th>
                <th>Nhân viên đặt</th>
                <th>Sân</th>
                <th style="width: 130px;">Thời gian</th>
                <th style="width: 60px;" class="num">Giờ</th>
                <th style="width: 90px;" class="num">Giá trị tham khảo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $index => $order)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $order->order_code }}</td>
                    <td>{{ $order->customer?->fullname ?? $order->buyer_name }}</td>
                    <td>{{ $order->items->pluck('product.name')->filter()->unique()->implode(', ') ?: '—' }}</td>
                    <td>
                        @foreach ($order->items as $item)
                            @if ($item->checkin_date && $item->checkout_date)
                                {{ $item->checkin_date->format('d/m H:i') }} - {{ $item->checkout_date->format('d/m H:i') }}<br>
                            @endif
                        @endforeach
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format(\App\Services\CorporateInvoiceRenderer::orderHours($order), 2, ',', '.'), '0'), ',') }}</td>
                    <td class="num">{{ number_format((float) ($order->full_amount ?? $order->amount ?? 0), 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; color: #9ca3af;">Không có đơn nào trong kỳ này.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align: right;">Tổng cộng</td>
                <td class="num">{{ rtrim(rtrim(number_format($totalHours, 2, ',', '.'), '0'), ',') }}</td>
                <td class="num">{{ number_format($totalReferenceValue, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Tổng số giờ đã dùng trong kỳ:</td>
            <td class="value">{{ rtrim(rtrim(number_format($totalHours, 2, ',', '.'), '0'), ',') }} giờ / {{ rtrim(rtrim(number_format((float) $account->monthly_hour_quota, 2, ',', '.'), '0'), ',') }} giờ</td>
        </tr>
        <tr>
            <td class="label">Giá trị tham khảo (nếu thanh toán lẻ, không qua hạn mức):</td>
            <td class="value">{{ number_format($totalReferenceValue, 0, ',', '.') }} đ</td>
        </tr>
    </table>

    <p class="footer-note">
        Giá trị tham khảo tính theo giá niêm yết từng đơn — KHÔNG phải số tiền công ty phải thanh toán thêm
        (đã bao gồm trong hợp đồng hạn mức). Xuất lúc {{ now()->format('d/m/Y H:i') }} — 365sport CMS.
    </p>
</body>
</html>
