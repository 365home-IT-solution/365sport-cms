<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\App\Filament\Resources\ProductResource\Tables\Actions\RoomCleaningAction;
use Modules\Product\App\Models\Product;

/**
 * Tình trạng dọn vệ sinh sân (housekeeping_status: available|cleaning|maintenance) — bản API cho
 * app nhân viên, tương đương màn Filament RoomHousekeepingResource + action RoomCleaningAction.
 * Vòng đời: hết giờ đặt → tự động 'cleaning' (MarkRoomsForCleaningCommand) hoặc chủ đối tác/
 * super_admin đánh dấu thủ công → nhân viên trực đúng chi nhánh xác nhận 'available'. 'maintenance'
 * là trạng thái bảo trì, chỉ chủ đối tác/super_admin bật/tắt trực tiếp (không có luồng tự động).
 *
 * Phạm vi hiển thị/sửa giống HousekeepingSummaryService (Modules/Dashboard) — global scope
 * 'partner' của Product KHÔNG tự áp dụng ngoài Filament panel nên lọc thủ công theo partner_id, rồi
 * thu hẹp thêm theo chi nhánh nhân viên trực (employee->workBranches()) nếu không phải chủ đối tác/
 * super_admin/nhân viên "làm tất cả chi nhánh".
 */
class RoomHousekeepingController extends Controller
{
    /**
     * GET /api/admin/room-housekeeping
     * Query params:
     *  - branch_id : lọc theo 1 chi nhánh/khu vực (categories.id)
     *  - status    : available|cleaning|maintenance
     *  - search    : theo tên sân
     *  - per_page  : mặc định 20
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Product::query()
            ->where('is_activated', true)
            ->with(['categories' => fn ($q) => $q->where('category_type', 'product')]);

        $this->scopeToVisibleRooms($query, $user);

        if ($request->filled('branch_id')) {
            $branchId = $request->integer('branch_id');
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $branchId));
        }

        if ($request->filled('status')) {
            $status = (string) $request->string('status');
            if (! in_array($status, ['available', 'cleaning', 'maintenance'], true)) {
                return response()->json(['message' => 'Invalid status filter. Allowed values: available, cleaning, maintenance.'], 422);
            }
            $query->where('housekeeping_status', $status);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $products = $query->orderBy('name')->paginate($request->integer('per_page', 20));
        $products->getCollection()->transform(fn (Product $p) => $this->toListItem($p));

        return response()->json($products);
    }

    /**
     * PATCH /api/admin/room-housekeeping/{id}
     * Body:
     *  - status (required) : available|cleaning|maintenance — trạng thái ĐÍCH
     *  - note (nullable)   : ghi chú khi xác nhận đã dọn xong (chuyển cleaning -> available)
     *
     * Các bước chuyển hợp lệ:
     *  - available   -> cleaning    : đánh dấu cần dọn (RoomCleaningAction::markForCleaning) —
     *                                 CHỈ chủ đối tác/super_admin (đúng quy tắc canMarkForCleaning
     *                                 hiện có, luồng tự động là nguồn còn lại tạo trạng thái này).
     *  - cleaning    -> available   : xác nhận đã dọn xong (Product::confirmCleaned) — CHỈ chủ đối
     *                                 tác/super_admin hoặc nhân viên trực đúng chi nhánh sân này
     *                                 (RoomCleaningAction::canManageCleaning), bắt buộc có hồ sơ
     *                                 Employee liên kết.
     *  - *           -> maintenance : chuyển sang bảo trì — CHỈ chủ đối tác/super_admin (không có
     *                                 model method riêng, ghi thẳng cột).
     *  - maintenance -> available   : hết bảo trì — CHỈ chủ đối tác/super_admin.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Product::query();
        $this->scopeToVisibleRooms($query, $user);
        $product = $query->find($id);

        if (! $product) {
            return response()->json(['message' => 'Room not found.'], 404);
        }

        $data = $request->validate([
            'status' => 'required|in:available,cleaning,maintenance',
            'note'   => 'nullable|string|max:1000',
        ]);

        $target  = $data['status'];
        $current = $product->housekeeping_status;

        if ($target === $current) {
            return response()->json(['message' => 'Room is already in this status.'], 422);
        }

        if ($target === 'cleaning') {
            if ($current !== 'available') {
                return response()->json(['message' => 'Only a room currently available can be marked for cleaning.'], 422);
            }
            if (! $this->canMarkForCleaning($user)) {
                return response()->json(['message' => 'You do not have permission to mark this room for cleaning.'], 403);
            }
            $product->markForCleaning();
        } elseif ($target === 'available' && $current === 'cleaning') {
            if (! RoomCleaningAction::canManageCleaning($product)) {
                return response()->json(['message' => 'You do not have permission to confirm cleaning for this room.'], 403);
            }
            $employee = $user->employee;
            if (! $employee) {
                return response()->json(['message' => 'Your account has no linked employee profile — cannot confirm cleaning.'], 422);
            }
            $product->confirmCleaned($employee, $data['note'] ?? null);
        } elseif ($target === 'maintenance' || ($target === 'available' && $current === 'maintenance')) {
            if (! $this->canMarkForCleaning($user)) {
                return response()->json(['message' => 'You do not have permission to change the maintenance status of this room.'], 403);
            }
            $product->update(['housekeeping_status' => $target]);
        } else {
            return response()->json(['message' => "Cannot change status from {$current} to {$target}."], 422);
        }

        return response()->json(['data' => $this->toListItem($product->fresh(['categories']))]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function scopeToVisibleRooms($query, ?User $user): void
    {
        if (! $user) {
            return;
        }

        if (! $user->isSuperAdmin()) {
            $query->where('partner_id', $user->partner_id);
        }

        if ($user->isSuperAdmin() || $user->isPartnerOwner()) {
            return;
        }

        $employee = $user->employee;
        if ($employee && ! $employee->works_all_branches) {
            $branchIds = $employee->workBranches()->pluck('categories.id');
            $query->whereHas('categories', function ($q) use ($branchIds) {
                $q->where('category_type', 'product')->whereIn('categories.id', $branchIds);
            });
        }
    }

    // Đánh dấu "cần dọn" thủ công / chuyển bảo trì — chỉ chủ đối tác/super_admin, đúng quy tắc
    // private canMarkForCleaning() của RoomCleaningAction (không tái sử dụng được vì private ở đó).
    private function canMarkForCleaning(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isPartnerOwner();
    }

    private function toListItem(Product $product): array
    {
        $pendingLog     = $product->cleaningLogs()->whereNull('cleaned_at')->first();
        $lastCleanedLog = $product->cleaningLogs()->whereNotNull('cleaned_at')->first();

        return [
            'id'                     => $product->id,
            'name'                   => $product->name,
            'branch'                 => $product->categories->first()?->name,
            'housekeeping_status'    => $product->housekeeping_status,
            'marked_for_cleaning_at' => $pendingLog?->marked_for_cleaning_at,
            'last_cleaned_at'        => $lastCleanedLog?->cleaned_at,
            'last_cleaned_by'        => $lastCleanedLog?->employee?->name,
        ];
    }
}
