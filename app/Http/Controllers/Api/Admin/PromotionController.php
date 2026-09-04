<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\App\Models\RoomTimeSlot;
use Modules\Promotion\App\Models\Promotion;

/**
 * CRUD Khuyến mãi (Promotion) — bảng `promotions`, cùng model/logic nghiệp vụ với
 * Modules\Promotion\App\Filament\Resources\PromotionResource (form PromotionForm, bảng
 * PromotionTable). API này chỉ thao tác trên chính bản ghi Promotion (tạo/sửa/xoá/liệt kê) — KHÔNG
 * đụng tới/trùng lặp việc gán-gỡ Promotion cho khung giờ sân (đó là RoomPromotionController, giữ
 * nguyên không đổi — endpoint GET/POST/DELETE /api/admin/rooms/{id}/promotions).
 *
 * Phân quyền: Promotion::BelongsToPartner chỉ tự lọc theo partner_id BÊN TRONG Filament panel
 * (global scope kiểm tra AdminPanelContext::isActive()) — route API KHÔNG đi qua panel đó nên phải
 * lọc thủ công theo partner_id của user đăng nhập (super_admin xem hết), giống hệt
 * RoomPromotionController::visiblePromotion().
 *
 * Field theo đúng PromotionForm (Filament) — KHÔNG có coupon-style field (code/apply_type/room_id/
 * usage_limit...), đó là của Coupon (model khác), không nhầm lẫn:
 *  - name, type (percentage|fixed|increase_percentage|increase_fixed), value, is_active
 *  - description (Filament ẩn field này nhưng cột vẫn tồn tại — vẫn nhận qua API nếu FE cần)
 *  - image, lable_client (nhãn hiển thị cho khách hàng)
 *  - start_at (required), end_at (required, sau start_at) — PromotionForm bắt buộc CẢ HAI, khác
 *    Coupon (end_at chỉ nullable)
 *  - tuimu_rewards: có trong $fillable của model nhưng KHÔNG xuất hiện ở PromotionForm — vẫn nhận
 *    qua API (cột đã tồn tại) nhưng không có validation nghiệp vụ nào để mirror từ Filament.
 *  - room_time_slot_ids[]: gán khuyến mãi cho các khung giờ NGAY LÚC TẠO/SỬA — tương đương thao tác
 *    thủ công qua RoomPromotionController::store() cho từng slot, tiện cho FE gửi 1 lần. min_quantity/
 *    custom_value của pivot dùng mặc định (1 / null) khi gán qua route này; muốn set 2 field pivot
 *    đó cho từng slot, dùng riêng RoomPromotionController::store().
 */
