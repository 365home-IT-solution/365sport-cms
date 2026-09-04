<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CorporateAccount;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Xuất "hoá đơn tổng hợp cuối kỳ" (PDF) cho 1 CorporateAccount — liệt kê mọi Order đã trừ vào hạn
// mức trong kỳ hiện tại (corporate_account_id = $account->id, tạo từ cycle_start_date tới hiện
// tại) + tổng giờ đã dùng. Dùng CHUNG cơ chế Dompdf đã có sẵn trong dự án (dompdf/dompdf, xem
// App\Services\PdfSigning\ContractPdfRenderer và Modules\Warehouse\App\Support\WarehousePdfRenderer
// — 2 nơi khác nhau nhưng cùng 1 cách gọi Dompdf, tránh viết thêm 1 wrapper thứ 3 khác gì mấy) —
// KHÔNG kéo thêm thư viện PDF mới (barryvdh/laravel-dompdf, mpdf...) dù chưa có sẵn trong
// composer.json, vì dompdf/dompdf gốc đã đủ dùng và đã được kiểm chứng chạy ổn với tiếng Việt có
// dấu (DejaVu Sans) ở 2 nơi trên.
class CorporateInvoiceRenderer
{
    // $orders: Order::corporate_account_id = $account->id, trong khoảng cycle_start_date -> hiện
    // tại (xem CorporateAccountResource action "Xuất hoá đơn" — nơi query + gọi hàm này).
    public static function download(CorporateAccount $account, Collection $orders): StreamedResponse
    {
        $totalHours = $orders->sum(fn ($order) => self::orderHours($order));
        // Giá trị QUY ĐỔI tham khảo — dùng full_amount (giá NIÊM YẾT của đơn nếu KHÔNG dùng hạn
        // mức, đã tính sẵn lúc tạo đơn ở BookingController::store()) để công ty đối chiếu "nếu trả
        // tiền mặt sẽ tốn bao nhiêu" — KHÔNG phải số tiền thật sự phải trả (hạn mức đã bao gồm
        // trong hợp đồng, xem ghi chú tại CorporateAccount).
        $totalReferenceValue = $orders->sum(fn ($order) => (int) ($order->full_amount ?? $order->amount ?? 0));

        $html = view('pdf.corporate-invoice', [
            'account'              => $account,
            'orders'               => $orders,
            'totalHours'           => $totalHours,
            'totalReferenceValue'  => $totalReferenceValue,
            'periodFrom'           => $account->cycle_start_date,
            'periodTo'             => now(),
        ])->render();

        return self::stream($html, $account);
    }

    public static function orderHours($order): float
    {
        $minutes = 0;

        foreach ($order->items as $item) {
            if (! $item->checkin_date || ! $item->checkout_date) {
                continue;
            }
            $minutes += $item->checkin_date->diffInMinutes($item->checkout_date);
        }

        return round($minutes / 60, 2);
    }

    private static function stream(string $html, CorporateAccount $account): StreamedResponse
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // hỗ trợ tiếng Việt có dấu

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml('<meta charset="utf-8">' . $html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $pdf = $dompdf->output();
        $fileName = 'hoa-don-' . \Illuminate\Support\Str::slug($account->company_name) . '-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(
            fn () => print ($pdf),
            $fileName,
            ['Content-Type' => 'application/pdf']
        );
    }
}
