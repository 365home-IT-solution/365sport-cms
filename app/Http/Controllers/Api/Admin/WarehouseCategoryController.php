<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Warehouse\App\Models\WarehouseCategory;

/**
 * Nhóm vật tư trong kho — danh mục DÙNG CHUNG cho mọi chi nhánh của 1 đối tác (không có branch_id,
 * chỉ có partner_id — xem BelongsToPartner). Global scope 'partner' của model chỉ tự áp dụng bên
 * trong admin panel Filament, KHÔNG áp dụng cho route API này, nên phải tự lọc partner_id thủ công.
 */
class WarehouseCategoryController extends Controller
{
    /** GET /api/admin/warehouse/categories */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseCategory::query();
        $this->scopeToPartner($query, $request);

        $categories = $query->orderBy('name')->get();

        return response()->json(['data' => $categories]);
    }

    /** POST /api/admin/warehouse/categories */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:150'],
            'partner_id' => ['nullable', 'uuid'],
        ]);

        $data['partner_id'] = $this->resolvePartnerId($request, $data['partner_id'] ?? null);

        $category = WarehouseCategory::create($data);

        return response()->json(['data' => $category], 201);
    }

    /** GET /api/admin/warehouse/categories/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = WarehouseCategory::query();
        $this->scopeToPartner($query, $request);

        $category = $query->find($id);
        if (! $category) {
            return response()->json(['message' => 'Không tìm thấy nhóm vật tư.'], 404);
        }

        return response()->json(['data' => $category]);
    }

    /** PUT /api/admin/warehouse/categories/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $query = WarehouseCategory::query();
        $this->scopeToPartner($query, $request);

        $category = $query->find($id);
        if (! $category) {
            return response()->json(['message' => 'Không tìm thấy nhóm vật tư.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $category->update($data);

        return response()->json(['data' => $category]);
    }

    /** DELETE /api/admin/warehouse/categories/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $query = WarehouseCategory::query();
        $this->scopeToPartner($query, $request);

        $category = $query->find($id);
        if (! $category) {
            return response()->json(['message' => 'Không tìm thấy nhóm vật tư.'], 404);
        }

        $category->delete();

        return response()->json(['message' => 'Đã xoá nhóm vật tư.']);
    }

    private function scopeToPartner($query, Request $request): void
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }
    }

    private function resolvePartnerId(Request $request, ?string $requested): ?string
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin()) {
            return $user->partner_id;
        }

        return $requested;
    }
}
