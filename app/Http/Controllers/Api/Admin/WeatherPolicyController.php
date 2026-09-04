<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WeatherPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Entities\Order;

/**
 * Hoãn/hoàn tiền do THỜI TIẾT cho đơn đặt sân NGOÀI TRỜI — tách RIÊNG khỏi OrderController/
 * OrderPaymentController (cùng tiền lệ tách OrderPaymentController ra khỏi OrderController: 1
 * nhóm nghiệp vụ độc lập, không dùng chung state/helper nào với 2 controller kia ngoài việc cùng
 * thao tác trên Order) — dễ tìm, dễ mở rộng thêm chính sách thời tiết sau này mà không phình thêm
 * OrderController vốn đã rất lớn.
 *
 * Toàn bộ logic nghiệp vụ nằm ở App\Services\WeatherPolicyService — controller chỉ validate input +
 * tra quyền truy cập đơn (đúng partner_id, trừ super_admin) rồi map kết quả service trả về thành
 * response, giống 100% khuôn mẫu OrderPaymentController.
 */
class WeatherPolicyController extends Controller
{
    /**
     * POST /api/admin/orders/{order_code}/weather-refund
     * Hoàn tiền TOÀN BỘ cho đơn sân ngoài trời bị hoãn do mưa — không giữ chỗ nữa. Chỉ ghi nhận
     * "chờ hoàn tiền" (cùng cơ chế panel "Hoàn tiền chưa xử lý" đã có) — admin xử lý chuyển khoản/
     * tiền mặt thật ngoài hệ thống rồi xác nhận qua ExtraChargeService::markRefundAsDone().
     */
    public function refund(Request $request, string $orderCode, WeatherPolicyService $service): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $order = Order::with('items.product.roomType')->where('order_code', $orderCode)->first();

        if (! $order || (! $admin->isSuperAdmin() && $order->partner_id !== $admin->partner_id)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $result = $service->refund($order, $admin);

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json([
            'message'       => 'Weather refund recorded successfully. The refund amount is now pending processing.',
            'order_code'    => $order->order_code,
            'refund_amount' => $result['refund_amount'],
        ]);
    }

    /**
     * POST /api/admin/orders/{order_code}/weather-reschedule
     * Dời khung giờ/ngày của đơn sang thời điểm mới do mưa — không phát sinh thu thêm/hoàn tiền,
     * chỉ đổi lịch. Yêu cầu khung giờ mới phải còn trống (kiểm tra trùng lịch giống lúc tạo đơn).
     *
     * Body:
     *  - new_checkin_date  : bắt buộc, ISO datetime/'Y-m-d H:i:s'
     *  - new_checkout_date : bắt buộc, phải sau new_checkin_date
     */
    public function reschedule(Request $request, string $orderCode, WeatherPolicyService $service): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $request->validate([
            'new_checkin_date'  => 'required|date',
            'new_checkout_date' => 'required|date|after:new_checkin_date',
        ]);

        $order = Order::with('items.product.roomType')->where('order_code', $orderCode)->first();

        if (! $order || (! $admin->isSuperAdmin() && $order->partner_id !== $admin->partner_id)) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $result = $service->reschedule(
            $order,
            $request->input('new_checkin_date'),
            $request->input('new_checkout_date'),
            $admin,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $order->refresh()->load('items');

        return response()->json([
            'message'    => 'The booking has been rescheduled due to weather.',
            'order_code' => $order->order_code,
            'checkin_date'  => $order->items->min('checkin_date'),
            'checkout_date' => $order->items->max('checkout_date'),
        ]);
    }
}
