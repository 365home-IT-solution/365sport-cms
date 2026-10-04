<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Entities\Order;
use Modules\Warehouse\App\Services\WarehouseRentalService;

class OrderServiceController extends Controller
{
    /**
     * POST /api/orders/{order}/services
     *
     * Thêm / cập nhật dịch vụ bổ sung cho đơn hàng.
     * Chỉ áp dụng khi đơn ở trạng thái pending.
     */
    public function store(Request $request, Order $order): JsonResponse
    {
        /** @var \App\Models\Customer $customer */
        $customer = auth('sanctum')->user();

        if ((string) $order->customer_id !== (string) $customer->id) {
            return response()->json(['message' => 'Bạn không có quyền chỉnh sửa đơn hàng này.'], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Chỉ có thể thêm dịch vụ khi đơn đang ở trạng thái pending.'], 422);
        }

        $request->validate([
            'services'                => 'required|array|min:1',
            'services.*.service_id'   => 'required|integer',
            'services.*.quantity'     => 'required|integer|min:1',
        ]);

        // Load sân từ order item đầu tiên
        $productId = $order->items->first()?->product_id;
        if (! $productId) {
            return response()->json(['message' => 'Đơn hàng không có sân.'], 422);
        }

        $room = \Modules\Product\App\Models\Product::where('id', $productId)
            ->with('additionalServices')
            ->first();

        if (! $room) {
            return response()->json(['message' => 'Sân không tồn tại.'], 404);
        }

        $availableServices = $room->additionalServices->keyBy('id');
        $servicesData      = [];
        $addedTotal        = 0;

        foreach ($request->input('services') as $index => $entry) {
            $serviceId = (int) $entry['service_id'];
            $quantity  = (int) $entry['quantity'];
            $service   = $availableServices->get($serviceId);

            if (! $service || ! $service->is_active) {
                throw ValidationException::withMessages([
                    "services.{$index}.service_id" => ["Dịch vụ #{$serviceId} không tồn tại hoặc không khả dụng cho sân này."],
                ]);
            }

            $subtotal      = $service->price * $quantity;
            $addedTotal   += $subtotal;
            $servicesData[] = [
                'service_id'   => $service->id,
                'service_name' => $service->name,
                'price'        => (int) $service->price,
                'quantity'     => $quantity,
                'subtotal'     => (int) $subtotal,
            ];
        }

        // Toàn bộ replace (trả dụng cụ cũ → xoá → tạo lại → mượn dụng cụ mới) phải ATOMIC — nếu 1
        // dịch vụ mới hết tồn kho giữa chừng (WarehouseRentalService::rent() ném
        // ValidationException), phải rollback lại đúng danh sách CŨ, không để đơn hàng dở dang
        // (mất services cũ nhưng chưa có đủ services mới).
        DB::transaction(function () use ($order, $servicesData) {
            app(WarehouseRentalService::class)->releaseForOrder($order);

            $order->services()->delete();

            foreach ($servicesData as $svc) {
                $order->services()->create($svc);
            }
        });

        // Cập nhật lại amount (giá gốc) và full_amount (giá thực tế sau giảm giá)
        $roomPrice   = (int) $order->items->sum('price');
        $newSubtotal = $roomPrice + $addedTotal;
        $discount    = max(0, (int) $order->amount - (int) $order->full_amount);
        $newFinal    = max(0, $newSubtotal - $discount);

        $order->update([
            'amount'      => $newSubtotal,
            'full_amount' => $newFinal,
        ]);

        $order->refresh();

        return response()->json([
            'order_code'      => $order->order_code,
            'subtotal'        => (int) $order->amount,
            'discount_amount' => $discount,
            'final_amount'    => (int) $order->full_amount,
            'services'        => $servicesData,
        ]);
    }
}
