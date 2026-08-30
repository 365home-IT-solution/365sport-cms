<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Warehouse\App\Models\WarehouseItem;

/**
 * Vật tư trong kho — scoped theo CẢ đối tác lẫn chi nhánh (branch_id → categories gốc, xem
 * BelongsToBranch). Global scope 'partner'/'branch' của model chỉ tự áp dụng bên trong admin panel
 * Filament (xem AdminPanelContext), route API này KHÔNG đi qua panel nên phải tự lặp lại đúng logic
 * lọc thủ công — không được dựa vào global scope tự chạy.
 */
class WarehouseItemController extends Controller
{
    /**
     * GET /api/admin/warehouse/items
     * Query params:
     *  - search      : tìm theo tên/SKU
     *  - category_id : lọc theo warehouse_category_id
     *  - unit_id     : lọc theo warehouse_unit_id
     *  - low_stock=1 : chỉ lấy vật tư sắp hết (quantity <= min_quantity, min_quantity > 0)
     *  - all=1       : bỏ qua phân trang, trả toàn bộ (dùng cho dropdown chọn vật tư)
     *  - per_page    : mặc định 20
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseItem::query()->with(['category', 'unit', 'branch']);
        $this->scopeToPartnerAndBranch($query, $request);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('warehouse_category_id', $request->input('category_id'));
        }

        if ($request->filled('unit_id')) {
            $query->where('warehouse_unit_id', $request->input('unit_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity', '<=', 'min_quantity')->where('min_quantity', '>', 0);
        }

        $query->orderBy('name');

        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        $items = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page'    => $items->lastPage(),
                'total'        => $items->total(),
                'per_page'     => $items->perPage(),
            ],
        ]);
    }

    /** POST /api/admin/warehouse/items */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'sku'                    => ['nullable', 'string', 'max:100'],
            'warehouse_category_id'  => ['nullable', 'integer', 'exists:warehouse_categories,id'],
            'warehouse_unit_id'      => ['nullable', 'integer', 'exists:warehouse_units,id'],
            'quantity'               => ['nullable', 'numeric', 'min:0'],
            'quantity_in_use'        => ['nullable', 'numeric', 'min:0'],
            'min_quantity'           => ['nullable', 'numeric', 'min:0'],
            'unit_price'             => ['nullable', 'numeric', 'min:0'],
            'description'            => ['nullable', 'string'],
            'status'                 => ['nullable', 'boolean'],
            'branch_id'              => ['nullable', 'integer', 'exists:categories,id'],
            'partner_id'             => ['nullable', 'uuid'],
        ]);

        $this->resolveOwnership($request, $data);

        $item = WarehouseItem::create($data);

        return response()->json(['data' => $item->load(['category', 'unit', 'branch'])], 201);
    }

    /** GET /api/admin/warehouse/items/{id} */
    public function show(Request $request, string $id): JsonResponse
    {
        $query = WarehouseItem::query()->with(['category', 'unit', 'branch']);
        $this->scopeToPartnerAndBranch($query, $request);

        $item = $query->find($id);
        if (! $item) {
            return response()->json(['message' => 'Không tìm thấy vật tư.'], 404);
        }

        return response()->json(['data' => $item]);
    }

    /** PUT /api/admin/warehouse/items/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $query = WarehouseItem::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $item = $query->find($id);
        if (! $item) {
            return response()->json(['message' => 'Không tìm thấy vật tư.'], 404);
        }

        $data = $request->validate([
            'name'                  => ['sometimes', 'required', 'string', 'max:255'],
            'sku'                   => ['nullable', 'string', 'max:100'],
            'warehouse_category_id' => ['nullable', 'integer', 'exists:warehouse_categories,id'],
            'warehouse_unit_id'     => ['nullable', 'integer', 'exists:warehouse_units,id'],
            'quantity'              => ['nullable', 'numeric', 'min:0'],
            'quantity_in_use'       => ['nullable', 'numeric', 'min:0'],
            'min_quantity'          => ['nullable', 'numeric', 'min:0'],
            'unit_price'            => ['nullable', 'numeric', 'min:0'],
            'description'           => ['nullable', 'string'],
            'status'                => ['nullable', 'boolean'],
            'branch_id'             => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $item->update($data);

        return response()->json(['data' => $item->load(['category', 'unit', 'branch'])]);
    }

    /** DELETE /api/admin/warehouse/items/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $query = WarehouseItem::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $item = $query->find($id);
        if (! $item) {
            return response()->json(['message' => 'Không tìm thấy vật tư.'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Đã xoá vật tư.']);
    }

    private function scopeToPartnerAndBranch($query, Request $request): void
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);

            $branchIds = $user->rootProductCategoryIds();
            $query->whereIn('branch_id', $branchIds);
        }
    }

    private function resolveOwnership(Request $request, array &$data): void
    {
        $user = $request->user();
        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        $data['partner_id'] = $user->partner_id;

        if (empty($data['branch_id'])) {
            $branchIds = $user->rootProductCategoryIds();
            if (count($branchIds) === 1) {
                $data['branch_id'] = $branchIds[0];
            }
        }
    }
}
