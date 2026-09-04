<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Promotion\App\Models\Coupon;

/**
 * CRUD Mã giảm giá (Coupon) — bảng `coupons`, cùng model/logic nghiệp vụ với
 * Modules\Coupon\App\Filament\Resources\CouponResource (form CouponForm, bảng CouponTable). API
 * này chỉ thao tác trên chính bản ghi Coupon (tạo/sửa/xoá/liệt kê) — KHÔNG đụng tới gán/gỡ Promotion
 * cho khung giờ sân (đó là RoomPromotionController, dùng model Promotion khác — Modules\Promotion\
 * App\Models\Promotion).
 *
 * Phân quyền: Coupon::BelongsToPartner chỉ tự lọc theo partner_id BÊN TRONG Filament panel (global
 * scope kiểm tra AdminPanelContext::isActive()) — route API KHÔNG đi qua panel đó nên phải lọc thủ
 * công theo partner_id của user đăng nhập (super_admin xem hết), cùng nguyên tắc với
 * RoomPromotionController::visiblePromotion()/WarehouseItemController.
 *
 * Giả định nghiệp vụ (cần double-check với PO):
 *  - `code` chỉ cần DUY NHẤT TRÊN TOÀN HỆ THỐNG (Rule::unique('coupons','code') không lọc thêm theo
 *    partner_id) — giống CouponForm::form() ở Filament (->unique(ignoreRecord: true) không có
 *    ->where partner_id). Nếu nghiệp vụ muốn "duy nhất theo từng đối tác" thay vì toàn cục, sửa lại
 *    rule unique thêm ->where('partner_id', ...).
 *  - `room_id` (bắt buộc khi apply_type=specific_room) không bị ép buộc phải thuộc đúng partner của
 *    user (Filament form cũng không chặn) — chỉ kiểm tra tồn tại (exists:products,id).
 */
