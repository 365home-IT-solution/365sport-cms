<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AuditLog\Services\AuditLogger;
use Modules\Payment\Entities\Order;

// Logic DUY NHẤT để xác nhận 1 đơn "chuyển khoản ngân hàng thủ công" đã nhận được tiền — dùng
// chung cho CẢ 2 đường: (1) admin bấm tay "Xác nhận đã nhận tiền" sau khi xem ảnh biên lai
// (OrderTable.php), (2) webhook tự động từ SePay/Casso khi đối chiếu đúng giao dịch (xem
// SePayWebhookController). Tách ra đây để 2 đường LUÔN làm đúng y hệt nhau (set status/cấp mã truy
// cập/ghi audit log) — trước đây chỉ có đường admin, giờ thêm đường tự động không được phép lặp
// lại/lệch logic.
class BankTransferConfirmationService
{
    /**
     * @param string|null $confirmedByUserId  null = hệ thống tự xác nhận (webhook SePay/Casso),
     *                                         khác null = admin/nhân viên bấm tay xác nhận.
     */
    public function confirm(Order $order, ?string $confirmedByUserId = null): void
    {
        // Refresh trước khi quyết định nhánh — order truyền vào có thể đã cũ nếu 1 request khác
        // (webhook retry/admin bấm 2 lần) vừa xử lý xong ngay trước đó.
        $order->refresh();

        // Đơn đã 'paid' rồi (dù đơn thường hay đơn có cọc đã xong cả 2 vòng) → không còn gì để làm,
        // chặn xử lý trùng khi webhook gọi lại/admin bấm nhiều lần.
        if ($order->status === 'paid') {
            Log::info('BankTransferConfirmationService: already paid, skip', ['order_id' => $order->id]);
            return;
        }

        // Đơn có tách cọc (deposit_percent khác null) mà TRẠNG THÁI HIỆN TẠI CHƯA phải 'deposit'
        // (còn đang 'pending') → lần confirm() này là XÁC NHẬN TIỀN CỌC, không phải đã thanh toán
        // đủ. TRƯỚC ĐÂY hàm này LUÔN đánh dấu status='paid' bất kể là cọc hay đủ — đơn chỉ mới
        // chuyển 50% cọc bị hiện SAI thành "đã thanh toán đầy đủ" trên mọi báo cáo/trang tài khoản.
        // Khi status ĐÃ là 'deposit' rồi (cọc đã xác nhận từ trước), 1 lần confirm() nữa nghĩa là
        // khách vừa chuyển nốt phần còn lại (luồng "chuyển khoản còn lại": khách upload lại ảnh
        // biên lai mới → bank_transfer_status quay về 'pending' → admin duyệt lại — xem
        // GuestBookingController::uploadPaymentProof()/OrderController::uploadPaymentProof(), cả 2
        // đều chỉ chặn upload khi status đã 'paid'/'refunded', KHÔNG chặn khi đang 'deposit') — lúc
        // này mới đúng là xác nhận ĐÃ THANH TOÁN ĐỦ.
        $isDepositPayment = $order->deposit_percent !== null && $order->status !== 'deposit';

        // Chặn xử lý trùng cho nhánh xác nhận cọc — nếu cọc đã được xác nhận từ trước
        // (deposit_paid_at đã có) mà status vẫn đang 'pending' do lỗi/race hiếm gặp nào đó, không
        // ghi đè lại deposit_paid_at (giữ mốc thời gian xác nhận cọc THẬT đầu tiên).
        if ($isDepositPayment && ! is_null($order->deposit_paid_at)) {
            Log::info('BankTransferConfirmationService: deposit already confirmed, skip', ['order_id' => $order->id]);
            return;
        }

        DB::transaction(function () use ($order, $confirmedByUserId, $isDepositPayment) {
            if ($isDepositPayment) {
                $order->update([
                    'status'                     => 'deposit',
                    'deposit_paid_at'            => now(),
                    'bank_transfer_status'       => 'confirmed',
                    'bank_transfer_confirmed_at' => now(),
                    'bank_transfer_confirmed_by' => $confirmedByUserId,
                ]);
            } else {
                $order->update([
                    'status'                     => 'paid',
                    'paid_at'                    => now(),
                    'bank_transfer_status'       => 'confirmed',
                    'bank_transfer_confirmed_at' => now(),
                    'bank_transfer_confirmed_by' => $confirmedByUserId,
                    ...($order->deposit_percent !== null ? [
                        'remaining_paid_at'        => now(),
                        'remaining_payment_method' => 'bank_transfer',
                    ] : []),
                ]);
            }

            AuditLogger::log(
                'update', 'Order', $order,
                ['status' => 'pending', 'bank_transfer_status' => 'pending'],
                ['status' => $isDepositPayment ? 'deposit' : 'paid', 'bank_transfer_status' => 'confirmed'],
                'Đơn #' . $order->order_code . ($confirmedByUserId ? '' : ' (tự động xác nhận qua webhook ngân hàng)')
            );
        });

        // Cọc mới xác nhận — CHƯA thanh toán đủ, chưa tới lúc cấp mã truy cập (đợi xác nhận phần
        // còn lại, giống hệt cách luồng PayOS KHÔNG gọi handleSuccessfulPayment() khi mới nhận cọc).
        if ($isDepositPayment) {
            return;
        }

        // Đơn mua gói nhiều buổi (order_type=package_purchase, xem
        // Modules\BladeThemeV1\Livewire\Book::buyPackage()/ProductDetail::buyPackage()) — kích
        // hoạt CustomerPackage NGAY, KHÔNG chạy tiếp xuống logic cấp mã truy cập bên dưới (đơn
        // này không có checkin_date/checkout_date/sân thật, gọi assignCodeToOrder() sẽ sai hoàn
        // toàn). DÙNG CHUNG activateFromOrder() với đường PayOS (xem
        // PaymentController::handlePackagePurchasePaid()) — trước đây chỉ đường PayOS tạo được
        // CustomerPackage, xác nhận chuyển khoản tay/tự động cho đơn mua gói sẽ đánh dấu "đã nhận
        // tiền" nhưng KHÔNG kích hoạt gói nào nếu không tách ra dùng chung (bug thật cần tránh).
        if ($order->order_type === 'package_purchase') {
            \App\Models\CustomerPackage::activateFromOrder($order);
            return;
        }
    }
}
