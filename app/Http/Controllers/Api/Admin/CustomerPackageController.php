<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Quản lý KHÁCH ĐÃ MUA gói nhiều buổi (App\Models\CustomerPackage) — đúng phạm vi
 * App\Filament\Resources\CustomerPackageResource. KHÔNG có store() — gói chỉ phát sinh qua khách
 * tự mua (order_type=package_purchase, xem PackageOrderController) hoặc admin "Bán gói" (xem
 * PackagePlanController::sell()), tránh trùng logic tạo bản ghi ở nhiều nơi. Resource này chỉ để
 * xem + sửa tay (bù/trừ buổi, gia hạn) + xem lịch sử dùng.
 *
 * GET /api/admin/customer-packages              → ?search=&sale_channel=&is_valid=&per_page=
 * GET /api/admin/customer-packages/{id}
 * PUT /api/admin/customer-packages/{id}         → sửa buyer_name/buyer_phone/sessions_remaining/expires_at
 * GET /api/admin/customer-packages/{id}/redemptions
 *                                                → lịch sử từng lượt trừ buổi (PackageRedemption)
 */
class CustomerPackageController extends Controller
{
    /** BelongsToPartner trên CustomerPackage chỉ tự áp dụng global scope trong context Filament
     *  panel (AdminPanelContext::isActive()) — ở context API thường phải tự lọc lại partner_id +
     *  chi nhánh đang hiệu lực, cùng cách CorporateAccountController đã làm. */
    private function baseQuery(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $query = CustomerPackage::query();

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->partner_id) {
            $query->where('partner_id', $user->partner_id);
        } else {
            return $query->whereRaw('1 = 0');
        }

        $branchIds = $user->effectiveBranchIds();
        if (empty($branchIds)) {
            return $query;
        }

        $categoryIds = $user->visibleProductCategoryIds($branchIds);
        if (empty($categoryIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($categoryIds) {
            $q->whereIn('category_id', $categoryIds)
                ->orWhereHas('product.categories', fn ($q2) => $q2->whereIn('categories.id', $categoryIds));
        });
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request)->with(['product', 'category', 'partner']);

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('buyer_name', 'like', "%{$term}%")
                    ->orWhere('buyer_phone', 'like', "%{$term}%");
            });
        }

        if ($request->filled('sale_channel')) {
            $query->where('sale_channel', $request->input('sale_channel'));
        }

        if ($request->filled('is_valid')) {
            $valid = $request->boolean('is_valid');
            $query->where(function ($q) use ($valid) {
                if ($valid) {
                    $q->where('sessions_remaining', '>', 0)
                        ->where(fn ($q2) => $q2->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                } else {
                    $q->where('sessions_remaining', '<=', 0)
                        ->orWhere(fn ($q2) => $q2->whereNotNull('expires_at')->where('expires_at', '<=', now()));
                }
            });
        }

        $packages = $query->orderByDesc('purchased_at')->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => collect($packages->items())->map(fn (CustomerPackage $p) => $this->toArray($p)),
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page'    => $packages->lastPage(),
                'per_page'     => $packages->perPage(),
                'total'        => $packages->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $package = $this->baseQuery($request)->with(['product', 'category', 'partner', 'customer'])->find($id);

        if (! $package) {
            return response()->json(['message' => 'Customer package not found.'], 404);
        }

        return response()->json(['data' => $this->toArray($package, withRedemptions: true)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $package = $this->baseQuery($request)->find($id);

        if (! $package) {
            return response()->json(['message' => 'Customer package not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'buyer_name'         => ['sometimes', 'required', 'string', 'max:150'],
            'buyer_phone'        => ['sometimes', 'required', 'string', 'max:20'],
            // Sửa tay khi cần bù/trừ buổi thủ công — mọi thay đổi qua đặt sân bình thường KHÔNG
            // cần sửa ở đây (đi qua CustomerPackage::redeem()/refundRedemptionsForOrder()), cùng
            // helperText đang hiện trên Filament.
            'sessions_remaining' => ['sometimes', 'required', 'integer', 'min:0'],
            'expires_at'         => ['sometimes', 'nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $package->update($validator->validated());

        return response()->json(['data' => $this->toArray($package->fresh(['product', 'category']))]);
    }

    public function redemptions(Request $request, int $id): JsonResponse
    {
        $package = $this->baseQuery($request)->find($id);

        if (! $package) {
            return response()->json(['message' => 'Customer package not found.'], 404);
        }

        $redemptions = $package->redemptions()
            ->with(['orderItem', 'order'])
            ->orderByDesc('redeemed_at')
            ->get()
            ->map(fn ($r) => [
                'id'           => $r->id,
                'redeemed_at'  => $r->redeemed_at?->toIso8601String(),
                'refunded_at'  => $r->refunded_at?->toIso8601String(),
                'order_code'   => $r->order?->order_code,
                'item_name'    => $r->orderItem?->name,
            ]);

        return response()->json(['data' => $redemptions]);
    }

    private function toArray(CustomerPackage $package, bool $withRedemptions = false): array
    {
        $data = [
            'id'                 => $package->id,
            'package_plan_id'    => $package->package_plan_id,
            'name'               => $package->name,
            'buyer_name'         => $package->buyer_name,
            'buyer_phone'        => $package->buyer_phone,
            'customer_id'        => $package->customer_id,
            'scope_label'        => $package->category_id
                ? ('Cả chi nhánh: ' . ($package->category?->name ?? '—'))
                : ($package->product?->name ?? '—'),
            'partner_name'       => $package->partner?->name,
            'sessions_total'     => $package->sessions_total,
            'sessions_remaining' => $package->sessions_remaining,
            'sale_channel'       => $package->sale_channel,
            'is_valid'           => $package->isValid(),
            'purchased_at'       => $package->purchased_at?->toIso8601String(),
            'expires_at'         => $package->expires_at?->toIso8601String(),
        ];

        if ($withRedemptions) {
            $data['redemptions'] = $package->redemptions()
                ->with(['orderItem', 'order'])
                ->orderByDesc('redeemed_at')
                ->get()
                ->map(fn ($r) => [
                    'id'          => $r->id,
                    'redeemed_at' => $r->redeemed_at?->toIso8601String(),
                    'refunded_at' => $r->refunded_at?->toIso8601String(),
                    'order_code'  => $r->order?->order_code,
                    'item_name'   => $r->orderItem?->name,
                ]);
        }

        return $data;
    }
}
