<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPackage;
use App\Models\PackagePlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;

/**
 * Quản lý GÓI NHIỀU BUỔI bán ra (App\Models\PackagePlan) — đúng phạm vi
 * App\Filament\Resources\PackagePlanResource, cùng quy tắc phạm vi loại trừ lẫn nhau (đúng 1
 * trong 3: 1 sân cụ thể / một số sân trong chi nhánh / cả chi nhánh) và cùng cách lọc theo chi
 * nhánh đang hiệu lực (User::effectiveBranchIds()/visibleProductCategoryIds()). Khách ĐÃ MUA gói
 * (CustomerPackage) quản lý riêng ở CustomerPackageController — resource này chỉ cấu hình gói bán.
 *
 * GET    /api/admin/package-plans        → ?category_id=&product_id=&is_active=&search=&per_page=
 * GET    /api/admin/package-plans/{id}
 * POST   /api/admin/package-plans        → tạo (scope_type: product|selected|category)
 * PUT    /api/admin/package-plans/{id}   → sửa
 * DELETE /api/admin/package-plans/{id}   → xoá
 * POST   /api/admin/package-plans/{id}/sell
 *                                        → "Bán gói" tay cho khách walk-in — thu tiền NGOÀI hệ
 *                                          thống (tiền mặt/chuyển khoản trực tiếp), chỉ ghi nhận
 *                                          lại, y hệt action "Bán gói" trên Filament.
 */
class PackagePlanController extends Controller
{
    /** Danh mục chi nhánh/sân user hiện tại được phép thấy — cùng nguồn PackagePlanResource dùng
     *  cho cả getEloquentQuery() (lọc danh sách) lẫn form (lọc Select sân/chi nhánh). */
    private function scopedCategoryIds(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [];
        }

        $branchIds = $user->effectiveBranchIds();
        if (empty($branchIds)) {
            return [];
        }

