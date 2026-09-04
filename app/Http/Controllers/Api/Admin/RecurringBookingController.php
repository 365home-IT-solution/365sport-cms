<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Payment\Entities\Order;

/**
 * Đặt sân ĐỊNH KỲ hàng tuần (khách quen — đặt CÙNG 1 sân + CÙNG khung giờ, lặp lại N tuần liên tiếp
 * trong 1 thao tác thay vì phải đặt từng tuần riêng lẻ). CHỈ hỗ trợ type='slot' (đặt theo khung giờ)
 * — đặt định kỳ theo NGÀY (daily)/THÁNG (monthly) không có khái niệm "lặp lại hàng tuần cùng 1 khung
 * giờ" nên không áp dụng ở đây.
 *
 * QUYẾT ĐỊNH THIẾT KẾ (xem thêm trong PR/báo cáo audit): mỗi tuần vẫn là 1 Order RIÊNG BIỆT, tạo
 * bằng cách gọi LẠI NGUYÊN VẸN BookingController::store() (không copy logic tính giá/validate
 * slot trùng — engine đó nằm ở BuildsRoomBooking, dùng chung cho mọi luồng tạo đơn trong hệ thống).
 * KHÔNG dồn N tuần vào chung 1 Order/nhiều OrderItem, vì toàn bộ hệ thống hiện tại (thanh toán, huỷ,
 * check-in/check-out, mở khoá cổng, đối soát...) đều giả định 1 Order = 1 lần nhận-trả sân + 1 lần
 * thanh toán + 1 order_code — nhồi nhiều tuần vào 1 đơn sẽ phá vỡ hàng loạt chỗ đó (vd Order::items()
 * chỉ có 1 checkin/checkout đại diện ở nhiều nơi hiển thị, OrderPaymentController tính tiền theo
 * đúng 1 đơn...). Tách 1 Order/tuần giữ nguyên mọi bất biến đó, đổi lại là KHÔNG có transaction bao
 * trùm cả loạt — tuần 3 bị trùng khung giờ (người khác vừa đặt) KHÔNG rollback tuần 1-2 đã tạo thành
 * công, mà chỉ báo lỗi cho đúng tuần đó trong 'failed' — đây là hành vi ĐÚNG mong muốn theo yêu cầu
 * (không mất các tuần đã đặt được chỉ vì 1 tuần sau đó bị đụng lịch).
 *
 * Các Order trong 1 loạt được NỐI lại bằng recurring_group_id (UUID sinh mới cho mỗi lần gọi
 * store() ở đây) — KHÔNG có bảng riêng cho "loạt đặt định kỳ", Order vẫn là nguồn sự thật duy nhất
 * (xem migration add_recurring_group_to_orders_table).
 *
 * KHÔNG hỗ trợ upload CCCD (cccd_front/back, guests[]) qua endpoint này — khách quen định kỳ thường
 * đã có hồ sơ/CCCD lưu sẵn (customer_id), nếu cần bổ sung CCCD cho từng đơn thì sửa tay từng đơn qua
 * PUT /api/admin/orders/{order_code} sau khi loạt đã tạo xong. Giữ endpoint này đơn giản, tránh phải
 * nhân bản N lần cùng 1 ảnh CCCD một cách vô nghĩa.
 */
class RecurringBookingController extends OrderController
{
    // Giới hạn số tuần tối đa 1 lần gọi — chặn lạm dụng (spam tạo hàng trăm đơn 1 lúc); 26 tuần ~
    // nửa năm, đủ cho nhu cầu thực tế (đặt sân cố định theo mùa giải/học kỳ).
    private const MAX_WEEKS = 26;

