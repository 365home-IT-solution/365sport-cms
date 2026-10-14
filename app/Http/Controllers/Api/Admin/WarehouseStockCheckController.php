<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\App\Models\WarehouseStockCheck;

/**
 * Phiếu kiểm kê kho — scoped theo CẢ đối tác lẫn chi nhánh (branch_id). Xem docblock
 * WarehouseItemController/WarehouseStockInController. Mỗi dòng chỉ cần warehouse_item_id +
 * actual_quantity — system_quantity/difference tự tính trong boot hook của WarehouseStockCheckItem
 * (xem model đó), KHÔNG cần client tự truyền.
 */
class WarehouseStockCheckController extends Controller
{
    /**
     * GET /api/admin/warehouse/stock-checks
     * Query params: search (theo mã phiếu), per_page (mặc định 20)
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseStockCheck::query()->with(['creator', 'branch'])->withCount('items');
        $this->scopeToPartnerAndBranch($query, $request);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('code', 'like', "%{$search}%");
        }

        $query->orderByDesc('checked_at');

        $stockChecks = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $stockChecks->items(),
            'meta' => [
                'current_page' => $stockChecks->currentPage(),
                'last_page'    => $stockChecks->lastPage(),
                'total'        => $stockChecks->total(),
                'per_page'     => $stockChecks->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/admin/warehouse/stock-checks
     * Body: { note?, checked_at? (mặc định bây giờ), branch_id?, partner_id? (chỉ super_admin),
     *         items: [{ warehouse_item_id, actual_quantity, note? }, ...] }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'note'                        => ['nullable', 'string'],
            'checked_at'                  => ['nullable', 'date'],
            'branch_id'                   => ['nullable', 'integer', 'exists:categories,id'],
            'partner_id'                  => ['nullable', 'uuid'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.warehouse_item_id'   => ['required', 'integer', 'exists:warehouse_items,id'],
            'items.*.actual_quantity'     => ['required', 'numeric', 'min:0'],
            'items.*.note'                => ['nullable', 'string'],
        ]);

        $this->resolveOwnership($request, $data);

        $stockCheck = DB::transaction(function () use ($data) {
            $stockCheck = WarehouseStockCheck::create([
                'note'       => $data['note'] ?? null,
                'checked_at' => $data['checked_at'] ?? now(),
                'branch_id'  => $data['branch_id'] ?? null,
                'partner_id' => $data['partner_id'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $stockCheck->items()->create($line);
            }

            return $stockCheck;
        });

        return response()->json(['data' => $stockCheck->load(['items.item', 'creator', 'branch'])], 201);
    }

    /** GET /api/admin/warehouse/stock-checks/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockCheck::query()->with(['items.item', 'creator', 'handoverConfirmedBy', 'branch']);
        $this->scopeToPartnerAndBranch($query, $request);

        $stockCheck = $query->find($id);
        if (! $stockCheck) {
            return response()->json(['message' => 'Không tìm thấy phiếu kiểm kê.'], 404);
        }

        return response()->json(['data' => $stockCheck]);
    }

    /**
     * PUT /api/admin/warehouse/stock-checks/{id}
     * Body: { note?, items?: [{ id?, warehouse_item_id, actual_quantity, note? }, ...] } — gửi kèm
     * 'items' sẽ THAY THẾ toàn bộ danh sách dòng cũ, cùng cơ chế với
     * WarehouseStockInController::update().
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockCheck::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $stockCheck = $query->find($id);
        if (! $stockCheck) {
            return response()->json(['message' => 'Không tìm thấy phiếu kiểm kê.'], 404);
        }

        $data = $request->validate([
            'note'                       => ['nullable', 'string'],
            'items'                      => ['sometimes', 'array', 'min:1'],
            'items.*.id'                 => ['nullable', 'integer', 'exists:warehouse_stock_check_items,id'],
            'items.*.warehouse_item_id'  => ['required_with:items', 'integer', 'exists:warehouse_items,id'],
            'items.*.actual_quantity'    => ['required_with:items', 'numeric', 'min:0'],
            'items.*.note'               => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($stockCheck, $data) {
            if (array_key_exists('note', $data)) {
                $stockCheck->update(['note' => $data['note']]);
            }

            if (! isset($data['items'])) {
                return;
            }

            $keepIds = [];
            foreach ($data['items'] as $line) {
                if (! empty($line['id'])) {
                    $stockCheck->items()->whereKey($line['id'])->first()?->update($line);
                    $keepIds[] = $line['id'];
                } else {
                    $keepIds[] = $stockCheck->items()->create($line)->id;
                }
            }

            $stockCheck->items()->whereNotIn('id', $keepIds)->get()->each->delete();
        });

        return response()->json(['data' => $stockCheck->fresh(['items.item', 'creator', 'branch'])]);
    }

    /** DELETE /api/admin/warehouse/stock-checks/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockCheck::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $stockCheck = $query->find($id);
        if (! $stockCheck) {
            return response()->json(['message' => 'Không tìm thấy phiếu kiểm kê.'], 404);
        }

        $stockCheck->delete();

        return response()->json(['message' => 'Đã xoá phiếu kiểm kê.']);
    }

    private function scopeToPartnerAndBranch($query, Request $request): void
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
            $query->whereIn('branch_id', $user->rootProductCategoryIds());
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
