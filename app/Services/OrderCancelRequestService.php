<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Category\Entities\Category;
use Modules\Payment\App\Filament\Resources\OrderResource;
use Modules\Payment\Entities\Order;

// Khách GỬI YÊU CẦU HUỶ đơn, admin duyệt hoặc từ chối (trước đây khách tự huỷ ngay, xem
// OrderCancellationService::cancelByCustomer()). Luồng:
//   request()  — khách gửi: đặt cancel_request_status='pending', báo admin (chuông + push) và xác nhận lại cho khách.
//   approve()  — admin duyệt: huỷ thật qua OrderCancellationService::cancelByCustomer() (cùng quy tắc hoàn
//                tiền/trả buổi gói/giải phóng khung giờ), rồi báo khách "được chấp nhận".
//   reject()   — admin từ chối: đơn giữ nguyên, báo khách kèm lý do. Khách được gửi lại yêu cầu mới.
// Mọi thông báo chỉ ghi log khi lỗi, không làm hỏng nghiệp vụ chính.
class OrderCancelRequestService
{
    public const PENDING  = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';

    public function __construct(
        private readonly OrderCancellationService $cancellation,
        private readonly OrderRealtimeService $realtime,
    ) {}

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function request(Order $order, ?string $reason = null): array
    {
        $result = DB::transaction(function () use ($order, $reason): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $locked->load('items');

            if ($locked->hasPendingCancelRequest()) {
                return ['ok' => false, 'error' => 'Yêu cầu huỷ của bạn đang chờ duyệt, vui lòng đợi phản hồi.'];
            }

            if (! $locked->canBeCancelledByCustomer()) {
                return ['ok' => false, 'error' => 'Đơn này không thể gửi yêu cầu huỷ (đã huỷ trước đó, chưa thanh toán, hoặc đã tới/qua giờ nhận sân).'];
            }

            $locked->update([
                'cancel_request_status'      => self::PENDING,
                'cancel_requested_at'        => now(),
                'cancel_request_reason'      => $reason,
                'cancel_request_reviewed_at' => null,
                'cancel_request_reviewed_by' => null,
                'cancel_request_note'        => null,
            ]);

            return ['ok' => true];
        });

        if (! $result['ok']) {
            return $result;
        }

        $order->refresh();

        $this->notifyAdmins($order);
        $this->notifyCustomer(
            $order,
            'Đã gửi yêu cầu huỷ đơn',
            "Yêu cầu huỷ đơn #{$order->order_code} của bạn đã được gửi và đang chờ duyệt. Chúng tôi sẽ phản hồi sớm nhất.",
            'cancel_request_sent',
        );
        $this->broadcast($order);

        Log::info('Order cancel requested by customer', ['order_id' => $order->id, 'order_code' => $order->order_code, 'reason' => $reason]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: true, refund_amount: int}|array{ok: false, error: string}
     */
    public function approve(Order $order, ?User $admin = null, ?string $note = null): array
    {
        $order->refresh();

        if (! $order->hasPendingCancelRequest()) {
            return ['ok' => false, 'error' => 'Đơn này không có yêu cầu huỷ nào đang chờ duyệt.'];
        }

        $result = $this->cancellation->cancelByCustomer($order, $order->cancel_request_reason);

        if (! $result['ok']) {
            return $result;
        }

        $order->refresh();
        $order->update([
            'cancel_request_status'      => self::APPROVED,
            'cancel_request_reviewed_at' => now(),
            'cancel_request_reviewed_by' => $admin?->id,
            'cancel_request_note'        => $note,
        ]);

        $refund = (int) $result['refund_amount'];
        $body   = "Yêu cầu huỷ đơn #{$order->order_code} đã được CHẤP NHẬN."
            . ($refund > 0 ? ' Số tiền hoàn: ' . number_format($refund, 0, ',', '.') . 'đ, chúng tôi sẽ chuyển khoản trong thời gian sớm nhất.' : '')
            . ($note ? " Ghi chú: {$note}" : '');

        $this->notifyCustomer($order, 'Yêu cầu huỷ đơn được chấp nhận', $body, 'cancel_request_approved');

        return ['ok' => true, 'refund_amount' => $refund];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function reject(Order $order, ?User $admin = null, ?string $note = null): array
    {
        $result = DB::transaction(function () use ($order, $admin, $note): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $locked->hasPendingCancelRequest()) {
                return ['ok' => false, 'error' => 'Đơn này không có yêu cầu huỷ nào đang chờ duyệt.'];
            }

            $locked->update([
                'cancel_request_status'      => self::REJECTED,
                'cancel_request_reviewed_at' => now(),
                'cancel_request_reviewed_by' => $admin?->id,
                'cancel_request_note'        => $note,
            ]);

            return ['ok' => true];
        });