class CouponController extends Controller
{
    /**
     * GET /api/admin/coupons
     * Query params:
     *  - search   : tìm theo code/name
     *  - status   : 1|0|true|false — lọc theo is_active
     *  - per_page : mặc định 20
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user  = $request->user();
        $query = $this->visibleCouponsQuery($user)->with(['room', 'creator']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $coupons = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($coupons->items())->map(fn (Coupon $c) => $this->toItem($c))->values(),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page'    => $coupons->lastPage(),
                'total'        => $coupons->total(),
                'per_page'     => $coupons->perPage(),
            ],
        ]);
    }

    /** GET /api/admin/coupons/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $coupon = $this->visibleCouponsQuery($request->user())
            ->with(['room', 'creator', 'roomTimeSlots.timeSlot'])
            ->find($id);

        if (! $coupon) {
            return response()->json(['message' => 'Coupon not found.'], 404);
        }

        return response()->json(['data' => $this->toItem($coupon, withSlots: true)]);
    }

    /**
     * POST /api/admin/coupons
     * Body — xem CouponForm::form():
     *  - code (required, duy nhất), name (required)
     *  - description (nullable)
     *  - type: percentage|fixed (required)
     *  - value (required, numeric, 0-100 nếu type=percentage)
     *  - max_discount (nullable — chỉ có ý nghĩa khi type=percentage)
     *  - min_order_value (nullable)
     *  - apply_type: all_rooms|specific_room|specific_slot (required)
     *  - room_id (required nếu apply_type=specific_room|specific_slot)
     *  - room_time_slot_ids[] (required nếu apply_type=specific_slot, phải thuộc room_id)
     *  - usage_limit (nullable)
     *  - start_at (required), end_at (nullable, sau start_at)
     *  - is_active (nullable, mặc định true)
     *  - customer_id (nullable — coupon cá nhân hoá, gán riêng cho 1 khách hàng)
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->validateData($request);

        if (! empty($data['room_time_slot_ids'])) {
            $this->assertSlotsBelongToRoom($data['room_time_slot_ids'], $data['room_id']);
        }

        $coupon = Coupon::create([
            'partner_id'      => $user->isSuperAdmin() ? null : $user->partner_id,
            'code'            => $data['code'],
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'type'            => $data['type'],
            'value'           => $data['value'],
            'apply_type'      => $data['apply_type'],
            'room_id'         => in_array($data['apply_type'], ['specific_room', 'specific_slot'], true) ? $data['room_id'] : null,
            'min_order_value' => $data['min_order_value'] ?? null,
            'max_discount'    => $data['max_discount'] ?? null,
            'usage_limit'     => $data['usage_limit'] ?? null,
            'used_count'      => 0,
            'start_at'        => $data['start_at'],
            'end_at'          => $data['end_at'] ?? null,
            'is_active'       => array_key_exists('is_active', $data) ? $data['is_active'] : true,
            'created_by'      => $user->id,
            'customer_id'     => $data['customer_id'] ?? null,
        ]);

        if (! empty($data['room_time_slot_ids'])) {
            $coupon->roomTimeSlots()->sync($data['room_time_slot_ids']);
        }

        return response()->json(['data' => $this->toItem($coupon->fresh(['room', 'creator']), withSlots: true)], 201);
    }

    /** PUT|PATCH /api/admin/coupons/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user   = $request->user();
        $coupon = $this->visibleCouponsQuery($user)->find($id);

        if (! $coupon) {
            return response()->json(['message' => 'Coupon not found.'], 404);
        }

        $data = $this->validateData($request, forUpdate: true, ignoreId: $coupon->id);

        $applyType = $data['apply_type'] ?? $coupon->apply_type;

        if (array_key_exists('room_time_slot_ids', $data) && ! empty($data['room_time_slot_ids'])) {
            $roomId = $data['room_id'] ?? $coupon->room_id;
            $this->assertSlotsBelongToRoom($data['room_time_slot_ids'], $roomId);
        }

        if (array_key_exists('apply_type', $data)) {
            $data['room_id'] = in_array($applyType, ['specific_room', 'specific_slot'], true)
                ? ($data['room_id'] ?? $coupon->room_id)
                : null;
        }

        if (array_key_exists('status', $data)) {
            $data['is_active'] = $data['status'];
            unset($data['status']);
        }

        $roomTimeSlotIds = $data['room_time_slot_ids'] ?? null;
        unset($data['room_time_slot_ids']);

        $coupon->update($data);

        if ($roomTimeSlotIds !== null) {
            $coupon->roomTimeSlots()->sync($roomTimeSlotIds);
        } elseif ($applyType !== 'specific_slot') {
            $coupon->roomTimeSlots()->sync([]);
        }

        return response()->json(['data' => $this->toItem($coupon->fresh(['room', 'creator']), withSlots: true)]);
    }

    /**
     * DELETE /api/admin/coupons/{id}
     * Chặn xoá nếu đã có lượt sử dụng (used_count > 0) — tránh mất dữ liệu lịch sử của đơn đã dùng
     * mã này. Trang Filament hiện tại (EditCoupon) không có chặn này — API cố tình chặt chẽ hơn.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $coupon = $this->visibleCouponsQuery($request->user())->find($id);

        if (! $coupon) {
            return response()->json(['message' => 'Coupon not found.'], 404);
        }

        if ($coupon->used_count > 0) {
            return response()->json(['message' => 'This coupon has already been used and cannot be deleted.'], 422);
        }

        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted successfully.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validateData(Request $request, bool $forUpdate = false, ?int $ignoreId = null): array
    {
        $req = $forUpdate ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'code'               => [$forUpdate ? 'sometimes' : 'required', 'string', 'max:50', 'alpha_num', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'name'               => [...$req, 'string', 'max:255'],
            'description'        => 'nullable|string|max:500',
            'type'               => [$forUpdate ? 'sometimes' : 'required', 'in:percentage,fixed'],
            'value'              => [$forUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'max_discount'       => 'nullable|numeric|min:0',
            'min_order_value'    => 'nullable|numeric|min:0',
            'apply_type'         => [$forUpdate ? 'sometimes' : 'required', 'in:all_rooms,specific_room,specific_slot'],
            'room_id'            => [Rule::requiredIf(fn () => in_array($request->input('apply_type'), ['specific_room', 'specific_slot'], true)), 'nullable', 'string', 'exists:products,id'],
            'room_time_slot_ids'   => 'nullable|array',
            'room_time_slot_ids.*' => 'integer|exists:room_time_slots,id',
            'usage_limit'        => 'nullable|integer|min:1',
            'start_at'           => [$forUpdate ? 'sometimes' : 'required', 'date'],
            'end_at'             => 'nullable|date|after:start_at',
            'is_active'          => 'sometimes|boolean',
            'status'             => 'sometimes|boolean',
            'customer_id'        => 'nullable|integer|exists:customers,id',
        ]);
    }

    private function assertSlotsBelongToRoom(array $slotIds, ?string $roomId): void
    {
        $count = RoomTimeSlot::whereIn('id', $slotIds)->where('room_id', $roomId)->count();

        if ($count !== count(array_unique($slotIds))) {
            abort(response()->json(['message' => 'room_time_slot_ids must belong to the selected room_id.'], 422));
        }
    }

    // Coupon::BelongsToPartner chỉ tự lọc theo partner_id BÊN TRONG Filament panel — API route
    // không đi qua panel đó nên phải lọc thủ công (cùng nguyên tắc RoomPromotionController).
    private function visibleCouponsQuery(User $user): Builder
    {
        $query = Coupon::query();

        if (! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }

        return $query;
    }

    private function toItem(Coupon $c, bool $withSlots = false): array
    {
        $item = [
            'id'              => $c->id,
            'partner_id'      => $c->partner_id,
            'code'            => $c->code,
            'name'            => $c->name,
            'description'     => $c->description,
            'type'            => $c->type,
            'value'           => (float) $c->value,
            'apply_type'      => $c->apply_type,
            'room_id'         => $c->room_id,
            'room_name'       => $c->room?->name,
            'min_order_value' => $c->min_order_value !== null ? (float) $c->min_order_value : null,
            'max_discount'    => $c->max_discount !== null ? (float) $c->max_discount : null,
            'usage_limit'     => $c->usage_limit,
            'used_count'      => $c->used_count,
            'start_at'        => $c->start_at,
            'end_at'          => $c->end_at,
            'is_active'       => $c->is_active,
            'customer_id'     => $c->customer_id,
            'created_by'      => $c->created_by,
            'creator_name'    => $c->creator?->name,
            'created_at'      => $c->created_at,
            'updated_at'      => $c->updated_at,
        ];

        if ($withSlots) {
            $item['room_time_slots'] = $c->roomTimeSlots->map(fn (RoomTimeSlot $rts) => [
                'id'         => $rts->id,
                'price'      => (float) $rts->price,
                'time_slot'  => $rts->timeSlot?->label,
            ])->values();
        }

        return $item;
    }
}