        return $user->visibleProductCategoryIds($branchIds);
    }

    private function baseQuery(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $query = PackagePlan::query();

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $categoryIds = $this->scopedCategoryIds($user);

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
        $query = $this->baseQuery($request)->with(['product', 'category']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('product_id')) {
            $query->where(function ($q) use ($request) {
                $productId = $request->input('product_id');
                $q->where('product_id', $productId)
                    ->orWhereJsonContains('product_ids', $productId);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        $plans = $query->withCount('customerPackages')
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => collect($plans->items())->map(fn (PackagePlan $p) => $this->toArray($p)),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page'    => $plans->lastPage(),
                'per_page'     => $plans->perPage(),
                'total'        => $plans->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = $this->baseQuery($request)->with(['product', 'category'])->find($id);

        if (! $plan) {
            return response()->json(['message' => 'Package plan not found.'], 404);
        }

        return response()->json(['data' => $this->toArray($plan, withStats: true)]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'scope_type'         => ['required', Rule::in(['product', 'selected', 'category'])],
            'product_id'         => ['required_if:scope_type,product', 'nullable', 'string', 'exists:products,id'],
            'category_id'        => ['required_if:scope_type,selected,category', 'nullable', 'integer', 'exists:categories,id'],
            'product_ids'        => ['required_if:scope_type,selected', 'nullable', 'array', 'min:1'],
            'product_ids.*'      => ['string', 'exists:products,id'],
            'name'               => ['required', 'string', 'max:150'],
            'sessions_count'     => ['required', 'integer', 'min:1'],
            'price'              => ['required', 'numeric', 'min:0'],
            'validity_days'      => ['nullable', 'integer', 'min:1'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $scopeCheck = $this->assertScopeAllowed($user, $data);
        if ($scopeCheck) {
            return $scopeCheck;
        }

        $data = $this->enforceScopeExclusivity($data);
        $data['partner_id'] = $this->resolvePartnerIdFromScope($data);
        $data['is_active']  = $data['is_active'] ?? true;

        $plan = PackagePlan::create($data);

        return response()->json(['data' => $this->toArray($plan->load(['product', 'category']))], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $plan = $this->baseQuery($request)->find($id);

        if (! $plan) {
            return response()->json(['message' => 'Package plan not found.'], 404);
        }

        /** @var User $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'scope_type'         => ['sometimes', 'required', Rule::in(['product', 'selected', 'category'])],
            'product_id'         => ['nullable', 'string', 'exists:products,id'],
            'category_id'        => ['nullable', 'integer', 'exists:categories,id'],
            'product_ids'        => ['nullable', 'array'],
            'product_ids.*'      => ['string', 'exists:products,id'],
            'name'               => ['sometimes', 'required', 'string', 'max:150'],
            'sessions_count'     => ['sometimes', 'required', 'integer', 'min:1'],
            'price'              => ['sometimes', 'required', 'numeric', 'min:0'],
            'validity_days'      => ['nullable', 'integer', 'min:1'],
            'is_active'          => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (array_key_exists('scope_type', $data)) {
            $scopeCheck = $this->assertScopeAllowed($user, $data);
            if ($scopeCheck) {
                return $scopeCheck;
            }

            $data = $this->enforceScopeExclusivity($data);
            $data['partner_id'] = $this->resolvePartnerIdFromScope($data) ?? $plan->partner_id;
        }

        $plan->update($data);

        return response()->json(['data' => $this->toArray($plan->fresh(['product', 'category']))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $plan = $this->baseQuery($request)->find($id);

        if (! $plan) {
            return response()->json(['message' => 'Package plan not found.'], 404);
        }

        $plan->delete();

        return response()->json(['message' => 'Package plan deleted.']);
    }

    /**
     * "Bán gói" tay cho khách walk-in — thu tiền NGOÀI hệ thống, chỉ ghi nhận lại, y hệt action
     * cùng tên trên Filament (PackagePlanResource::table()). Định danh bằng SỐ ĐIỆN THOẠI (không
     * bắt buộc chọn Customer) — xem docblock CustomerPackage::findValidForPhoneAndProduct().
     */
    public function sell(Request $request, int $id): JsonResponse
    {
        $plan = $this->baseQuery($request)->find($id);

        if (! $plan) {
            return response()->json(['message' => 'Package plan not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'buyer_name'  => ['required', 'string', 'max:150'],
            'buyer_phone' => ['required', 'string', 'max:20'],
            'customer_id' => ['sometimes', 'nullable', 'string', 'size:36', 'exists:customers,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        /** @var User $user */
        $user = $request->user();

        $customerPackage = CustomerPackage::create([
            'package_plan_id'    => $plan->id,
            'partner_id'         => $plan->partner_id,
            'customer_id'        => $request->input('customer_id'),
            'buyer_phone'        => $request->input('buyer_phone'),
            'buyer_name'         => $request->input('buyer_name'),
            'product_id'         => $plan->product_id,
            'category_id'        => $plan->category_id,
            'product_ids'        => $plan->product_ids,
            'name'               => $plan->name,
            'sessions_total'     => $plan->sessions_count,
            'sessions_remaining' => $plan->sessions_count,
            'price_paid'         => $plan->price,
            'purchased_at'       => now(),
            'expires_at'         => $plan->validity_days ? now()->addDays($plan->validity_days) : null,
            'sale_channel'       => 'admin',
            'created_by'         => $user->id,
        ]);

        return response()->json(['message' => 'Package sold successfully.', 'data' => [
            'id'                 => $customerPackage->id,
            'buyer_name'         => $customerPackage->buyer_name,
            'buyer_phone'        => $customerPackage->buyer_phone,
            'sessions_remaining' => $customerPackage->sessions_remaining,
            'expires_at'         => $customerPackage->expires_at?->toIso8601String(),
        ]], 201);
    }

    /** Ép ĐÚNG 1 trong 3 phạm vi loại trừ lẫn nhau — cùng logic
     *  App\Filament\Resources\PackagePlanResource\Concerns\EnforcesPackagePlanScope, viết lại ở
     *  đây vì trait đó nằm trong namespace Filament Page, không tiện dùng chung cho API controller. */
    private function enforceScopeExclusivity(array $data): array
    {
        $scope = $data['scope_type'] ?? 'product';
        unset($data['scope_type']);

        $data['product_id']  = $scope === 'product' ? ($data['product_id'] ?? null) : null;
        $data['category_id'] = in_array($scope, ['category', 'selected'], true) ? ($data['category_id'] ?? null) : null;
        $data['product_ids'] = $scope === 'selected' ? array_values($data['product_ids'] ?? []) : null;

        return $data;
    }

    /** partner_id suy từ phạm vi đã chọn — cùng logic CreatePackagePlan::mutateFormDataBeforeCreate(). */
    private function resolvePartnerIdFromScope(array $data): ?string
    {
        if (! empty($data['product_id'])) {
            return Product::find($data['product_id'])?->partner_id;
        }

        if (! empty($data['category_id'])) {
            return Category::find($data['category_id'])?->partner_id;
        }

        if (! empty($data['product_ids'][0] ?? null)) {
            return Product::find($data['product_ids'][0])?->partner_id;
        }

        return null;
    }

    /** Chặn admin đối tác tạo/sửa gói cho sân/chi nhánh KHÔNG thuộc phạm vi mình được thấy — cùng
     *  danh sách category id dùng để lọc index(), tránh admin gửi thẳng product_id/category_id lạ
     *  ngoài phạm vi qua request body (index() chỉ lọc DANH SÁCH, không tự chặn được store()/update()). */
    private function assertScopeAllowed(User $user, array $data): ?JsonResponse
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $categoryIds = $this->scopedCategoryIds($user);

        $scope = $data['scope_type'] ?? 'product';

        if ($scope === 'product' && ! empty($data['product_id'])) {
            $allowed = Product::where('id', $data['product_id'])
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds))
                ->exists();

            if (! $allowed) {
                return response()->json(['message' => 'You are not allowed to manage a package plan for this product.'], 403);
            }
        }

        if (in_array($scope, ['category', 'selected'], true) && ! empty($data['category_id'])) {
            if (! in_array((int) $data['category_id'], $categoryIds, true)) {
                return response()->json(['message' => 'You are not allowed to manage a package plan for this branch.'], 403);
            }
        }

        return null;
    }

    private function toArray(PackagePlan $plan, bool $withStats = false): array
    {
        $data = [
            'id'             => $plan->id,
            'scope_type'     => $plan->scopeType(),
            'product_id'     => $plan->product_id,
            'product_name'   => $plan->product?->name,
            'category_id'    => $plan->category_id,
            'category_name'  => $plan->category?->name,
            'product_ids'    => $plan->product_ids,
            'name'           => $plan->name,
            'sessions_count' => $plan->sessions_count,
            'price'          => (int) $plan->price,
            'validity_days'  => $plan->validity_days,
            'is_active'      => $plan->is_active,
            'sold_count'     => $plan->customer_packages_count ?? $plan->customerPackages()->count(),
            'created_at'     => $plan->created_at?->toIso8601String(),
        ];

        if ($withStats) {
            $data['per_session_list_price'] = $plan->perSessionListPrice();
            $data['savings_percent']        = $plan->savingsPercent();
        }

        return $data;
    }
}
