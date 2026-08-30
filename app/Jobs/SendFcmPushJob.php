<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer;
use App\Models\NotificationFcmRecipient;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

// Gửi push FCM thật sự qua hàng đợi thay vì đồng bộ ngay trong request — trước đây mỗi lần đặt
// sân/thanh toán thành công/waitlist trống chỗ đều chờ luôn cuộc gọi HTTP tới FCM (có thể chậm/
// timeout) mới trả response cho khách. Xem NotificationFcmService::sendToCustomer() (nơi tạo job
// này) — bản ghi NotificationFcmRecipient tạo trước với status='sent' (thông báo hiện trong app
// ngay, không đợi job chạy — NotificationController lọc where('status','sent') để hiển thị). Job
// này chỉ SỬA lại thành 'failed' khi push thật sự thất bại; nếu retry sau đó thành công, status
// được ghi lại 'sent' nên thông báo tự hiện lại (không bao giờ kẹt mãi ở trạng thái ẩn).
class SendFcmPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data,
        public readonly int $notificationId,
    ) {}

    public function handle(FcmService $fcm): void
    {
        try {
            $fcm->sendToCustomer($this->customer, $this->title, $this->body, $this->data);

            NotificationFcmRecipient::where('notification_fcm_id', $this->notificationId)
                ->where('customer_id', $this->customer->id)
                ->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            NotificationFcmRecipient::where('notification_fcm_id', $this->notificationId)
                ->where('customer_id', $this->customer->id)
                ->update(['status' => 'failed']);

            Log::warning('SendFcmPushJob failed', [
                'customer_id' => $this->customer->id,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
