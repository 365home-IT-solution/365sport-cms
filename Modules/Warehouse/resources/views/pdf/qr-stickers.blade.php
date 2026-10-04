<!DOCTYPE html>
<html lang="vi">
<head>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        .sheet { page-break-before: always; }
        .sheet:first-child { page-break-before: avoid; }
        table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.grid td {
            width: 7.14%; /* 14 cột */
            text-align: center;
            vertical-align: top;
            padding: 2px;
        }
        .sticker img { width: 34px; height: 34px; }
        .sticker .sku {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 5px;
            word-break: break-all;
            margin-top: 1px;
        }
        .sheet-title { font-size: 11px; font-weight: bold; margin: 0 0 6px; }
    </style>
</head>
<body>
    @php $cols = 14; @endphp
    @foreach ($items as $item)
        <div class="sheet">
            <p class="sheet-title">{{ $item->name }} — {{ $item->sku }} ({{ $copies }} tem)</p>
            <table class="grid">
                @for ($row = 0; $row < ceil($copies / $cols); $row++)
                    <tr>
                        @for ($col = 0; $col < $cols; $col++)
                            @php $n = $row * $cols + $col; @endphp
                            <td>
                                @if ($n < $copies)
                                    <div class="sticker">
                                        <img src="data:image/png;base64,{{ $qrImages[$item->id] }}" alt="QR">
                                        <div class="sku">{{ $item->sku }}</div>
                                    </div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endfor
            </table>
        </div>
    @endforeach
</body>
</html>
