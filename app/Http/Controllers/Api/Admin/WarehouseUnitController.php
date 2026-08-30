<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Warehouse\App\Models\WarehouseUnit;

/**
 * Đơn vị tính trong kho — cùng bản chất với WarehouseCategoryController (danh mục dùng chung cho
 * mọi chi nhánh của 1 đối tác, không có branch_id). Xem docblock WarehouseCategoryController.
 */
class WarehouseUnitController extends Controller
{
    /** GET /api/admin/warehouse/units */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseUnit::query();
        $this->scopeToPartner($query, $request);

        $units = $query->orderBy('name')->get();

        return response()->json(['data' => $units]);
    }

    /** POST /api/admin/warehouse/units */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:50'],
            'partner_id' => ['nullable', 'uuid'],
        ]);

        $data['partner_id'] = $this->resolvePartnerId($request, $data['partner_id'] ?? null);

        $unit = WarehouseUnit::create($data);

        return response()->json(['data' => $unit], 201);
    }

    /** GET /api/admin/warehouse/units/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = WarehouseUnit::query();
        $this->scopeToPartner($query, $request);

        $unit = $query->find($id);
        if (! $unit) {
            return response()->json(['message' => 'Không tìm thấy đơn vị tính.'], 404);
        }

        return response()->json(['data' => $unit]);
    }

    /** PUT /api/admin/warehouse/units/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $query = WarehouseUnit::query();
        $this->scopeToPartner($query, $request);

        $unit = $query->find($id);
        if (! $unit) {
            return response()->json(['message' => 'Không tìm thấy đơn vị tính.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $unit->update($data);

        return response()->json(['data' => $unit]);
    }

    /** DELETE /api/admin/warehouse/units/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $query = WarehouseUnit::query();
        $this->scopeToPartner($query, $request);

        $unit = $query->find($id);
        if (! $unit) {
            return response()->json(['message' => 'Không tìm thấy đơn vị tính.'], 404);
        }

        $unit->delete();

        return response()->json(['message' => 'Đã xoá đơn vị tính.']);
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