    /**
     * POST /api/admin/recurring-bookings
     * Tạo 1 loạt đơn đặt sân định kỳ — body GIỐNG HỆT POST /api/admin/orders (type='slot', xem
     * docblock BookingController::store()) NGOẠI TRỪ:
     *   - KHÔNG nhận 'date'/'slots.*.date' — ngày của MỖI tuần được tự tính từ 'start_date' + 7 *
     *     (số tuần đã qua), tất cả slot trong 1 tuần dùng CHUNG đúng 1 ngày đó.
     *   - Thêm 'start_date' (bắt buộc, ngày của TUẦN ĐẦU TIÊN, >= hôm nay) và 'weeks' (bắt buộc, số
     *     tuần muốn lặp, tối đa 26).
     *
     * Mỗi tuần gọi LẠI đúng BookingController::store() (xem docblock class) — 1 tuần lỗi (vd
     * khung giờ đã bị người khác đặt) KHÔNG làm hỏng các tuần khác, chỉ được liệt kê riêng trong
     * 'failed' kèm lý do; các tuần thành công vẫn giữ nguyên trong 'created'.
     *
     * Response: {recurring_group_id, weeks_requested, created: [{occurrence, date, order_code}],
     *            failed: [{occurrence, date, reason}]}
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'room_id'                => 'required|string',
            'slots'                  => 'required|array|min:1',
            'slots.*.timeslot_id'    => 'required|integer',
            'start_date'             => 'required|date_format:Y-m-d|after_or_equal:today',
            'weeks'                  => 'required|integer|min:1|max:' . self::MAX_WEEKS,
            'guest_count'            => 'required|integer|min:1',
            'customer_id'            => 'sometimes|nullable|string|size:36|exists:customers,id',
            'customer_phone'         => 'sometimes|nullable|string|max:20',
            'buyer_name'             => 'sometimes|nullable|string|max:100',
            'buyer_phone'            => 'sometimes|nullable|string|max:20',
            'payment_method'         => 'sometimes|in:PayOS,cod',
            'payment_type'           => 'sometimes|in:full,deposit',
            'status'                 => 'sometimes|in:pending,paid,deposit,failed,cancelled_payment,refunded',
            'services'               => 'sometimes|nullable|array',
            'services.*.service_id'  => 'required_with:services|integer',
            'services.*.quantity'    => 'required_with:services|integer|min:1',
            'note_for_admin'         => 'sometimes|nullable|string|max:500',
            'description'            => 'sometimes|nullable|string|max:500',
            'short_description'      => 'sometimes|nullable|string|max:255',
        ]);

        $weeks           = (int) $request->input('weeks');
        $startDate       = Carbon::parse($request->input('start_date'));
        $recurringGroupId = (string) Str::uuid();

        // Payload dùng chung cho mọi tuần — bỏ 'start_date'/'weeks' (không thuộc payload của
        // BookingController::store()), ép type='slot' (duy nhất hỗ trợ), và bỏ mọi 'date' gửi
        // kèm theo từng slot (nếu có) vì ngày của loạt định kỳ LUÔN do start_date + 7*tuần quyết
        // định, không cho slot tự chọn ngày riêng khác tuần.
        $baseData         = $request->except(['start_date', 'weeks']);
        $baseData['type'] = 'slot';
        $baseData['slots'] = collect($request->input('slots'))
            ->map(fn ($slot) => ['timeslot_id' => (int) $slot['timeslot_id']])
            ->all();

        $bookingController = app(BookingController::class);

        $created = [];
        $failed  = [];

        for ($i = 0; $i < $weeks; $i++) {
            $occurrenceNumber = $i + 1;
            $occurrenceDate   = $startDate->copy()->addWeeks($i)->format('Y-m-d');

            $weekData         = $baseData;
            $weekData['date'] = $occurrenceDate;

            // Request riêng cho từng tuần (không tái dùng $request gốc) — BookingController::
            // store() tự validate/đọc input trên chính request được truyền vào, dựng request mới
            // tránh lẫn 'date' của tuần trước sang tuần sau. setUserResolver() để $request->user()
            // bên trong store() nhận đúng admin đang gọi (request tự tạo không tự có, khác request
            // thật đi qua middleware auth:sanctum).
            $weekRequest = Request::create('/api/admin/orders', 'POST', $weekData);
            $weekRequest->setUserResolver($request->getUserResolver());

            try {
                $response = $bookingController->store($weekRequest);
                $payload  = json_decode($response->getContent(), true) ?? [];

                if ($response->getStatusCode() >= 400) {
                    $failed[] = [
                        'occurrence' => $occurrenceNumber,
                        'date'       => $occurrenceDate,
                        'reason'     => $payload['message'] ?? 'Could not create this occurrence.',
                    ];
                    continue;
                }

                $orderId = $payload['order']['id'] ?? null;
                if ($orderId) {
                    Order::where('id', $orderId)->update([
                        'recurring_group_id'           => $recurringGroupId,
                        'recurring_occurrence_number'  => $occurrenceNumber,
                    ]);
                }

                $created[] = [
                    'occurrence'  => $occurrenceNumber,
                    'date'        => $occurrenceDate,
                    'order_code'  => $payload['order']['order_code'] ?? null,
                ];
            } catch (ValidationException $e) {
                $failed[] = [
                    'occurrence' => $occurrenceNumber,
                    'date'       => $occurrenceDate,
                    'reason'     => collect($e->errors())->flatten()->first() ?? 'Validation failed for this occurrence.',
                ];
            } catch (\Throwable $e) {
                Log::error('Recurring booking: failed to create one occurrence', [
                    'occurrence' => $occurrenceNumber,
                    'date'       => $occurrenceDate,
                    'error'      => $e->getMessage(),
                ]);
                $failed[] = [
                    'occurrence' => $occurrenceNumber,
                    'date'       => $occurrenceDate,
                    'reason'     => 'Unexpected error while creating this occurrence.',
                ];
            }
        }

        return response()->json([
            'recurring_group_id' => $recurringGroupId,
            'weeks_requested'    => $weeks,
            'created'            => $created,
            'failed'             => $failed,
        ], empty($created) ? 422 : 201);
    }

    /**
     * GET /api/admin/recurring-bookings/{recurring_group_id}
     * Danh sách toàn bộ đơn thuộc 1 loạt đặt định kỳ, sắp theo đúng thứ tự tuần (recurring_occurrence_
     * number) — dùng SHAPE JSON giống hệt từng phần tử của GET /api/admin/orders (xem
     * OrderController::toListItem()) để FE tái dùng lại đúng component hiển thị danh sách đơn, không
     * cần format riêng. Cùng phạm vi truy cập theo đối tác với OrderController::index() — 404 nếu
     * không tìm thấy đơn nào thuộc loạt này trong phạm vi đối tác của tài khoản đang gọi.
     */
    public function show(Request $request, string $recurringGroupId): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $query = Order::query()
            ->with([
                'items.product:id,name,styles',
                'items.product.media',
                'category:id,name',
                'customer:id,fullname,phone',
            ])
            ->where('recurring_group_id', $recurringGroupId);

