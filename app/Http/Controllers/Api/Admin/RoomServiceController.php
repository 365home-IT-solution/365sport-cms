<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomService;

/**
 * Quản lý DỊCH VỤ BỔ SUNG gắn riêng cho 1 sân (bảng `room_services`, product_id) — khác với
 * additional_services (Modules\BladeThemeV1\App\Models\AdditionService, field "additional_services"
 * ở ProductForm/API /api/admin/products, dùng chung nhiều sân) — xem docblock
 * Modules\Product\App\Filament\Resources\RoomServiceResource. RoomService KHÔNG có cột partner_id
 * riêng, phạm vi hiển thị/sửa lọc GIÁN TIẾP qua partner_id của Product cha — cùng nguyên tắc
 * RoomTimeSlotController (sub-resource của products/{id}).
 */
class RoomServiceController extends Controller
{
    /**
     * GET /api/admin/products/{id}/services
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $services = $product->services()->get();

        return response()->json(['data' => $services->map(fn (RoomService $s) => $this->toItem($s))->values()]);
    }

    /**
     * POST /api/admin/products/{id}/services
     * Body: name (required), icon, description, price, unit, sort_order.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $data = $request->validate($this->rules());

        $service = RoomService::create([
            'product_id'  => $product->id,
            'name'        => $data['name'],
            'icon'        => $data['icon'] ?? null,
            'description' => $data['description'] ?? null,
            'price'       => $data['price'] ?? 0,
            'unit'        => $data['unit'] ?? null,
            'sort_order'  => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $this->toItem($service)], 201);
    }

    /**
     * PUT|POST /api/admin/products/{id}/services/{serviceId}
     */
    public function update(Request $request, string $id, string $serviceId): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $service = RoomService::where('id', $serviceId)->where('product_id', $product->id)->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $data = $request->validate($this->rules(isUpdate: true));

        $service->update(collect($data)->only([
            'name', 'icon', 'description', 'price', 'unit', 'sort_order',
        ])->toArray());

        return response()->json(['data' => $this->toItem($service)]);
    }

    /**
     * DELETE /api/admin/products/{id}/services/{serviceId}
     */
    public function destroy(Request $request, string $id, string $serviceId): JsonResponse
    {
        $product = $this->visibleProduct($request->user(), $id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $service = RoomService::where('id', $serviceId)->where('product_id', $product->id)->first();

        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        $service->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function rules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return [
            'name'        => "{$required}|string|max:255",
            'icon'        => 'nullable|string|max:150',
            'description' => 'nullable|string|max:1000',
            'price'       => 'nullable|integer|min:0',
            'unit'        => 'nullable|string|max:100',
            'sort_order'  => 'nullable|integer|min:0',
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

    private function toItem(RoomService $service): array
    {
        return [
            'id'          => $service->id,
            'product_id'  => $service->product_id,
            'name'        => $service->name,
            'icon'        => $service->icon,
            'description' => $service->description,
            'price'       => $service->price,
            'unit'        => $service->unit,
            'sort_order'  => $service->sort_order,
        ];
    }
}
