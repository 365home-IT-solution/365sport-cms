<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriptionPayment as Pay;
use Illuminate\Support\Facades\Log;

// Thu PHÍ GÓI DỊCH VỤ qua PayOS — dùng bộ khoá PayOS CHUNG của nền tảng (PayOSCredentialResolver::resolve(null), tức config payos.*)
// và CHUNG webhook POST /webhook/payos: PaymentController gọi handleWebhook() trước khi xử lý đơn đặt sân; không phải giao dịch gói
// thì trả null để luồng khác chạy tiếp. Thanh toán thành công → SubscriptionService::markPaid() tự gia hạn/kích hoạt.
class SubscriptionPayosService
{
    // Dải mã đơn riêng (bắt đầu bằng 8) để không đụng đơn đặt sân trên cùng tài khoản PayOS.
    private const ORDER_CODE_PREFIX = '8';

    public function isConfigured(): bool
    {
        $c = PayOSCredentialResolver::resolve(null);

        return filled($c['client_id']) && filled($c['api_key']) && filled($c['checksum_key']);
    }

    /** Tạo link/QR PayOS. Lỗi PayOS không làm hỏng yêu cầu (vẫn có mã giao dịch để chuyển khoản tay & Super Admin xác nhận). */
    public function createLink(Pay $payment): void
    {
        if (! $this->isConfigured() || $payment->payos_order_code) {
            return;
        }

        try {
            $partner = $payment->partner;
            $orderCode = $this->newOrderCode();
            $expiredAt = now()->addHours((int) config('subscription.payment_link_hours', 24));
            $back = route('partner-onboarding.page');

            $link = PayOSCredentialResolver::client(null)->createPaymentLink([
                'orderCode'   => $orderCode,
                'amount'      => (int) $payment->amount_vnd,
                'description' => (string) $payment->transaction_code,
                'returnUrl'   => $back,
                'cancelUrl'   => $back,
                'buyerName'   => (string) ($partner?->legal_name ?: $partner?->name),
                'items'       => [['name' => 'Phi goi dich vu ' . $payment->months . ' thang', 'quantity' => 1, 'price' => (int) $payment->amount_vnd]],
                'expiredAt'   => $expiredAt->timestamp,
            ]);

            $payment->update([
                'payos_order_code'      => $orderCode,
                'payos_payment_link_id' => $link['paymentLinkId'] ?? null,
                'payos_checkout_url'    => $link['checkoutUrl'] ?? null,
                'payos_qr_code'         => $link['qrCode'] ?? null,
                'payos_expired_at'      => $expiredAt,
                'payos_bank_bin'        => $link['bin'] ?? null,
                'payos_account_number'  => $link['accountNumber'] ?? null,
                'payos_account_name'    => $link['accountName'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gói dịch vụ: không tạo được link PayOS', ['payment' => $payment->id, 'error' => $e->getMessage()]);
        }
    }

    public function cancelLink(Pay $payment): void
    {
        if (! $this->isConfigured() || ! $payment->payos_order_code) {
            return;
        }

        try {
            PayOSCredentialResolver::client(null)->cancelPaymentLink($payment->payos_order_code, 'Yeu cau thanh toan da dong');
        } catch (\Throwable $e) {
            Log::warning('Gói dịch vụ: không huỷ được link PayOS', ['payment' => $payment->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload  body webhook (chữ ký đã được kiểm tra)
     * @return array<string, mixed>|null  phản hồi nếu là giao dịch gói; null nếu không phải
     */
    public function handleWebhook(array $payload): ?array
    {
        $data = $payload['data'] ?? [];

        if (! is_array($data) || ! isset($data['orderCode'])) {
            return null;
        }

        $payment = Pay::query()->where('payos_order_code', (int) $data['orderCode'])->first();

        // Chuyển khoản trực tiếp có nội dung chứa mã giao dịch (SBxxxxxx).
        if (! $payment && preg_match('/SB[A-HJ-NP-Z2-9]{6}/', strtoupper((string) ($data['description'] ?? '')), $m)) {
            $payment = Pay::query()->where('transaction_code', $m[0])->first();
        }

        if (! $payment) {
            return null;
        }

        if ((string) ($data['code'] ?? $payload['code'] ?? '') === '00') {
            app(SubscriptionService::class)->markPaid($payment, (int) ($data['amount'] ?? 0), (string) ($data['reference'] ?? '') ?: 'PAYOS-' . $data['orderCode']);
        }

        return ['error' => 0, 'message' => 'Subscription webhook processed'];
    }

    private function newOrderCode(): int
    {
        do {
            $code = (int) (self::ORDER_CODE_PREFIX . substr((string) (int) (microtime(true) * 1000), -13));
        } while (Pay::query()->where('payos_order_code', $code)->exists());

        return $code;
    }
}
