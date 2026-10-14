<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomAmenity;
use Modules\Product\App\Models\RoomAmenityAssign;

/**
 * Quản lý TIỆN ÍCH SÂN (bảng `room_amenities`) — danh mục dùng CHUNG cho nhiều sân của 1 đối tác
 * (many-to-many qua bảng pivot `room_amenity_assigns`, xem RoomAmenityAssign), KHÔNG phải tiện ích
 * gắn riêng cho 1 sân — xem docblock Modules\Product\App\Filament\Resources\RoomAmenityResource.
 *
 * Khác với App\Http\Controllers\Api\Admin\TagController (bảng `tags`, field "tags" trong
 * ProductForm — label "Tiện ích" nhưng dùng Spatie Tag) — RoomAmenity là 1 danh mục HOÀN TOÀN
 * riêng, có amenity_type để nhóm hiển thị (xem RoomAmenityAssignResource Edit/List page — group
 * theo amenity_type, mỗi group 1 CheckboxList).
 *
 * BelongsToPartner trên RoomAmenity CHỈ tự lọc theo partner_id BÊN TRONG Filament panel (global
 * scope check AdminPanelContext::isActive()) — route API không chạy qua panel đó nên phải lọc thủ
 * công, cùng nguyên tắc ProductController::visibleProductsQuery().
 *
 * Gán/gỡ tiện ích cho 1 sân cụ thể (bảng `room_amenity_assigns`, room_id/amenity_id) được gộp luôn
 * vào controller này (assignedToProduct()/syncForProduct()) thay vì tách controller riêng — UX
 * thực tế ở Filament (RoomAmenityAssignResource) chỉ có 1 thao tác duy nhất: chọn 1 sân rồi tick
 * chọn LẠI TOÀN BỘ tiện ích áp dụng (xoá hết rồi tạo lại theo danh sách gửi lên), không có
 * add/remove từng cái một — nên 1 endpoint "sync" (thay thế toàn bộ danh sách) là đủ và an toàn
 * hơn so với thêm 2 endpoint assign/unassign riêng lẻ.
 */
class RoomAmenityController extends Controller
{
    /**
     * GET /api/admin/room-amenities
     * Danh sách tiện ích của đối tác (super_admin xem hết).
     * Query: ?amenity_type=&status=&search=&per_page=
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $this->visibleAmenitiesQuery($user);

        if ($request->filled('amenity_type')) {
            $query->where('amenity_type', (string) $request->string('amenity_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->boolean('status'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        $amenities = $query->orderBy('amenity_type')->orderBy('sort_order')
            ->paginate($request->integer('per_page', 50));

        $amenities->getCollection()->transform(fn (RoomAmenity $a) => $this->toItem($a));

        return response()->json($amenities);
    }

    /**
     * POST /api/admin/room-amenities
     * Body: amenity_type, icon, name (required), status (default true), sort_order (default 0).
     * partner_id: super_admin có thể truyền partner_id, user thường luôn gán theo đối tác của mình.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isSuperAdmin() && empty($user->partner_id)) {
            return response()->json(['message' => 'Account does not belong to any partner, cannot create amenity.'], 403);
        }

        $data = $request->validate($this->rules());

        $partnerId = $user->isSuperAdmin() ? ($data['partner_id'] ?? null) : $user->partner_id;

        $amenity = RoomAmenity::create([
            'partner_id'   => $partnerId,
            'amenity_type' => $data['amenity_type'] ?? 'Khác', // cột bắt buộc: không gửi thì xếp vào nhóm "Khác"
            'icon'         => $data['icon'] ?? null,
            'name'         => $data['name'],
            'status'       => $data['status'] ?? true,
            'sort_order'   => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $this->toItem($amenity)], 201);
    }

    /**
     * GET /api/admin/room-amenities/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $amenity = $this->visibleAmenitiesQuery($request->user())->find($id);

        if (! $amenity) {
            return response()->json(['message' => 'Amenity not found.'], 404);
        }

        return response()->json(['data' => $this->toItem($amenity)]);
    }

    /**
     * PUT|POST /api/admin/room-amenities/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $amenity = $this->visibleAmenitiesQuery($user)->find($id);

        if (! $amenity) {
            return response()->json(['message' => 'Amenity not found.'], 404);
        }

        $data = $request->validate($this->rules(isUpdate: true));

        $amenity->update(collect($data)->only([
            'amenity_type', 'icon', 'name', 'status', 'sort_order',
        ])->toArray());

        return response()->json(['data' => $this->toItem($amenity)]);
    }

    /**
     * DELETE /api/admin/room-amenities/{id}
     * Xoá cả các dòng gán (room_amenity_assigns) liên quan tới tiện ích này.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user    = $request->user();
        $amenity = $this->visibleAmenitiesQuery($user)->find($id);

        if (! $amenity) {
            return response()->json(['message' => 'Amenity not found.'], 404);
        }

        DB::transaction(function () use ($amenity) {
            RoomAmenityAssign::where('amenity_id', $amenity->id)->delete();
            $amenity->delete();
        });

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * GET /api/admin/products/{id}/amenities
     * Danh sách tiện ích đang gán cho 1 sân.
     */
    public function assignedToProduct(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        // Product::amenities() (belongsToMany qua room_amenity_assigns) đã tự where('status', true)
        // + orderBy amenity_type/sort_order — đúng phạm vi hiển thị dùng ở Filament CheckboxList.
        $amenities = $product->amenities()->get();

        return response()->json(['data' => $amenities->map(fn (RoomAmenity $a) => $this->toItem($a))->values()]);
    }

