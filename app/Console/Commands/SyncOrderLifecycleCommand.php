<?php

namespace App\Console\Commands;

use App\Services\OrderRealtimeService;
use Illuminate\Console\Command;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;
use Modules\Warehouse\App\Services\WarehouseRentalService;

/**
 * Safety-net: tự động trả sân (order_status = checked_out) khi khách quá giờ
 * checkout mà nhân viên chưa cập nhật trạng thái trả sân.
 */
class SyncOrderLifecycleCommand extends Command
{
    protected $signature   = 'orders:sync-lifecycle';
    protected $description = 'Tự động chuyển order_status sang checked_out khi quá giờ checkout mà khách chưa tự trả sân';

    public function handle(OrderRealtimeService $realtime, WarehouseRentalService $rentals): int
    {
        $overdueOrderIds = OrderItem::where('extra_fee', 0)
            ->whereNotNull('checkout_date')
            ->where('checkout_date', '<', now())
            ->whereHas('order', fn ($q) => $q->where('order_status', 'staying'))
            ->pluck('order_id')
            ->unique();

        $orders = Order::whereIn('id', $overdueOrderIds)
            ->where('order_status', 'staying')
            ->get();

        foreach ($orders as $order) {
            $order->update(['order_status' => 'checked_out', 'checked_out_at' => now()]);

            // Hết giờ chơi — coi như buổi thuê dụng cụ (nếu có) đã kết thúc, tự trả lại kho. Nhân
            // viên vẫn có thể tự tay báo "mất" sau đó nếu khách không trả thật (xem
            // WarehouseItemRentalResource).
            $rentals->releaseForOrder($order);

            $realtime->broadcastOrderUpdate(
                (string) $order->order_code,
                ['order_status' => $order->order_status, 'payment_status' => $order->payment_status],
                $order->customer_id,
            );
        }

        $this->info("Đã tự động trả sân {$orders->count()} đơn quá giờ checkout.");

        return self::SUCCESS;
    }
}