class PromotionController extends Controller
{
    /**
     * GET /api/admin/promotions
     * Query params:
     *  - search   : tìm theo name
     *  - status   : 1|0|true|false — lọc theo is_active
     *  - per_page : mặc định 20
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user  = $request->user();
        $query = $this->visiblePromotionsQuery($user)->with('creator');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $promotions = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($promotions->items())->map(fn (Promotion $p) => $this->toItem($p))->values(),
            'meta' => [
                'current_page' => $promotions->currentPage(),
                'last_page'    => $promotions->lastPage(),
                'total'        => $promotions->total(),
                'per_page'     => $promotions->perPage(),
            ],
        ]);
    }

    /** GET /api/admin/promotions/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $promotion = $this->visiblePromotionsQuery($request->user())
            ->with(['creator', 'roomTimeSlots.timeSlot', 'roomTimeSlots.room'])
            ->find($id);

        if (! $promotion) {
            return response()->json(['message' => 'Promotion not found.'], 404);
        }

        return response()->json(['data' => $this->toItem($promotion, withSlots: true)]);
    }

    /**
     * POST /api/admin/promotions
     * Body — xem PromotionForm::form():
     *  - name (required), type (required: percentage|fixed|increase_percentage|increase_fixed)
     *  - value (required, numeric, 0-100 nếu type=percentage|increase_percentage)
     *  - description (nullable), image (nullable, path/url — upload file xử lý riêng ở FE/Filament,
     *    API này chỉ lưu chuỗi đường dẫn nếu có sẵn)
     *  - lable_client (nullable, max 100)
     *  - start_at (required), end_at (required, sau start_at)
     *  - is_active (nullable, mặc định true)
     *  - room_time_slot_ids[] (nullable) — gán ngay các khung giờ cho khuyến mãi này
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->validateData($request);

        $promotion = Promotion::create([
            'partner_id'    => $user->isSuperAdmin() ? null : $user->partner_id,
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'type'          => $data['type'],
            'value'         => $data['value'],
            'tuimu_rewards' => $data['tuimu_rewards'] ?? null,
            'start_at'      => $data['start_at'],
            'end_at'        => $data['end_at'],
            'lable_client'  => $data['lable_client'] ?? null,
            'image'         => $data['image'] ?? null,
            'is_active'     => array_key_exists('is_active', $data) ? $data['is_active'] : true,
            'created_by'    => $user->id,
        ]);

        if (! empty($data['room_time_slot_ids'])) {
            $promotion->roomTimeSlots()->sync(
                collect($data['room_time_slot_ids'])->mapWithKeys(fn ($slotId) => [$slotId => ['min_quantity' => 1]])->all()
            );
        }

        return response()->json(['data' => $this->toItem($promotion->fresh('creator'), withSlots: true)], 201);
    }

    /** PUT|PATCH /api/admin/promotions/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user      = $request->user();
        $promotion = $this->visiblePromotionsQuery($user)->find($id);

        if (! $promotion) {
            return response()->json(['message' => 'Promotion not found.'], 404);
        }

        $data = $this->validateData($request, forUpdate: true);

        if (array_key_exists('status', $data)) {
            $data['is_active'] = $data['status'];
            unset($data['status']);
        }

        $roomTimeSlotIds = $data['room_time_slot_ids'] ?? null;
        unset($data['room_time_slot_ids']);

        $promotion->update($data);

        if ($roomTimeSlotIds !== null) {
            $promotion->roomTimeSlots()->sync(
                collect($roomTimeSlotIds)->mapWithKeys(fn ($slotId) => [$slotId => ['min_quantity' => 1]])->all()
            );
        }

        return response()->json(['data' => $this->toItem($promotion->fresh('creator'), withSlots: true)]);
    }

    /**
     * DELETE /api/admin/promotions/{id}
     * Xoá luôn kéo theo gỡ toàn bộ gán khung giờ (pivot promotion_room_time_slot) — không có ràng
     * buộc lịch sử sử dụng như Coupon (Promotion không có used_count/usage_limit).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $promotion = $this->visiblePromotionsQuery($request->user())->find($id);

        if (! $promotion) {
            return response()->json(['message' => 'Promotion not found.'], 404);
        }

        $promotion->roomTimeSlots()->detach();
        $promotion->delete();

        return response()->json(['message' => 'Promotion deleted successfully.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validateData(Request $request, bool $forUpdate = false): array
    {
        $req = $forUpdate ? ['sometimes', 'required'] : ['required'];

        return $request->validate([
            'name'                 => [...$req, 'string', 'max:255'],
            'description'          => 'nullable|string|max:500',
            'type'                 => [...$req, 'in:percentage,fixed,increase_percentage,increase_fixed'],
            'value'                => [
                ...$req,
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request, $forUpdate) {
                    $type = $request->input('type');
                    if (in_array($type, ['percentage', 'increase_percentage'], true) && $value > 100) {
                        $fail('The value must not be greater than 100 for a percentage type.');
                    }
                },
            ],
            'tuimu_rewards'        => 'nullable|integer|min:0',
            'lable_client'         => 'nullable|string|max:100',
            'image'                => 'nullable|string|max:2048',
            'start_at'             => [...$req, 'date'],
            'end_at'               => [...$req, 'date', 'after:start_at'],
            'is_active'            => 'sometimes|boolean',
            'status'               => 'sometimes|boolean',
            'room_time_slot_ids'   => 'nullable|array',
            'room_time_slot_ids.*' => 'integer|exists:room_time_slots,id',
        ]);
    }

    // Promotion::BelongsToPartner chỉ tự lọc theo partner_id BÊN TRONG Filament panel — API route
    // không đi qua panel đó nên phải lọc thủ công (cùng nguyên tắc RoomPromotionController).
    private function visiblePromotionsQuery(User $user): Builder
    {
        $query = Promotion::query();

        if (! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }

        return $query;
    }

    private function toItem(Promotion $p, bool $withSlots = false): array
    {
        $item = [
            'id'            => $p->id,
            'partner_id'    => $p->partner_id,
            'name'          => $p->name,
            'description'   => $p->description,
            'type'          => $p->type,
            'value'         => (float) $p->value,
            'tuimu_rewards' => $p->tuimu_rewards,
            'start_at'      => $p->start_at,
            'end_at'        => $p->end_at,
            'lable_client'  => $p->lable_client,
            'image'         => $p->image,
            'image_url'     => $p->image ? asset('storage/' . $p->image) : null,
            'is_active'     => $p->is_active,
            'created_by'    => $p->created_by,
            'creator_name'  => $p->creator?->name,
            'created_at'    => $p->created_at,
            'updated_at'    => $p->updated_at,
        ];

        if ($withSlots) {
            $item['room_time_slots'] = $p->roomTimeSlots->map(fn (RoomTimeSlot $rts) => [
                'id'           => $rts->id,
                'room_id'      => $rts->room_id,
                'room_name'    => $rts->room?->name,
                'price'        => (float) $rts->price,
                'time_slot'    => $rts->timeSlot?->label,
                'min_quantity' => $rts->pivot->min_quantity,
                'custom_value' => $rts->pivot->custom_value !== null ? (float) $rts->pivot->custom_value : null,
            ])->values();
        }

        return $item;
    }
}
