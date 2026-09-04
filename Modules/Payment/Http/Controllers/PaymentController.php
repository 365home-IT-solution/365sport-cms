<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use PayOS\PayOS;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\OrderNotificationMail;
use App\Services\TelegramService;
use App\Services\PayOSCredentialResolver;
use Modules\BladeThemeV1\Services\Zns\ZaloZnsService;
use Modules\Payment\Entities\Order;
use Carbon\Carbon;

class PaymentController extends Controller
{
    private string $payOSClientId;
    private string $payOSApiKey;
    private string $payOSChecksumKey;
    protected $zaloZnsService;

    public function __construct(
        ZaloZnsService $zaloZnsService
    ) {
        $this->payOSClientId    = Config::get('payos.client_id');
        $this->payOSApiKey      = Config::get('payos.api_key');
        $this->payOSChecksumKey = Config::get('payos.checksum_key');
        $this->zaloZnsService    = $zaloZnsService;
    }

    // =========================================================
    // BUILD TELEGRAM MESSAGE
    // =========================================================

    private function isStyle2Order(Order $order): bool
    {
        return optional($order->items->first()->product)->styles == 2;
    }

    private function sendTelegram(Order $order, string $msg): void
    {
        $telegram = app(TelegramService::class);
        if ($this->isStyle2Order($order)) {
            $telegram->sendDepositMessage($msg);
        } else {
            $telegram->sendMessage($msg);
        }
    }

private function buildTelegramMessage(Order $order, string $status): string
{
    $amount = number_format($order->amount, 0, ',', '.');

    $emoji = match ($status) {
        'paid'           => '✅',
        'deposit'        => '💰',
        'remaining_paid' => '🎉',
        'cancelled'      => '❌',
        'expired'        => '⏰',
        default          => '❓',
    };

    $title = match ($status) {
        'paid'           => 'Đơn hàng thanh toán thành công!',
        'deposit'        => 'Đã nhận tiền cọc!',
        'remaining_paid' => 'Đã thanh toán đủ — mã cổng đã gửi!',
        'cancelled'      => 'Đơn hàng đã bị hủy!',
        'expired'        => 'Đơn hàng hết hạn thanh toán!',
        default          => 'Trạng thái không xác định',
    };

    $message = "{$emoji} <b>{$title}</b>\n"
             . "🧾 Mã đơn: <code>{$order->order_code}</code>\n"
             . "👤 Khách: {$order->buyer_name}\n"
             . "📞 SĐT: {$order->buyer_phone}\n";

    // ========== CỌC / FULL AMOUNT ==========
    // full_amount CỐ ĐỊNH = tổng giá thật — tiền cọc đã thu tính riêng qua depositDueAmount().
    if ($order->deposit_percent !== null) {
        $fullAmtNum = (int) $order->full_amount;
        $paidAmtNum = $order->depositDueAmount();
        $fullAmt   = number_format($fullAmtNum, 0, ',', '.');
        $paidAmt   = number_format($paidAmtNum, 0, ',', '.');
        $remaining = number_format(max(0, $fullAmtNum - $paidAmtNum), 0, ',', '.');
        $pct       = $order->deposit_percent;
        $message  .= "💵 Tổng tiền sân: {$fullAmt}đ\n"
                  .  "💳 Tiền cọc ({$pct}%): {$paidAmt}đ\n"
                  .  "⏳ Còn lại khi nhận sân: {$remaining}đ\n";
    } else {
        $message .= "💰 Tổng tiền: {$amount}đ\n";
    }

    // ========== KHUNG GIỜ / NGÀY Ở ==========
    $order->loadMissing('items');
    $items = $order->items->filter(fn($item) => $item->extra_fee == 0);

    if ($items->isNotEmpty()) {
        $message .= "🏠 <b>Sân đã đặt:</b>\n";
        foreach ($items->values() as $index => $item) {
            $checkin  = $item->checkin_date  ? $item->checkin_date->format('H:i d/m/Y')  : '';
            $checkout = $item->checkout_date ? $item->checkout_date->format('H:i d/m/Y') : '';
            $price    = number_format($item->price, 0, ',', '.');
            $message .= "  " . ($index + 1) . ". {$item->name}\n"
                     .  "     🕐 {$checkin} → {$checkout}\n"
                     .  "     💵 {$price}đ\n";
        }
    }

    // ========== PHỤ PHÍ KHÁCH ==========
    $extraItem = $order->items->firstWhere('extra_fee', '>', 0);
    if ($extraItem) {
        $extraFee = number_format($extraItem->extra_fee, 0, ',', '.');
        $message .= "➕ Phụ phí khách: +{$extraFee}đ\n";
    }

    // ========== DỊCH VỤ THÊM ==========
    $order->loadMissing('services');
    $services = $order->services;

    if ($services && $services->isNotEmpty()) {
        $serviceTotal = $services->sum('subtotal');
        $message .= "🛎️ <b>Dịch vụ thêm:</b>\n";
        foreach ($services as $service) {
            $subtotal = number_format($service->subtotal, 0, ',', '.');
            $message .= "  • {$service->service_name} x{$service->quantity}"
                     .  " → {$subtotal}đ\n";
        }
        $message .= "  💳 Tổng dịch vụ: +" . number_format($serviceTotal, 0, ',', '.') . "đ\n";
    }

    $message .= "🕐 Thời gian: " . now()->format('H:i:s d/m/Y');

    return $message;
}

