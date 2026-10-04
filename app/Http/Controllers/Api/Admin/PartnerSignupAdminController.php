<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\PartnerOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Super Admin: đăng ký đối tác CHỜ DUYỆT (khách mua gói trên website, được tặng dùng thử sau khi duyệt) — cùng logic với action
 * "Duyệt đăng ký"/"Từ chối" ở Filament → Đối tác.
 *
 * GET  /api/admin/partner-signups?status=pending|approved|rejected&search=
 * POST /api/admin/partner-signups/{partner}/approve   → tặng dùng thử, kích hoạt, tạo tài khoản + gửi email
 * POST /api/admin/partner-signups/{partner}/reject    {reason}
 * POST /api/admin/partner-signups/{partner}/resend-credentials → đặt lại mật khẩu và gửi email đăng nhập
 */
class PartnerSignupAdminController extends Controller
{
    public function __construct(private PartnerOnboardingService $onboarding) {}

    public function index(Request $request): JsonResponse
    {
        $this->superAdmin($request);
        $request->validate(['status' => ['nullable', 'in:pending,approved,rejected'], 'search' => ['nullable', 'string', 'max:100']]);

        $rows = Partner::query()->with(['subscription.plan'])->whereNotNull('onboarding_token')
            ->when($request->filled('status'), fn ($q) => $q->where('verification_status', $request->input('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest()->paginate(max(1, min((int) $request->input('per_page', 20), 100)));

        return response()->json([
            'data' => collect($rows->items())->map(fn (Partner $p) => $this->format($p))->values(),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total()],
        ]);
    }

    public function approve(Request $request, string $partner): JsonResponse
    {
        $this->superAdmin($request);
        $result = $this->onboarding->approveSignup(Partner::query()->findOrFail($partner), $request->user());

        return response()->json(['message' => 'Đã duyệt đăng ký và tặng dùng thử.', 'data' => $result]);
    }

    public function reject(Request $request, string $partner): JsonResponse
    {
        $this->superAdmin($request);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']], ['reason.required' => 'Vui lòng nhập lý do từ chối.']);
        $this->onboarding->rejectSignup(Partner::query()->findOrFail($partner), $data['reason'], $request->user());

        return response()->json(['message' => 'Đã từ chối đăng ký.']);
    }

    public function resendCredentials(Request $request, string $partner): JsonResponse
    {
        $this->superAdmin($request);

        return response()->json(['message' => 'Đã gửi lại thông tin đăng nhập.', 'data' => $this->onboarding->resendCredentials(Partner::query()->findOrFail($partner))]);
    }

    private function format(Partner $p): array
    {
        $plan = $p->signup_plan_id ? \App\Models\PartnerPlan::query()->find($p->signup_plan_id) : $p->subscription?->plan;

        return [
            'id'                  => $p->id,
            'name'                => $p->name,
            'representative_name' => $p->representative_name,
            'phone'               => $p->phone,
            'email'               => $p->email,
            'address'             => $p->address,
            'verification_status' => $p->verification_status,
            'awaiting_approval'   => $this->onboarding->awaitingSignupApproval($p),
            'has_account'         => $p->users()->exists(),
            'plan'                => $plan?->only(['id', 'slug', 'name']),
            'periods'             => $p->signup_periods,
            'landing_addon'       => (bool) ($p->subscription?->landing_addon ?? $p->signup_landing_addon),
            'subscription'        => $p->subscription ? ['status' => $p->subscription->state(), 'expires_at' => $p->subscription->expires_at?->toIso8601String()] : null,
            'created_at'          => $p->created_at?->toIso8601String(),
        ];
    }

    private function superAdmin(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Chỉ Super Admin được thực hiện thao tác này.');
    }
}
