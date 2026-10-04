<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Warehouse\App\Models\WarehouseItemRental;
use Modules\Warehouse\App\Services\WarehouseRentalService;

/**
 * Nhật ký mượn/trả dụng cụ thể thao (thuê kèm đặt sân) — được TẠO TỰ ĐỘNG khi khách chọn dịch vụ
 * cho thuê dụng cụ lúc đặt sân (xem WarehouseRentalService::rent()), API này chỉ để XEM + xử lý
 * (trả/báo mất), không có endpoint tạo thủ công.
 */
class WarehouseItemRentalController extends Controller
{
    /**
     * GET /api/admin/warehouse/item-rentals
     * Query params: status (rented|returned|lost), per_page
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseItemRental::query()
            ->with(['warehouseItem', 'orderService.order'])
            ->whereHas('warehouseItem', fn ($q) => $this->scopeItems($q, $request));

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $query->orderByDesc('rented_at');

        $rentals = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $rentals->items(),
            'meta' => [
                'current_page' => $rentals->currentPage(),
                'last_page'    => $rentals->lastPage(),
                'total'        => $rentals->total(),
                'per_page'     => $rentals->perPage(),
            ],
        ]);
    }

    /** POST /api/admin/warehouse/item-rentals/{id}/return */
    public function markReturned(Request $request, string $id, WarehouseRentalService $service): JsonResponse
    {
        $rental = WarehouseItemRental::whereHas('warehouseItem', fn ($q) => $this->scopeItems($q, $request))->find($id);
        if (! $rental) {
            return response()->json(['message' => 'Rental not found.'], 404);
        }

        if (! $rental->isActive()) {
            return response()->json(['message' => 'This rental is not currently active.'], 422);
        }

        $service->releaseForOrderService($rental->orderService, WarehouseItemRental::STATUS_RETURNED);

        return response()->json(['data' => $rental->fresh(['warehouseItem', 'orderService.order'])]);
    }

    /** POST /api/admin/warehouse/item-rentals/{id}/mark-lost */
    public function markLost(Request $request, string $id, WarehouseRentalService $service): JsonResponse
    {
        $rental = WarehouseItemRental::whereHas('warehouseItem', fn ($q) => $this->scopeItems($q, $request))->find($id);
        if (! $rental) {
            return response()->json(['message' => 'Rental not found.'], 404);
        }

        if (! $rental->isActive()) {
            return response()->json(['message' => 'This rental is not currently active.'], 422);
        }

        $service->releaseForOrderService($rental->orderService, WarehouseItemRental::STATUS_LOST);

        return response()->json(['data' => $rental->fresh(['warehouseItem', 'orderService.order'])]);
    }

    // Global scope partner/branch của WarehouseItem chỉ chạy trong admin panel Filament — API phải
    // tự lọc (cùng logic WarehouseItemController::scopeToPartnerAndBranch).
    private function scopeItems($query, Request $request): void
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id)
                ->whereIn('branch_id', $user->rootProductCategoryIds());
        }
    }
}
