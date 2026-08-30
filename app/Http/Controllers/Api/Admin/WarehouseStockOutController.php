<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\App\Models\WarehouseStockOut;

/**
 * Phiếu xuất kho — scoped theo CẢ đối tác lẫn chi nhánh (branch_id). Xem docblock
 * WarehouseItemController/WarehouseStockInController. "room_id" (tham số API, khớp đúng tên field
 * FE) map vào cột products_id thật của bảng là 'product_id' — xem WarehouseStockOut::room().
 */
class WarehouseStockOutController extends Controller
{
    /**
     * GET /api/admin/warehouse/stock-outs
     * Query params: search (theo mã phiếu), reason (lọc theo lý do CỦA TỪNG DÒNG), room_id,
     * per_page (mặc định 20)
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseStockOut::query()->with(['creator', 'room', 'branch', 'items'])->withCount('items');
        $this->scopeToPartnerAndBranch($query, $request);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('code', 'like', "%{$search}%");
        }

        if ($reason = $request->input('reason')) {
            $query->whereHas('items', fn ($q) => $q->where('reason', $reason));
        }

        if ($request->filled('room_id')) {
            $query->where('product_id', $request->input('room_id'));
        }

        $query->orderByDesc('issued_at');

        $stockOuts = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $stockOuts->items(),
            'meta' => [
                'current_page' => $stockOuts->currentPage(),
                'last_page'    => $stockOuts->lastPage(),
                'total'        => $stockOuts->total(),
                'per_page'     => $stockOuts->perPage(),
            ],
        ]);
    }

    /**
     * POST /api/admin/warehouse/stock-outs
     * Body: { room_id?, issued_to?, note?, branch_id?, partner_id? (chỉ super_admin),
     *         items: [{ warehouse_item_id, quantity, reason, note? }, ...] }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id'                    => ['nullable', 'string', 'exists:products,id'],
            'issued_to'                  => ['nullable', 'string', 'max:255'],
            'note'                       => ['nullable', 'string'],
            'branch_id'                  => ['nullable', 'integer', 'exists:categories,id'],
            'partner_id'                 => ['nullable', 'uuid'],
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.warehouse_item_id'  => ['required', 'integer', 'exists:warehouse_items,id'],
            'items.*.quantity'           => ['required', 'numeric', 'min:0.01'],
            'items.*.reason'             => ['required', 'string', 'in:' . implode(',', array_keys(WarehouseStockOut::REASONS))],
            'items.*.note'               => ['nullable', 'string'],
        ]);

        $this->resolveOwnership($request, $data);

        $stockOut = DB::transaction(function () use ($data) {
            $stockOut = WarehouseStockOut::create([
                'product_id' => $data['room_id'] ?? null,
                'issued_to'  => $data['issued_to'] ?? null,
                'note'       => $data['note'] ?? null,
                'branch_id'  => $data['branch_id'] ?? null,
                'partner_id' => $data['partner_id'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $stockOut->items()->create($line);
            }

            return $stockOut;
        });

        return response()->json(['data' => $stockOut->load(['items.item', 'creator', 'room', 'branch'])], 201);
    }

    /** GET /api/admin/warehouse/stock-outs/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockOut::query()->with(['items.item', 'creator', 'room', 'branch']);
        $this->scopeToPartnerAndBranch($query, $request);

        $stockOut = $query->find($id);
        if (! $stockOut) {
            return response()->json(['message' => 'Không tìm thấy phiếu xuất kho.'], 404);
        }

        return response()->json(['data' => $stockOut]);
    }

    /**
     * PUT /api/admin/warehouse/stock-outs/{id}
     * Body: { room_id?, issued_to?, note?, items?: [{ id?, warehouse_item_id, quantity, reason,
     *         note? }, ...] } — gửi kèm 'items' sẽ THAY THẾ toàn bộ danh sách dòng cũ, cùng cơ chế
     * với WarehouseStockInController::update().
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockOut::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $stockOut = $query->find($id);
        if (! $stockOut) {
            return response()->json(['message' => 'Không tìm thấy phiếu xuất kho.'], 404);
        }

        $data = $request->validate([
            'room_id'                    => ['nullable', 'string', 'exists:products,id'],
            'issued_to'                  => ['nullable', 'string', 'max:255'],
            'note'                       => ['nullable', 'string'],
            'items'                      => ['sometimes', 'array', 'min:1'],
            'items.*.id'                 => ['nullable', 'integer', 'exists:warehouse_stock_out_items,id'],
            'items.*.warehouse_item_id'  => ['required_with:items', 'integer', 'exists:warehouse_items,id'],
            'items.*.quantity'           => ['required_with:items', 'numeric', 'min:0.01'],
            'items.*.reason'             => ['required_with:items', 'string', 'in:' . implode(',', array_keys(WarehouseStockOut::REASONS))],
            'items.*.note'               => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($stockOut, $data) {
            $headerData = array_intersect_key($data, array_flip(['room_id', 'issued_to', 'note']));
            if (array_key_exists('room_id', $headerData)) {
                $headerData['product_id'] = $headerData['room_id'];
                unset($headerData['room_id']);
            }
            if ($headerData) {
                $stockOut->update($headerData);
            }

            if (! isset($data['items'])) {
                return;
            }

            $keepIds = [];
            foreach ($data['items'] as $line) {
                if (! empty($line['id'])) {
                    $stockOut->items()->whereKey($line['id'])->first()?->update($line);
                    $keepIds[] = $line['id'];
                } else {
                    $keepIds[] = $stockOut->items()->create($line)->id;
                }
            }

            $stockOut->items()->whereNotIn('id', $keepIds)->get()->each->delete();
        });

        return response()->json(['data' => $stockOut->fresh(['items.item', 'creator', 'room', 'branch'])]);
    }

    /** DELETE /api/admin/warehouse/stock-outs/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $query = WarehouseStockOut::query();
        $this->scopeToPartnerAndBranch($query, $request);

        $stockOut = $query->find($id);
        if (! $stockOut) {
            return response()->json(['message' => 'Không tìm thấy phiếu xuất kho.'], 404);
        }

        $stockOut->delete();

        return response()->json(['message' => 'Đã xoá phiếu xuất kho.']);
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
