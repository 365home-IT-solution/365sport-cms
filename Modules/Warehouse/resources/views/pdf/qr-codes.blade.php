<!DOCTYPE html>
<html lang="vi">
<head>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        .card {
            border: 1px dashed #9ca3af;
            padding: 12px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        table.card-table { width: 100%; border-collapse: collapse; }
        table.card-table td { vertical-align: middle; border: none; padding: 0; }
        .qr-cell { width: 230px; text-align: center; }
        .qr-cell img { width: 220px; height: 220px; }
        .info-cell { padding-left: 16px; }
        .item-name { font-size: 16px; font-weight: bold; margin: 0 0 6px; }
        .item-sku { font-size: 13px; color: #374151; }
        .item-sku span { font-family: 'DejaVu Sans Mono', monospace; }
        .footer-note { margin-top: 16px; color: #9ca3af; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    @foreach ($items as $item)
        <div class="card">
            <table class="card-table">
                <tr>
                    <td class="qr-cell">
                        <img src="data:image/png;base64,{{ $qrImages[$item->id] }}" alt="QR {{ $item->sku }}">
                    </td>
                    <td class="info-cell">
                        <p class="item-name">{{ $item->name }}</p>
                        <p class="item-sku">Mã SKU: <span>{{ $item->sku }}</span></p>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <p class="footer-note">Cắt theo đường nét đứt và dán lên vật tư — 365sport CMS</p>
</body>
</html>