    /**
     * POST /api/admin/products/{id}/amenities
     * Gán LẠI TOÀN BỘ danh sách tiện ích cho sân — xoá hết dòng gán cũ rồi tạo lại theo
     * amenity_ids[] gửi lên (đồng bộ đúng hành vi handleRecordUpdate() ở
     * RoomAmenityAssignResource\Pages\EditRoomAmenityAssign). Gửi amenity_ids=[] để gỡ hết.
     *
     * Body: { "amenity_ids": [1, 2, 3] }
     */
    public function syncForProduct(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $data = $request->validate([
            'amenity_ids'   => 'present|array',
            'amenity_ids.*' => 'integer|exists:room_amenities,id',
        ]);

        DB::transaction(function () use ($product, $data) {
            RoomAmenityAssign::where('room_id', $product->id)->delete();

            foreach (array_unique($data['amenity_ids']) as $amenityId) {
                RoomAmenityAssign::create([
                    'room_id'    => $product->id,
                    'amenity_id' => $amenityId,
                ]);
            }
        });

        $amenities = RoomAmenity::whereIn('id', $data['amenity_ids'])
            ->orderBy('amenity_type')->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $amenities->map(fn (RoomAmenity $a) => $this->toItem($a))->values()]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function rules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'amenity_type' => 'nullable|string|max:100',
            'icon'         => 'nullable|string|max:150',
            'name'         => "{$required}|string|max:255",
            'status'       => 'nullable|boolean',
            'sort_order'   => 'nullable|integer|min:0',
            'partner_id'   => 'nullable|uuid|exists:partners,id',
        ];
    }

    private function visibleAmenitiesQuery(User $user): Builder
    {
        $query = RoomAmenity::query();

        if (! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }

        return $query;
    }

    private function visibleProduct(User $user, string $id): ?Product
    {
        $query = Product::query();

        if (! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }

        return $query->find($id);
    }

    private function toItem(RoomAmenity $amenity): array
    {
        return [
            'id'           => $amenity->id,
            'partner_id'   => $amenity->partner_id,
            'amenity_type' => $amenity->amenity_type,
            'icon'         => $amenity->icon,
            'name'         => $amenity->name,
            'status'       => (bool) $amenity->status,
            'sort_order'   => $amenity->sort_order,
        ];
    }
}