        if (! $admin->isSuperAdmin()) {
            if (empty($admin->partner_id)) {
                return response()->json(['message' => 'Recurring booking group not found.'], 404);
            }
            $query->where('partner_id', $admin->partner_id);
        }

        $orders = $query->orderBy('recurring_occurrence_number')->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Recurring booking group not found.'], 404);
        }

        return response()->json([
            'recurring_group_id' => $recurringGroupId,
            'orders'             => $orders->map(fn (Order $order) => $this->toListItem($order))->values(),
        ]);
    }

    /**
     * DELETE /api/admin/recurring-bookings/{recurring_group_id}
     * Huỷ (xoá hẳn) MỌI đơn CHƯA DIỄN RA (khung giờ sớm nhất của đơn vẫn còn trong tương lai) thuộc
     * 1 loạt đặt định kỳ — dùng LẠI đúng logic xoá 1 đơn của OrderController::deleteOrderWithRelations()
     * (dọn order_items/order_services/order_guest_cccds + file CCCD trong storage) cho từng đơn, KHÔNG
     * viết lại. Đơn ĐÃ QUA (checkin_date sớm nhất đã ở quá khứ — sân đã/đang được dùng cho tuần đó)
     * được GIỮ NGUYYÊN, không đụng tới, dù cùng thuộc loạt này.
     *
     * Response: {recurring_group_id, deleted: [order_code...], skipped_past_occurrences: [order_code...]}
     */
    public function destroy(Request $request, string $recurringGroupId): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $query = Order::with(['items', 'services', 'guestCccds'])
            ->where('recurring_group_id', $recurringGroupId);

        if (! $admin->isSuperAdmin()) {
            if (empty($admin->partner_id)) {
                return response()->json(['message' => 'Recurring booking group not found.'], 404);
            }
            $query->where('partner_id', $admin->partner_id);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            return response()->json(['message' => 'Recurring booking group not found.'], 404);
        }

        $deleted     = [];
        $skippedPast = [];

        foreach ($orders as $order) {
            $earliestCheckin = $order->items->pluck('checkin_date')->filter()->min();

            if ($earliestCheckin && Carbon::parse($earliestCheckin)->isPast()) {
                $skippedPast[] = $order->order_code;
                continue;
            }

            $orderCode = $order->order_code;
            $this->deleteOrderWithRelations($order);
            $deleted[] = $orderCode;
        }

        return response()->json([
            'recurring_group_id'       => $recurringGroupId,
            'deleted'                  => $deleted,
            'skipped_past_occurrences' => $skippedPast,
        ]);
    }
}
