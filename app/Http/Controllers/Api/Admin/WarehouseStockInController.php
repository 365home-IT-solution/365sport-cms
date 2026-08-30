<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\App\Models\WarehouseStockIn;

/**
 * Phiếu nhập kho — scoped theo CẢ đối tác lẫn chi nhánh (branch_id). Xem docblock
 * WarehouseItemController cho lý do phải tự lặp lại logic lọc thủ công (global scope chỉ tự chạy
 * trong admin panel Filament). Dòng chi tiết (items) tạo qua $stockIn->items()->create() để các
 * boot hook cộng tồn kho của WarehouseStockInItem tự chạy đúng (xem model đó) — KHÔNG insert thẳng
 * qua query builder.
 */
class WarehouseStockInController extends Controller
{
    /**
     * GET /api/admin/warehouse/stock-ins
     * Query params: search (theo mã phiếu), per_page (mặc định 20)
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseStockIn::query()->with(['creator', 'branch'])->withCount('items');
        $this->scopeToPartnerAndBranch($query, $request);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('code', 'like', "%{$search}%");
        }

        $query->orderByDesc('received_at');

        $stockIns = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $stockIns->items(),
            'meta' => [
                'current_page' => $stockIns->currentPage(),
                'last_page'    => $stockIns->lastPage(),
                'total'        => $stockIns->total(),
                'per_page'     => $stockIns->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/admin/warehouse/stock-ins
     * Body: { note?, branch_id?, partner_id? (chỉ super_admin),
     *         items: [{ warehouse_item_id, quantity, unit_price, note? }, ...] }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'note'                        => ['nullable', 'string'],
            'branch_id'                   => ['nullable', 'integer', 'exists:categories,id'],
            'partner_id'                  => ['nullable', 'uuid'],
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.warehouse_item_id'   => ['required', 'integer', 'exists:warehouse_items,id'],
            'items.*.quantity'            => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'          => ['required', 'numeric', 'min:0'],
            'items.*.note'                => ['nullable', 'string'],
        ]);

        $this->resolveOwnership($request, $data);

        $stockIn = DB::transaction(function () use ($data) {
            $stockIn = WarehouseStockIn::create([
                'note'       => $data['note'] ?? null,
                'branch_id'  => $data['branch_id'] ?? null,
                'partner_id' => $data['partner_id'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $stockIn->items()->create($line);
            }

            return $stockIn;
        });

        return response()->json(['data' => $stockIn->load(['items.item', 'creator', 'branch'])], 201);
    }

    /** GET /api/admin/warehouse/stock-ins/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockIn::query()->with(['items.item', 'creator', 'branch']);
        $this->scopeToPartnerAndBranch($query, $request);

        $stockIn = $query->find($id);
        if (! $stockIn) {
            return response()->json(['message' => 'Không tìm thấy phiếu nhập kho.'], 404);
        }

        return response()->json(['data' => $stockIn]);
    }

    /**
     * PUT /api/admin/warehouse/stock-ins/{id}
     * Body: { note?, items?: [{ id?, warehouse_item_id, quantity, unit_price, note? }, ...] }
     * Nếu gửi kèm 'items', THAY THẾ TOÀN BỘ danh sách dòng cũ bằng danh sách mới — dòng cũ không
     * còn trong danh sách gửi lên sẽ bị xoá (kích hoạt hoàn tác tồn kho qua boot hook deleted()).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockIn::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $stockIn = $query->find($id);
        if (! $stockIn) {
            return response()->json(['message' => 'Không tìm thấy phiếu nhập kho.'], 404);
        }

        $data = $request->validate([
            'note'                       => ['nullable', 'string'],
            'items'                      => ['sometimes', 'array', 'min:1'],
            'items.*.id'                 => ['nullable', 'integer', 'exists:warehouse_stock_in_items,id'],
            'items.*.warehouse_item_id'  => ['required_with:items', 'integer', 'exists:warehouse_items,id'],
            'items.*.quantity'           => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.unit_price'         => ['required_with:items', 'numeric', 'min:0'],
            'items.*.note'               => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($stockIn, $data) {
            if (array_key_exists('note', $data)) {
                $stockIn->update(['note' => $data['note']]);
            }

            if (! isset($data['items'])) {
                return;
            }

            $keepIds = [];
            foreach ($data['items'] as $line) {
                if (! empty($line['id'])) {
                    $stockIn->items()->whereKey($line['id'])->first()?->update($line);
                    $keepIds[] = $line['id'];
                } else {
                    $keepIds[] = $stockIn->items()->create($line)->id;
                }
            }

            $stockIn->items()->whereNotIn('id', $keepIds)->get()->each->delete();
        });

        return response()->json(['data' => $stockIn->fresh(['items.item', 'creator', 'branch'])]);
    }

    /** DELETE /api/admin/warehouse/stock-ins/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockIn::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $stockIn = $query->find($id);
        if (! $stockIn) {
            return response()->json(['message' => 'Không tìm thấy phiếu nhập kho.'], 404);
        }

        $stockIn->delete();

        return response()->json(['message' => 'Đã xoá phiếu nhập kho.']);
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
