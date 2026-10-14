<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Filament\Resources\PartnerPlanResource;
use App\Http\Controllers\Controller;
use App\Models\PartnerPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Ma trận tính năng theo gói đối tác (hàng = tính năng, cột = gói) — tương đương trang Filament "Tính năng theo gói". Chỉ super admin.
class PartnerFeatureMatrixController extends Controller
{
    // GET /api/admin/partner-plans/feature-matrix
    public function show(Request $request): JsonResponse
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        return response()->json(['data' => [
            'features' => collect(PartnerPlanResource::FEATURE_OPTIONS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'plans' => PartnerPlan::orderBy('sort_order')->get()->map(fn (PartnerPlan $p) => [
                'id' => $p->id, 'name' => $p->name, 'max_branches' => $p->max_branches,
                'features' => collect(PartnerPlanResource::FEATURE_OPTIONS)->mapWithKeys(fn ($label, $key) => [$key => $p->hasFeature($key)])->all(),
            ])->all(),
        ]]);
    }

    // PUT|PATCH /api/admin/partner-plans/feature-matrix — body: plans: [{id, max_branches?: int|null, features: {key: bool}}]. Lưu tất cả hoặc không lưu gì.
    public function update(Request $request): JsonResponse
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $keys = array_keys(PartnerPlanResource::FEATURE_OPTIONS);
        $data = $request->validate([
            'plans' => 'required|array|min:1',
            'plans.*.id' => 'required|integer|exists:partner_plans,id',
            'plans.*.max_branches' => 'sometimes|nullable|integer|min:0|max:1000',
            'plans.*.features' => 'sometimes|array',
            'plans.*.features.*' => 'boolean',
        ]);

        foreach ($data['plans'] as $row) {
            foreach (array_keys($row['features'] ?? []) as $k) {
                if (! in_array($k, $keys, true)) {
                    return response()->json(['message' => "Unknown feature: {$k}.", 'errors' => ['plans' => ["Unknown feature: {$k}."]]], 422);
                }
            }
        }

        DB::transaction(function () use ($data) {
            foreach ($data['plans'] as $row) {
                $plan = PartnerPlan::findOrFail($row['id']);
                $update = [];
                if (array_key_exists('features', $row)) {
                    // Chỉ đổi các tính năng được gửi; giữ nguyên các tính năng còn lại.
                    $current = collect(PartnerPlanResource::FEATURE_OPTIONS)->keys()->filter(fn ($k) => $plan->hasFeature($k))->all();
                    $on = collect($row['features'])->filter()->keys()->all();
                    $off = collect($row['features'])->reject()->keys()->all();
                    $update['features'] = array_values(array_unique(array_diff(array_merge($current, $on), $off)));
                }
                if (array_key_exists('max_branches', $row)) {
                    $update['max_branches'] = $row['max_branches'];
                }
                if ($update) {
                    $plan->update($update);
                }
            }
        });

        return $this->show($request);
    }

    private function deny(Request $request): ?JsonResponse
    {
        return $request->user()->isSuperAdmin() ? null : response()->json(['message' => 'Forbidden.'], 403);
    }
}
