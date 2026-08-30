<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BankTransferConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Payment\Entities\Order;

// Nhận webhook "biến động số dư" từ SePay (1 tài khoản SePay DUY NHẤT quản lý nhiều ngân hàng của
// nhiều đối tác, theo lựa chọn "Chỉ cần chia sẻ biến động số dư" ở SePay — KHÔNG dùng gói "Cổng
// thanh toán" của SePay, vì hệ thống đã tự sinh QR VietQR + tự quản lý luồng đặt sân riêng).
//
// ⚠️ CẢNH BÁO QUAN TRỌNG: tên field trong $payload bên dưới (accountNumber/content/transferAmount/
// transferType...) viết theo cấu trúc payload webhook SePay đã công bố CÔNG KHAI tại thời điểm
// viết code này — CHƯA được kiểm chứng bằng 1 request webhook THẬT từ tài khoản SePay của bạn.
// Việc ĐẦU TIÊN cần làm sau khi có tài khoản SePay: cấu hình Webhook URL trỏ về route này, bấm nút
// "Gửi thử" (test webhook) trong dashboard SePay, xem log tại storage/logs/laravel.log (dòng
// "SePay webhook payload" bên dưới) để xác nhận ĐÚNG tên field — sửa lại nếu SePay đặt tên khác.
class SePayWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('SePay webhook payload', $payload);

        if (! $this->isAuthentic($request)) {
            Log::warning('SePay webhook: xác thực Authorization header thất bại');

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Chỉ xử lý giao dịch TIỀN VÀO (transferType='in') — bỏ qua tiền ra/giao dịch khác, SePay
        // có thể báo cả 2 chiều tuỳ cấu hình tài khoản.
        if (($payload['transferType'] ?? 'in') !== 'in') {
            return response()->json(['success' => true, 'message' => 'Ignored (not incoming transfer)']);
        }

        $content        = (string) ($payload['content'] ?? $payload['description'] ?? '');
        $accountNumber  = (string) ($payload['accountNumber'] ?? '');
        $transferAmount = (int) ($payload['transferAmount'] ?? 0);

        $orderCode = $this->extractOrderCode($content);

        if (! $orderCode) {
            Log::info('SePay webhook: không tìm thấy mã đơn hàng trong nội dung CK', ['content' => $content]);

            return response()->json(['success' => true, 'message' => 'No order code found in content']);
        }

        $order = Order::with(['items', 'partner'])
            ->where('order_code', $orderCode)
            ->where('payment_method', 'bank_transfer')
            ->whereNotIn('status', ['paid', 'refunded'])
            ->first();

        if (! $order) {
            Log::info('SePay webhook: không khớp đơn hàng nào cần xử lý', ['order_code' => $orderCode]);

            return response()->json(['success' => true, 'message' => 'No matching pending order']);
        }

        // Chỉ đối tác admin đã CHỌN "Chuyển khoản — Tự động xác nhận (SePay)" ở
        // "Phương thức thanh toán áp dụng" VÀ đang ĐÚNG GÓI có tính năng "Xác nhận chuyển khoản tự
        // động (SePay)" (Partner::bankTransferMode(), gộp cả 2 điều kiện) mới được tự động xác
        // nhận qua webhook — đối tác khác vẫn rơi về luồng upload ảnh + admin duyệt tay sẵn có
        // (KHÔNG bị chặn đặt sân, chỉ không có phần tự động). Giới hạn theo gói là CHỦ ĐỘNG (không
        // phải lỗi) — 1 tài khoản SePay nền tảng chỉ có hạn mức giao dịch/tháng nhất định, không
        // thể mở tự động cho toàn bộ đối tác cùng lúc.
        if (! $order->partner || $order->partner->bankTransferMode() !== 'sepay') {
            Log::info('SePay webhook: đối tác chưa bật SePay tự động (chưa chọn ở admin hoặc chưa có tính năng theo gói), bỏ qua', [
                'order_code' => $orderCode,
                'partner_id' => $order->partner_id,
            ]);

            return response()->json(['success' => true, 'message' => 'Partner not on auto-confirm plan, skipped']);
        }

        // Đối chiếu AN TOÀN — số tài khoản nhận tiền phải ĐÚNG số tài khoản của đối tác sở hữu đơn
        // này (tránh 1 giao dịch trùng số/trùng nội dung ngẫu nhiên vào TÀI KHOẢN CỦA ĐỐI TÁC
        // KHÁC lại tự động xác nhận nhầm đơn). Không khớp → KHÔNG tự xác nhận, chỉ log lại để admin
        // tự kiểm tra thủ công (rơi về luồng duyệt ảnh biên lai sẵn có).
        if ($order->partner->bank_account_number !== $accountNumber) {
            Log::warning('SePay webhook: số tài khoản nhận tiền không khớp đối tác của đơn', [
                'order_code'        => $orderCode,
                'expected_account'  => $order->partner?->bank_account_number,
                'webhook_account'   => $accountNumber,
            ]);

            return response()->json(['success' => true, 'message' => 'Account number mismatch, skipped']);
        }

        // Đối chiếu số tiền — phải ĐÚNG số tiền cần thu ngay của đơn (cọc hoặc đủ), không tự xác
        // nhận nếu khách chuyển thiếu/thừa (thiếu rõ ràng không đủ; thừa có thể là gộp nhiều đơn,
        // cần admin tự kiểm tra tay để tránh xác nhận sai đơn).
        $expectedAmount = $order->status === 'deposit' ? $order->depositDueAmount() : (int) $order->full_amount;

        if ($transferAmount !== $expectedAmount) {
            Log::warning('SePay webhook: số tiền không khớp', [
                'order_code' => $orderCode,
                'expected'   => $expectedAmount,
                'received'   => $transferAmount,
            ]);

            return response()->json(['success' => true, 'message' => 'Amount mismatch, skipped']);
        }

        try {
            app(BankTransferConfirmationService::class)->confirm($order, null);
        } catch (\Throwable $e) {
            // Lỗi khi xác nhận (vd cấp mã truy cập thất bại) KHÔNG được để lộ trang debug HTML ra
            // ngoài cho SePay — trả JSON gọn + mã lỗi 500 để SePay tự retry theo cơ chế của họ,
            // đồng thời log đủ để admin biết cần xử lý tay (đơn vẫn ở nguyên trạng thái cũ, CHƯA bị
            // đánh dấu paid nếu confirm() ném lỗi trước khi update xong).
            Log::error('SePay webhook: lỗi khi xác nhận đơn', [
                'order_code' => $orderCode,
                'error'      => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Confirm failed, will retry'], 500);
        }

        Log::info('SePay webhook: đã tự động xác nhận đơn', ['order_code' => $orderCode]);

        return response()->json(['success' => true, 'message' => 'Order confirmed']);
    }

    private function isAuthentic(Request $request): bool
    {
        $expected = config('sepay.api_key');

        if (! $expected) {
            // Chưa cấu hình SEPAY_API_KEY trong .env — KHÔNG cho qua mặc định (an toàn hơn là tiện
            // lợi), tránh request giả mạo tự xác nhận đơn khi quên cấu hình.
            return false;
        }

        $header = (string) $request->header('Authorization', '');

        return hash_equals('Apikey ' . $expected, $header);
    }

    // Tìm mã đơn hàng (order_code, xem Order::booted() — chuỗi SỐ) trong nội dung chuyển khoản.
    // Nội dung CK thật thường có thêm tiền tố/hậu tố do NGÂN HÀNG tự chèn (vd "NAPAS247 CT tu ...
    // 123456789 ... "), nên tìm CỤM SỐ DÀI NHẤT trong content khớp với 1 order_code có thật, thay
    // vì giả định content = order_code y hệt.
    private function extractOrderCode(string $content): ?string
    {
        preg_match_all('/\d{6,}/', $content, $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            if (Order::where('order_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return null;
    }
}