        if (! $result['ok']) {
            return $result;
        }

        $order->refresh();

        $this->notifyCustomer(
            $order,
            'Yêu cầu huỷ đơn bị từ chối',
            "Yêu cầu huỷ đơn #{$order->order_code} KHÔNG được chấp nhận." . ($note ? " Lý do: {$note}" : '') . ' Đơn của bạn vẫn được giữ nguyên.',
            'cancel_request_rejected',
        );
        $this->broadcast($order);

        return ['ok' => true];
    }

    private function broadcast(Order $order): void
    {
        try {
            $this->realtime->broadcastOrderUpdate($order->order_code, [
                'status'                => $order->status,
                'cancel_request_status' => $order->cancel_request_status,
                'cancel_request_note'   => $order->cancel_request_note,
            ], $order->customer_id);
        } catch (\Throwable $e) {
            Log::warning('Cancel request broadcast failed: ' . $e->getMessage());
        }
    }

    private function notifyCustomer(Order $order, string $title, string $body, string $subtype): void
    {
        try {
            $extra = ['order_code' => (string) $order->order_code, 'type' => 'order', 'subtype' => $subtype];

            if ($order->customer_id && ($customer = Customer::find($order->customer_id))) {
                app(NotificationFcmService::class)->sendToCustomer($customer, $title, $body, 'booking', $extra);
            } elseif (! empty($order->device_token)) {
                app(NotificationFcmService::class)->sendToGuestToken($order->device_token, $title, $body, 'booking', $extra);
            }
        } catch (\Throwable $e) {
            Log::warning('Cancel request customer notification failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /** Chuông trong trang quản trị + push cho super_admin và admin chi nhánh của đơn. */
    private function notifyAdmins(Order $order): void
    {
        try {
            $superAdmins = User::role(config('filament-shield.super_admin.name'))->get();
            $users       = $superAdmins;

            if ($order->category_id !== null) {
                $matchIds = [$order->category_id];
                $cat      = Category::select('id', 'parent_id')->find($order->category_id);
                if ($cat?->parent_id) {
                    $matchIds[] = $cat->parent_id;
                }

                $regional = User::whereDoesntHave('roles', fn ($q) => $q->where('name', config('filament-shield.super_admin.name')))
                    ->whereHas('branchPermissions', fn ($q) => $q->whereIn('category_id', $matchIds))
                    ->get();

                $users = $superAdmins->merge($regional)->unique('id');
            }

            if ($users->isEmpty()) {
                return;
            }

            $title = 'Khách xin huỷ đơn';
            $body  = "#{$order->order_code} · {$order->buyer_name}" . ($order->cancel_request_reason ? " · Lý do: {$order->cancel_request_reason}" : '');

            Notification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-exclamation-triangle')
                ->warning()
                ->actions([
                    Action::make('view')
                        ->label('Xem & duyệt')
                        ->url(OrderResource::getUrl('edit', ['record' => $order->id]))
                        ->button(),
                ])
                ->sendToDatabase($users);

            try {
                app(FcmService::class)->sendToUsers($users, $title, $body);
            } catch (\Throwable $e) {
                Log::warning('FCM push (cancel request) failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
