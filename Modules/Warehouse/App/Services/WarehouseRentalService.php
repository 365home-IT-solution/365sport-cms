<?php

declare(strict_types=1);

namespace Modules\Warehouse\App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BladeThemeV1\App\Models\AdditionService;
use Modules\Payment\App\Models\OrderService;
use Modules\Payment\Entities\Order;
use Modules\Warehouse\App\Models\WarehouseItem;
use Modules\Warehouse\App\Models\WarehouseItemRental;

// Mượn/trả dụng cụ thể thao gắn kèm 1 order_service (khác hẳn xuất kho tiêu hao thường —
// WarehouseItem::quantity_in_use tăng khi mượn, giảm lại khi trả, KHÔNG trừ vĩnh viễn 'quantity').
class WarehouseRentalService
{
    // Gọi khi 1 order_service mới được tạo — nếu dịch vụ đó gắn 1 warehouse_item (thuê dụng cụ),
    // khoá dòng WarehouseItem để tránh 2 khách cùng lúc thuê vượt quá tồn khả dụng, rồi tăng
    // quantity_in_use + ghi 1 dòng rental. Không đủ tồn khả dụng thì chặn luôn, không tạo được
    // order_service.
    public function rent(OrderService $orderService): void
    {
        $additionService = AdditionService::find($orderService->service_id);
        if (! $additionService || blank($additionService->warehouse_item_id)) {
            return;
        }

        DB::transaction(function () use ($orderService, $additionService) {
            /** @var WarehouseItem $item */
            $item = WarehouseItem::whereKey($additionService->warehouse_item_id)->lockForUpdate()->first();

            if (! $item) {
                return;
            }

            $requested = (float) $orderService->quantity;
            $available = $item->quantity_reserve;

            if ($requested > $available) {
                throw ValidationException::withMessages([
                    'services' => ["Not enough available stock for \"{$item->name}\" ({$available} left, {$requested} requested)."],
                ]);
            }

            WarehouseItem::whereKey($item->id)->increment('quantity_in_use', $requested);

            WarehouseItemRental::create([
                'order_service_id'  => $orderService->id,
                'warehouse_item_id' => $item->id,
                'quantity'          => $requested,
                'rented_at'         => now(),
                'status'            => WarehouseItemRental::STATUS_RENTED,
                'created_by'        => auth()->id(),
            ]);
        });
    }

    // Trả lại dụng cụ cho 1 order_service (huỷ dịch vụ trước khi thanh toán, hoặc kết thúc buổi
    // chơi) — giảm lại quantity_in_use đúng bằng số đã mượn, đánh dấu rental đã trả.
    public function releaseForOrderService(OrderService $orderService, string $status = WarehouseItemRental::STATUS_RETURNED): void
    {
        $rentals = WarehouseItemRental::where('order_service_id', $orderService->id)
            ->where('status', WarehouseItemRental::STATUS_RENTED)
            ->get();

        foreach ($rentals as $rental) {
            $this->releaseRental($rental, $status);
        }
    }

    // Trả lại TOÀN BỘ dụng cụ đang mượn của 1 đơn hàng — gọi khi đơn bị huỷ/thất bại/hoàn tiền,
    // hoặc khi đơn bị xoá hẳn (trước khi order_services bị xoá theo).
    public function releaseForOrder(Order $order, string $status = WarehouseItemRental::STATUS_RETURNED): void
    {
        $serviceIds = $order->services()->pluck('id');
        if ($serviceIds->isEmpty()) {
            return;
        }

        $rentals = WarehouseItemRental::whereIn('order_service_id', $serviceIds)
            ->where('status', WarehouseItemRental::STATUS_RENTED)
            ->get();

        foreach ($rentals as $rental) {
            $this->releaseRental($rental, $status);
        }
    }

    private function releaseRental(WarehouseItemRental $rental, string $status): void
    {
        DB::transaction(function () use ($rental, $status) {
            // Khoá + chốt trạng thái NGAY trong 1 điều kiện WHERE — chặn 2 lệnh trả cùng lúc (hoặc
            // job tự động chạy trùng lịch) cùng trừ quantity_in_use 2 lần cho 1 lượt mượn.
            $updated = WarehouseItemRental::whereKey($rental->id)
                ->where('status', WarehouseItemRental::STATUS_RENTED)
                ->update(['status' => $status, 'returned_at' => now()]);

            if ($updated === 0) {
                return;
            }

            // 'lost' (báo mất) KHÔNG trả lại quantity_in_use — dụng cụ coi như vẫn "đang ngoài kho"
            // cho tới khi admin tự điều chỉnh giảm 'quantity' thật qua trang sửa vật tư.
            if ($status !== WarehouseItemRental::STATUS_LOST) {
                WarehouseItem::whereKey($rental->warehouse_item_id)
                    ->update(['quantity_in_use' => DB::raw('GREATEST(quantity_in_use - ' . (float) $rental->quantity . ', 0)')]);
            }
        });
    }
}