    // =========================================================
    // CHECK PAYMENT STATUS (dùng cho trang success/cancel)
    // =========================================================

    public function checkPaymentStatus($order, $hintCode = null)
    {
        try {
            if (!$order) {
                Log::error('Order is null in checkPaymentStatus');
                return response()->json(['error' => 1, 'message' => 'Order not found'], 404);
            }

            $order->load('items');

            // Đơn mua gói nhiều buổi — KHÔNG đi qua bất kỳ nhánh cọc/remaining/access-code nào bên
            // dưới (toàn bộ nhánh đó giả định đơn có sân/khung giờ thật), xử lý RIÊNG và return
            // ngay (xem handlePackagePurchasePaid(), cùng nhánh webhook PAID đang dùng — hàm này
            // tự kiểm tra idempotent qua status).
            if ($order->order_type === 'package_purchase') {
                if ($order->status !== 'paid') {
                    $payOS    = PayOSCredentialResolver::client($order->partner);
                    $response = $payOS->getPaymentLinkInformation((int) $order->order_code);
                    if (($response['status'] ?? null) === 'PAID') {
                        $this->handlePackagePurchasePaid($order);
                    }
                    return response()->json(['error' => 0, 'message' => 'Success', 'data' => $response ?? []]);
                }
                return response()->json(['error' => 0, 'message' => 'Already paid']);
            }

            // ── Trường hợp 1: đơn đã thanh toán đầy đủ → không làm gì thêm ──
            if ($order->status === 'paid') {
                Log::info('checkPaymentStatus: order already paid', ['order_id' => $order->id]);
                return response()->json(['error' => 0, 'message' => 'Already paid']);
            }

            // ── Trường hợp 2: thanh toán TIỀN CÒN LẠI (remaining payment) ──
            // hintCode khớp remaining_payos_code  OR  có remaining_payos_code + checkout_url rỗng
            $isRemainingPayment = !empty($order->remaining_payos_code)
                && $order->status === 'deposit'
                && (
                    ($hintCode && (string)$hintCode === (string)$order->remaining_payos_code)
                    || (empty($hintCode) && empty($order->checkout_url) && !empty($order->remaining_payos_code))
                );

            if ($isRemainingPayment) {
                $payOS    = PayOSCredentialResolver::client($order->partner);
                $response = $payOS->getPaymentLinkInformation((int) $order->remaining_payos_code);
                $status   = $response['status'] ?? 'PENDING';

                Log::info('checkPaymentStatus: checking remaining payment', [
                    'order_id'       => $order->id,
                    'remaining_code' => $order->remaining_payos_code,
                    'payos_status'   => $status,
                ]);

                if ($status === 'PAID') {
                    $this->handleRemainingPayment($order);
                }

                return response()->json(['error' => 0, 'message' => 'Success', 'data' => $response]);
            }

            // ── Trường hợp 3: retry cọc (deposit_retry_payos_code) ──
            $isDepositRetry = $order->status === 'deposit'
                && $order->deposit_percent !== null
                && !empty($order->deposit_retry_payos_code)
                && ($hintCode === null || (string)$hintCode === (string)$order->deposit_retry_payos_code);

            if ($isDepositRetry) {
                $payOS     = PayOSCredentialResolver::client($order->partner);
                $orderCode = (int) $order->deposit_retry_payos_code;
            } else {
                $payOS     = PayOSCredentialResolver::client($order->partner);
                $orderCode = (int) $order->order_code;
            }

            $response = $payOS->getPaymentLinkInformation($orderCode);
            $status   = $response['status'] ?? 'PENDING';

            switch ($status) {
                case 'PAID':
                    // Guard: đơn cọc đã hoàn thành (checkout_url rỗng) → không revert về deposit
                    if ($order->deposit_percent !== null && empty($order->checkout_url)) {
                        // Deposit đã được ghi nhận trước đó, không cần update thêm
                        Log::info('checkPaymentStatus: deposit already marked, skipping', ['order_id' => $order->id]);
                        break;
                    }
                    if ($order->deposit_percent !== null) {
                        $order->update(['status' => 'deposit', 'checkout_url' => null]);
                        try {
                            $this->sendTelegram($order, $this->buildTelegramMessage($order, 'deposit'));
                            Log::info('Telegram deposit notification sent via checkPaymentStatus', ['order_id' => $order->id]);
                        } catch (\Exception $e) {
                            Log::error('Telegram failed in checkPaymentStatus', ['error' => $e->getMessage()]);
                        }
                    } else {
                        $order->update(['status' => 'paid', 'paid_at' => now()]);
                        $this->handleSuccessfulPayment($order);
                        try {
                            $this->sendTelegram($order, $this->buildTelegramMessage($order, 'paid'));
                            Log::info('Telegram paid notification sent via checkPaymentStatus', ['order_id' => $order->id]);
                        } catch (\Exception $e) {
                            Log::error('Telegram failed in checkPaymentStatus', ['error' => $e->getMessage()]);
                        }
                    }
                    break;

                case 'CANCELLED':
                    $order->update(['status' => 'failed']);
                    try {
                        $this->sendTelegram($order, $this->buildTelegramMessage($order, 'cancelled'));
                    } catch (\Exception $e) {
                        Log::error('Telegram cancel failed in checkPaymentStatus', ['error' => $e->getMessage()]);
                    }
                    break;

                case 'EXPIRED':
                    $order->update(['status' => 'cancelled_payment']);
                    try {
                        $this->sendTelegram($order, $this->buildTelegramMessage($order, 'expired'));
                    } catch (\Exception $e) {
                        Log::error('Telegram expired failed in checkPaymentStatus', ['error' => $e->getMessage()]);
                    }
                    break;

                default:
                    if (!in_array($order->status, ['paid', 'deposit'])) {
                        $order->update(['status' => 'pending']);
                    }
                    break;
            }

            return response()->json(['error' => 0, 'message' => 'Success', 'data' => $response]);

        } catch (\Exception $e) {
            Log::error('PayOS Check Status Error', [
                'order_id'   => $order->id ?? null,
                'order_code' => $order->order_code ?? null,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 1, 'message' => $e->getMessage()], 400);
        }
    }

