<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomSpecial;

/**
 * Quản lý ĐIỂM ĐẶC BIỆT của 1 sân (bảng `room_specials`, product_id) — vd "Miễn phí WiFi tốc độ
 * cao", "Ban công view đẹp"... xem docblock Modules\Product\App\Filament\Resources\RoomSpecialResource.
 * RoomSpecial KHÔNG có cột partner_id riêng, phạm vi hiển thị/sửa lọc GIÁN TIẾP qua partner_id của
 * Product cha — cùng nguyên tắc RoomServiceController/RoomTimeSlotController.
 */
class RoomSpecialController extends Controller
{
    /**
     * GET /api/admin/products/{id}/specials
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $specials = $product->specials()->get();

        return response()->json(['data' => $specials->map(fn (RoomSpecial $s) => $this->toItem($s))->values()]);
    }

    /**
     * POST /api/admin/products/{id}/specials
     * Body: title (required), icon, short_description, sort_order.
     */
    /**
     * GET /api/admin/products/{id}/specials/{specialId}
     */
    public function show(Request $request, string $id, string $specialId): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $item = RoomSpecial::where('id', $specialId)->where('product_id', $product->id)->first();

        if (! $item) {
            return response()->json(['message' => 'Special not found.'], 404);
        }

        return response()->json(['data' => $this->toItem($item)]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $data = $request->validate($this->rules());

        $special = RoomSpecial::create([
            'product_id'        => $product->id,
            'title'             => $data['title'],
            'icon'              => $data['icon'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'sort_order'        => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $this->toItem($special)], 201);
    }

    /**
     * PUT|POST /api/admin/products/{id}/specials/{specialId}
     */
    public function update(Request $request, string $id, string $specialId): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $special = RoomSpecial::where('id', $specialId)->where('product_id', $product->id)->first();

        if (! $special) {
            return response()->json(['message' => 'Special not found.'], 404);
        }

        $data = $request->validate($this->rules(isUpdate: true));

        $special->update(collect($data)->only([
            'title', 'icon', 'short_description', 'sort_order',
        ])->toArray());

        return response()->json(['data' => $this->toItem($special)]);
    }

    /**
     * DELETE /api/admin/products/{id}/specials/{specialId}
     */
    public function destroy(Request $request, string $id, string $specialId): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $special = RoomSpecial::where('id', $specialId)->where('product_id', $product->id)->first();

        if (! $special) {
            return response()->json(['message' => 'Special not found.'], 404);
        }

        $special->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function rules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'title'             => "{$required}|string|max:255",
            'icon'              => 'nullable|string|max:150',
            'short_description' => 'nullable|string|max:500',
            'sort_order'        => 'nullable|integer|min:0',
        ];
    }

    private function visibleProduct(User $user, string $id): ?Product
    {
        $query = Product::query();

        if (! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }

        return $query->find($id);
    }

    private function toItem(RoomSpecial $special): array
    {
        return [
            'id'                => $special->id,
            'product_id'        => $special->product_id,
            'title'             => $special->title,
            'icon'              => $special->icon,
            'short_description' => $special->short_description,
            'sort_order'        => $special->sort_order,
        ];
    }
}