    // =========================================================
    // HANDLE SUCCESSFUL PAYMENT (full payment or remaining)
    // =========================================================

    protected function handleSuccessfulPayment($order)
    {
        try {
            $firstItem = $order->items->sortBy('checkin_date')->first();

            $categoryId = $order->category_id;
            if (!$categoryId && $firstItem && $firstItem->product) {
                $categoryId = $firstItem->product->category_id;
                $order->update(['category_id' => $categoryId]);
            }

            // Không dùng ZaloZNS nữa — Telegram xử lý ở caller
            $this->sendAdminNotification($order);

            return true;
        } catch (\Exception $e) {
            Log::error('handleSuccessfulPayment error', [
                'order_id' => $order->id ?? null,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // =========================================================
    // HANDLE REMAINING PAYMENT (tiền còn lại sau cọc)
    // =========================================================

    private function handleRemainingPayment(Order $order): bool
    {
        try {
            // Chặn xử lý trùng — webhook cổng thanh toán CÓ THỂ gọi lại nhiều lần cho cùng 1 giao
            // dịch thanh toán phần còn lại (retry khi không nhận response 200 kịp thời). Trước đây
            // hàm này không kiểm tra gì trước khi update — 2 lần gọi trùng sẽ cộng thêm tiền/gửi
            // lại thông báo/broadcast 2 lần cho cùng 1 lần thanh toán thật.
            if (! is_null($order->remaining_paid_at)) {
                Log::info('handleRemainingPayment: already processed, skip', ['order_id' => $order->id]);
                return true;
            }

            // full_amount CỐ ĐỊNH = tổng giá thật từ lúc tạo đơn (không tính ngược từ tiền cọc
            // nữa). extra_charge_amount = khoản phát sinh cộng thêm vào tổng thực thu cuối cùng.
            $processedOrder = DB::transaction(function () use ($order): ?Order {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
                if (! is_null($locked->remaining_paid_at)) {
                    return null;
                }

                $extraCharge = (int) ($locked->extra_charge_amount ?? 0);
                $locked->update([
                    'status'                   => 'paid',
                    'amount'                   => (int) $locked->full_amount + $extraCharge,
                    'extra_charge_amount'      => null,
                    'settled_adjustment_total' => (int) ($locked->settled_adjustment_total ?? 0) + $extraCharge,
                    'remaining_paid_at'        => now(),
                    'paid_at'                  => now(),
                ]);

                return $locked->fresh();
            });

            if ($processedOrder === null) {
                Log::info('handleRemainingPayment: already processed after lock, skip', ['order_id' => $order->id]);
                return true;
            }
            $order = $processedOrder;

            Log::info('Order remaining payment received', [
                'order_id'   => $order->id,
                'order_code' => $order->order_code,
            ]);

            // FCM → khách
            try {
                $notifService = app(\App\Services\NotificationFcmService::class);
                $title        = "Đơn #{$order->order_code}: Thanh toán thành công";
                $body         = 'Thanh toán phần còn lại thành công. Mã cổng sẽ được gán trong giây lát.';
                $extra        = ['order_code' => (string) $order->order_code, 'type' => 'order_remaining_paid'];
                if (is_null($order->customer_id) && $order->device_token) {
                    $notifService->sendToGuestToken($order->device_token, $title, $body, 'order_remaining_paid', $extra);
                } elseif ($order->customer_id) {
                    $customer = $order->customer;
                    if ($customer) {
                        $notifService->sendToCustomer($customer, $title, $body, 'order_remaining_paid', $extra);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('handleRemainingPayment: FCM failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            // WS broadcast → cập nhật status trên app khách ngay
            try {
                app(\App\Services\OrderRealtimeService::class)->broadcastOrderUpdate(
                    (string) $order->order_code,
                    ['status' => 'paid', 'remaining_paid_at' => now()->toISOString()],
                    $order->customer_id,
                );
            } catch (\Throwable $e) {
                Log::warning('handleRemainingPayment: broadcastOrderUpdate failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            // Gán mã cổng sau khi thanh toán đủ
            $this->handleSuccessfulPayment($order);

            // Telegram — gửi theo nhóm phù hợp (styles=2 → deposit chat)
            try {
                $this->sendTelegram($order, $this->buildTelegramMessage($order, 'remaining_paid'));
                Log::info('Telegram remaining_paid notification sent', ['order_id' => $order->id]);
            } catch (\Exception $e) {
                Log::error('Telegram remaining_paid notification failed', ['error' => $e->getMessage()]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('handleRemainingPayment error', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // =========================================================
    // WEBHOOK PAYOS
    // =========================================================

    public function handlePayOSWebhook(Request $request)
    {
        try {
            Log::info('PayOS Webhook received', [
                'headers' => $request->headers->all(),
                'body'    => $request->all(),
                'ip'      => $request->ip(),
            ]);

            $webhookData = $request->all();
            $data        = $webhookData['data'] ?? $webhookData;
            $orderCode   = $data['orderCode'] ?? null;
            $status      = $data['status'] ?? null;

            // Tra ĐÚNG checksum key để xác minh chữ ký — nếu đơn/mã webhook này thuộc về đối tác đã
            // tự cấu hình PayOS riêng (Partner::hasPayosConfigured()) thì PHẢI dùng checksum của
            // CHÍNH đối tác đó (PayOS ký bằng checksum của tài khoản merchant đã tạo link thanh
            // toán, không phải checksum chung của nền tảng) — không tìm được đơn (mã lạ/test) thì
            // coi như dùng checksum CHUNG như hành vi cũ.
            if (!$this->verifyWebhookSignature($request, $this->resolveWebhookChecksumKey($orderCode))) {
                Log::error('PayOS Webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            if (!$orderCode) {
                Log::error('PayOS Webhook: No orderCode provided', ['data' => $webhookData]);
                return response()->json(['error' => 'No orderCode provided'], 400);
            }

            $order = Order::with('items')->where('order_code', $orderCode)->first();

            // Tìm theo current_payos_code (retry / update đơn hàng dùng code mới)
            if (!$order) {
                $order = Order::with('items')->where('current_payos_code', $orderCode)->first();
            }

            // Kiểm tra xem đây có phải thanh toán còn lại không
            if (!$order) {
                $parentOrder = Order::with('items')
                    ->where('remaining_payos_code', $orderCode)
                    ->first();

                if ($parentOrder && (isset($data['code']) ? $data['code'] == '00' : $status === 'PAID')) {
                    Log::info('PayOS Webhook: Remaining payment received', [
                        'remaining_code' => $orderCode,
                        'parent_order'   => $parentOrder->id,
                    ]);
                    $updated = $this->handleRemainingPayment($parentOrder);
                    return response()->json([
                        'error'   => 0,
                        'message' => $updated ? 'Remaining payment processed' : 'Already processed',
                    ], 200);
                }

                // Kiểm tra xem đây có phải thanh toán cọc (lần 2 — retry) không
                $depositRetryOrder = Order::with('items')
                    ->where('deposit_retry_payos_code', $orderCode)
                    ->first();

                if ($depositRetryOrder && (isset($data['code']) ? $data['code'] == '00' : $status === 'PAID')) {
                    Log::info('PayOS Webhook: Deposit retry payment received', [
                        'retry_code'  => $orderCode,
                        'order_id'    => $depositRetryOrder->id,
                    ]);
                    // Xử lý giống như deposit payment gốc
                    $updated = false;
                    if ($depositRetryOrder->status === 'deposit' && $depositRetryOrder->deposit_percent !== null) {
                        $updated = $this->handlePaidStatus($depositRetryOrder, $data);
                    }
                    return response()->json([
                        'error'   => 0,
                        'message' => $updated ? 'Deposit retry processed' : 'Already processed',
                    ], 200);
                }

                // Kiểm tra xem đây có phải extra charge (phát sinh thêm) không
                $extraChargeOrder = Order::with('items')
                    ->where('extra_charge_payos_code', $orderCode)
                    ->first();

                if ($extraChargeOrder && (isset($data['code']) ? $data['code'] == '00' : $status === 'PAID')) {
                    Log::info('PayOS Webhook: Extra charge payment received', [
                        'extra_code' => $orderCode,
                        'order_id'   => $extraChargeOrder->id,
                    ]);

                    $processed = app(\App\Services\ExtraChargeService::class)->handleExtraChargePaid($extraChargeOrder);
                    if (! $processed) {
                        return response()->json(['error' => 0, 'message' => 'Extra charge already processed']);
                    }

                    // Notify khách real-time
                    try {
                        $amount = number_format((int) $extraChargeOrder->extra_charge_amount, 0, ',', '.');
                        $title  = "Thanh toán khoản phát sinh thành công";
                        $body   = "Đơn #{$extraChargeOrder->order_code}: {$amount}đ đã được xác nhận.";
                        $extra  = [
                            'order_code' => (string) $extraChargeOrder->order_code,
                            'type'       => 'order_extra_charge_paid',
                        ];

                        $notifService = app(\App\Services\NotificationFcmService::class);

                        if (is_null($extraChargeOrder->customer_id) && $extraChargeOrder->device_token) {
                            $notifService->sendToGuestToken($extraChargeOrder->device_token, $title, $body, 'order_extra_charge_paid', $extra);
                        } elseif ($extraChargeOrder->customer_id) {
                            $customer = $extraChargeOrder->customer;
                            if ($customer) {
                                $notifService->sendToCustomer($customer, $title, $body, 'order_extra_charge_paid', $extra);
                            }
                        }

                        app(\App\Services\OrderRealtimeService::class)->broadcastOrderUpdate(
                            (string) $extraChargeOrder->order_code,
                            ['extra_charge' => ['is_paid' => true, 'paid_at' => now()->toISOString(), 'payment_method' => 'payos']],
                            $extraChargeOrder->customer_id,
                        );
                    } catch (\Throwable $e) {
                        Log::warning('PayOS Webhook: Extra charge notify failed', [
                            'order_id' => $extraChargeOrder->id,
                            'error'    => $e->getMessage(),
                        ]);
                    }

                    return response()->json(['error' => 0, 'message' => 'Extra charge processed']);
                }

                Log::warning('PayOS Webhook: Order not found', ['orderCode' => $orderCode]);
                return response()->json(['message' => 'Order not found, assuming test request'], 200);
            }

            if (empty($status) && isset($data['code']) && $data['code'] == '00') {
                $status = 'PAID';
            }
            $status = $status ?? 'PENDING';

            Log::info('PayOS Webhook: Processing order', [
                'order_id'       => $order->id,
                'order_code'     => $orderCode,
                'current_status' => $order->status,
                'webhook_status' => $status,
            ]);

            $updated = $this->processWebhookStatus($order, $status, $data);

            if ($updated) {
                return response()->json([
                    'error'   => 0,
                    'message' => 'Webhook processed successfully',
                    'data'    => ['orderCode' => $orderCode, 'status' => $order->status],
                ], 200);
            }

            return response()->json(['error' => 0, 'message' => 'No update needed'], 200);

        } catch (\Exception $e) {
            Log::error('PayOS Webhook Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 1, 'message' => 'Internal server error'], 500);
        }
    }

    // =========================================================
    // VERIFY WEBHOOK SIGNATURE
    // =========================================================

    private function verifyWebhookSignature(Request $request, ?string $checksumKey = null): bool
    {
        try {
            $receivedSignature = $request->input('signature');

            if (!$receivedSignature) {
                Log::warning('PayOS Webhook: No signature in body — request rejected');
                return false;
            }

            $data = $request->input('data');
            ksort($data);

            $hashStr = '';
            foreach ($data as $key => $value) {
                $hashStr .= $key . '=' . ($value ?? '') . '&';
            }
            $hashStr = rtrim($hashStr, '&');

            $key = $checksumKey ?? $this->payOSChecksumKey;

            // KHÔNG được tính hash với key rỗng — nếu cấu hình PayOS (bảng payment_configs) chưa
            // được tạo/điền, $key có thể là null/rỗng, khi đó hash_hmac('sha256', $hashStr, '')
            // cho ra 1 kết quả CỐ ĐỊNH mà bất kỳ ai cũng tự tính lại được (không cần biết bí mật
            // nào) — kẻ tấn công có thể tự ký request rồi POST giả webhook để đánh dấu đơn "đã
            // thanh toán" miễn phí. So sánh khác SePayWebhookController::isAuthentic() (đã có sẵn
            // guard này) — thêm cho khớp, phòng vệ theo chiều sâu dù bình thường $key luôn được
            // seed sẵn.
            if (! $key) {
                Log::warning('PayOS Webhook: checksum key rỗng/chưa cấu hình — từ chối webhook (an toàn hơn tiện lợi)');
                return false;
            }

            $expected = hash_hmac('sha256', $hashStr, $key);

            return hash_equals($expected, $receivedSignature);

        } catch (\Exception $e) {
            Log::error('Signature verification error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // Tìm checksum key ĐÚNG đối tác cho 1 mã webhook — thử LẦN LƯỢT tất cả cột mã PayOS có thể
    // khớp (giống hệt thứ tự dò tìm đơn hàng ở handlePayOSWebhook() bên trên, chỉ để xác định
    // PARTNER trước khi verify chữ ký, không xử lý nghiệp vụ). Không tìm được đơn nào (mã lạ/test
    // webhook) → null, verifyWebhookSignature() tự dùng checksum CHUNG của nền tảng như cũ.
    private function resolveWebhookChecksumKey(?string $orderCode): ?string
    {
        if (!$orderCode) {
            return null;
        }

        $order = Order::where('order_code', $orderCode)
            ->orWhere('current_payos_code', $orderCode)
            ->orWhere('remaining_payos_code', $orderCode)
            ->orWhere('deposit_retry_payos_code', $orderCode)
            ->orWhere('extra_charge_payos_code', $orderCode)
            ->first();

        if (!$order || !$order->partner || !$order->partner->hasPayosConfigured()) {
            return null;
        }

        return PayOSCredentialResolver::resolve($order->partner)['checksum_key'];
    }

    // =========================================================
    // PROCESS WEBHOOK STATUS
    // =========================================================

    private function processWebhookStatus(Order $order, string $status, array $data): bool
    {
        // Đơn đã paid hoặc deposit: không cho phép downgrade do webhook muộn/cũ.
        // Chỉ cho phép tiếp tục xử lý nếu webhook báo PAID (idempotency check phía dưới).
        if (in_array($order->status, ['paid', 'deposit']) && $status !== 'PAID') {
            Log::info('processWebhookStatus: skipping stale webhook for paid/deposit order', [
                'order_id'       => $order->id,
                'current_status' => $order->status,
                'webhook_status' => $status,
            ]);
            return false;
        }

        if ($order->status === 'paid' && $status === 'PAID') {
            Log::info('Order already paid', ['order_id' => $order->id, 'current_status' => $order->status]);
            return false;
        }

        switch ($status) {
            case 'PAID':      return $this->handlePaidStatus($order, $data);
            case 'CANCELLED': return $this->handleCancelledStatus($order, $data);
            case 'EXPIRED':   return $this->handleExpiredStatus($order, $data);
            case 'PENDING':
                if (! in_array($order->status, ['paid', 'deposit'])) {
                    $order->update(['status' => 'pending']);
                }
                return true;
            default:
                Log::warning('Unknown payment status', ['order_id' => $order->id, 'status' => $status]);
                return false;
        }
    }

    // =========================================================
    // HANDLE PAID STATUS ✅
    // =========================================================

    private function handlePaidStatus(Order $order, array $data): bool
    {
        try {
            // Đơn MUA GÓI NHIỀU BUỔI (order_type=package_purchase, xem
            // ProductDetail::buyPackage()) — KHÔNG có sân/khung giờ thật để gán mã cổng/checkin,
            // xử lý RIÊNG và return NGAY, không chạy tiếp logic đặt sân bên dưới (deposit/access
            // code/Telegram booking...).
            if ($order->order_type === 'package_purchase') {
                return $this->handlePackagePurchasePaid($order);
            }

            // Nếu là đơn cọc (deposit_percent != null) → chỉ ghi nhận cọc,
            // KHÔNG gán mã cổng. Mã cổng chỉ gán sau khi khách thanh toán phần còn lại.
            if ($order->deposit_percent !== null) {
                $order->update([
                    'status'          => 'deposit',
                    'checkout_url'    => null,
                    'deposit_paid_at' => now(),
                ]);

                Log::info('Deposit paid — awaiting remaining payment', [
                    'order_id'        => $order->id,
                    'deposit_percent' => $order->deposit_percent,
                ]);

                // Telegram — gửi vào nhóm cọc nếu có cấu hình TELEGRAM_DEPOSIT_CHAT_ID
                try {
                    $this->sendTelegram($order, $this->buildTelegramMessage($order, 'deposit'));
                    Log::info('Telegram deposit notification sent', ['order_id' => $order->id]);
                } catch (\Exception $e) {
                    Log::error('Telegram deposit notification failed', ['error' => $e->getMessage()]);
                }

                return true;
            }

            // Đơn thanh toán đầy đủ (không cọc)
            $order->update(['status' => 'paid', 'paid_at' => now()]);

            Log::info('Order paid (full) successfully via webhook', [
                'order_id'   => $order->id,
                'order_code' => $order->order_code,
            ]);

            try {
                $this->sendTelegram($order, $this->buildTelegramMessage($order, 'paid'));
                Log::info('Telegram paid notification sent', ['order_id' => $order->id]);
            } catch (\Exception $e) {
                Log::error('Telegram paid notification failed', ['error' => $e->getMessage()]);
            }

            $this->handleSuccessfulPayment($order);

            return true;
        } catch (\Exception $e) {
            Log::error('Error handling paid status', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // =========================================================
    // HANDLE PACKAGE PURCHASE PAID 🎫
    // =========================================================

    /**
     * Khách vừa thanh toán xong 1 gói nhiều buổi (order_type=package_purchase) — kích hoạt
     * CustomerPackage ngay, KHÔNG đụng gì tới access code/checkin/Telegram booking (đơn này không
     * có sân/khung giờ thật). Idempotent qua order->status check (webhook PayOS có thể gọi lại).
     */
    private function handlePackagePurchasePaid(Order $order): bool
    {
        if ($order->status === 'paid') {
            Log::info('Package purchase already paid, skipping', ['order_id' => $order->id]);
            return false;
        }

        $order->update(['status' => 'paid', 'paid_at' => now()]);

        // Logic tạo CustomerPackage DÙNG CHUNG với đường chuyển khoản ngân hàng (xem
        // App\Models\CustomerPackage::activateFromOrder() — trước đây chỉ nằm ở đây, tách ra dùng
        // chung khi thêm phương thức chuyển khoản cho mua gói).
        $package = \App\Models\CustomerPackage::activateFromOrder($order);
        if (! $package) {
            Log::error('handlePackagePurchasePaid: package plan not found', [
                'order_id' => $order->id, 'package_plan_id' => $order->package_plan_id,
            ]);
            return true; // Đơn vẫn đánh dấu paid (đã thu tiền thật) — admin xử lý thủ công nếu plan bị xoá giữa chừng.
        }

        Log::info('Package purchase activated', [
            'order_id' => $order->id, 'package_plan_id' => $package->package_plan_id, 'buyer_phone' => $order->buyer_phone,
        ]);

        return true;
    }

    // =========================================================
    // HANDLE CANCELLED STATUS ❌
    // =========================================================

    private function handleCancelledStatus(Order $order, array $data): bool
    {
        try {
            $order->update(['status' => 'failed']);

            Log::info('Order cancelled via webhook', [
                'order_id'   => $order->id,
                'order_code' => $order->order_code,
            ]);

            try {
                $this->sendTelegram($order, $this->buildTelegramMessage($order, 'cancelled'));
                Log::info('Telegram cancel notification sent', ['order_id' => $order->id]);
            } catch (\Exception $e) {
                Log::error('Telegram cancel notification failed', ['error' => $e->getMessage()]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error handling cancelled status', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // =========================================================
    // HANDLE EXPIRED STATUS ⏰ (QR hết hạn)
    // =========================================================

    private function handleExpiredStatus(Order $order, array $data): bool
    {
        try {
            // ✅ Trạng thái riêng: "Hủy Thanh toán" (cancelled_payment)
            $order->update(['status' => 'cancelled_payment']);

            Log::info('Order expired (QR timeout) via webhook', [
                'order_id'   => $order->id,
                'order_code' => $order->order_code,
            ]);

            try {
                $this->sendTelegram($order, $this->buildTelegramMessage($order, 'expired'));
                Log::info('Telegram expired notification sent', ['order_id' => $order->id]);
            } catch (\Exception $e) {
                Log::error('Telegram expired notification failed', ['error' => $e->getMessage()]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Error handling expired status', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    // =========================================================
    // SEND ADMIN EMAIL NOTIFICATION
    // =========================================================

    private function sendAdminNotification(Order $order)
    {
        try {
            $adminEmail = env('ADMIN_EMAIL', '365home.cantho@gmail.com');
            Mail::to($adminEmail)->send(new OrderNotificationMail($order));
            Log::info('Admin notification email sent', [
                'order_id'    => $order->id,
                'order_code'  => $order->order_code,
                'admin_email' => $adminEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send admin notification email', [
                'order_id'   => $order->id,
                'order_code' => $order->order_code,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // =========================================================
    // CREATE REMAINING PAYMENT LINK (tiền cọc còn lại)
    // =========================================================

    public function createRemainingPayment(Request $request, string $code)
    {
        try {
            $order = Order::with('items')->where('order_code', $code)->firstOrFail();

            if ($order->status !== 'deposit') {
                return response()->json([
                    'error'   => 1,
                    'message' => 'Đơn này không ở trạng thái chờ thanh toán còn lại.',
                ], 422);
            }

            // Nếu đã tạo link rồi, trả luôn
            if ($order->remaining_payos_code && $order->remaining_checkout_url) {
                return response()->json([
                    'error'        => 0,
                    'checkoutUrl'  => $order->remaining_checkout_url,
                    'payosCode'    => $order->remaining_payos_code,
                ]);
            }

            // full_amount CỐ ĐỊNH = tổng giá thật — tiền cọc đã thu tính qua depositDueAmount().
            $depositPaid = $order->depositDueAmount();
            $extraCharge = (int) ($order->extra_charge_amount ?? 0);
            $remaining   = max(0, (int) $order->full_amount - $depositPaid) + $extraCharge;

            if ($remaining <= 0) {
                return response()->json(['error' => 1, 'message' => 'Không còn khoản cọc còn lại.'], 422);
            }

            // Tạo order_code mới cho PayOS (phải là integer)
            $remainingCode = (int) (intval(substr(strval(microtime(true) * 10000), -6)) . rand(10, 99));

            // Dùng PayOS thứ 2 cho thanh toán tiền còn lại (fallback về PayOS chính nếu chưa cấu hình)
            $payOS = PayOSCredentialResolver::client($order->partner);

            $expiredAt = now()->addMinutes(30)->timestamp;

            $data = [
                'orderCode'   => $remainingCode,
                'amount'      => $remaining,
                'description' => 'Tt con lai - ' . $order->order_code,
                'returnUrl'   => route('booking.detail', ['code' => $order->order_code]) . '?remaining_paid=1',
                'cancelUrl'   => route('booking.detail', ['code' => $order->order_code]) . '?remaining_cancelled=1',
                'buyerName'   => $order->buyer_name,
                'buyerPhone'  => $order->buyer_phone,
                'buyerEmail'  => $order->buyer_email ?? '',
                'expiredAt'   => $expiredAt,
                'items'       => [
                    [
                        'name'     => 'Tiền còn lại - ' . ($order->items->first()->name ?? 'Sân'),
                        'quantity' => 1,
                        'price'    => $remaining,
                    ],
                ],
            ];

            $response = $payOS->createPaymentLink($data);

            $checkoutUrl = $response['checkoutUrl'] ?? null;

            if (!$checkoutUrl) {
                return response()->json(['error' => 1, 'message' => 'Không thể tạo link thanh toán.'], 500);
            }

            $order->update([
                'remaining_payos_code'    => $remainingCode,
                'remaining_checkout_url'  => $checkoutUrl,
            ]);

            Log::info('Remaining payment link created', [
                'order_id'       => $order->id,
                'remaining_code' => $remainingCode,
                'amount'         => $remaining,
            ]);

            return response()->json([
                'error'       => 0,
                'checkoutUrl' => $checkoutUrl,
                'payosCode'   => $remainingCode,
            ]);

        } catch (\Exception $e) {
            Log::error('createRemainingPayment error', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 1, 'message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}
