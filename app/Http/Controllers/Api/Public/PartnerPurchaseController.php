<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerPlan;
use App\Models\PartnerSubscription;
use App\Models\Province;
use App\Models\SubscriptionPayment as Pay;
use App\Models\Ward;
use App\Services\PartnerOnboardingService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ĐĂNG KÝ ĐỐI TÁC (không hồ sơ giấy tờ/hợp đồng, không chọn gói): khách chỉ nhập thông tin liên hệ → hồ sơ chờ Super Admin duyệt →
 * duyệt xong: tặng tháng đầu dùng thử (nếu đủ điều kiện), tạo tài khoản chủ đối tác và gửi email đăng nhập. Hết thời gian dùng thử,
 * đối tác tự chọn gói và thanh toán trong trang quản trị (Gói dịch vụ).
 *
 * GET  /api/public/partner-plans            → gói đang bán + các kỳ mua và số tiền
 * POST /api/public/partner-purchase         → đăng ký / tạo đơn mua gói → {purchase_token, stage, payment...}
 * GET  /api/public/partner-purchase/{token} → trạng thái đơn (app/web gọi lại để biết đã thanh toán / được duyệt chưa)
 */
class PartnerPurchaseController extends Controller
{
    public function __construct(
        private readonly PartnerOnboardingService $onboarding,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    public function plans(): JsonResponse
    {
        $plans = PartnerPlan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        return response()->json([
            'data' => $plans->map(fn (PartnerPlan $p) => $p->toApi())->values(),
            'meta' => ['signup_trial_months' => (int) config('partner_flow.signup_trial_months', 0)],
        ]);
    }

    /** Địa chỉ hành chính cho form đăng ký: GET /api/public/partner-plans/address?province_code= */
    public function address(Request $request): JsonResponse
    {
        $request->validate(['province_code' => ['nullable', 'integer']]);

        if ($request->filled('province_code')) {
            return response()->json(['data' => Ward::query()->where('province_code', $request->integer('province_code'))->orderBy('name')->get(['code', 'name'])]);
        }

        return response()->json(['data' => Province::query()->orderBy('name')->get(['code', 'name'])]);
    }

    public function purchase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'             => ['required', 'string', 'max:255'],
            'phone'                 => ['required', 'string', 'regex:/^(0|\+84)[0-9]{9}$/'],
            'email'                 => ['required', 'email', 'max:255'],
            'business_name'         => ['required', 'string', 'max:255'],
            'address'               => ['required_without:address_street', 'nullable', 'string', 'max:500'],
            'address_street'        => ['required_without:address', 'nullable', 'string', 'max:255'],
            'address_province_code' => ['required_with:address_street', 'nullable', 'integer', Rule::exists(Province::class, 'code')],
            'address_ward_code'     => ['required_with:address_street', 'nullable', 'integer', Rule::exists(Ward::class, 'code')],
            'address_unit'          => ['nullable', 'string', 'max:100'],
            'address_building'      => ['nullable', 'string', 'max:150'],
            'postal_code'           => ['nullable', 'regex:/^[0-9]{5,6}$/'],
        ], [
            'phone.regex'                     => 'Số điện thoại không hợp lệ.',
            'postal_code.regex'               => 'Mã bưu điện gồm 5–6 chữ số.',
            'address_street.required_without' => 'Vui lòng nhập số nhà, tên đường/phố.',
            'address.required_without'        => 'Vui lòng nhập địa chỉ.',
        ], PartnerOnboardingService::LABELS);

        // Đăng ký CHỈ nhập thông tin (không chọn gói, không thanh toán): hồ sơ chờ Super Admin duyệt; duyệt xong mới tặng tháng đầu (nếu đủ điều kiện)
        // và gửi tài khoản đăng nhập. Hết thời gian dùng thử, đối tác tự chọn gói và thanh toán trong trang quản trị (Gói dịch vụ).
        $data['address'] = $this->onboarding->composeAddress($data);
        $result = $this->onboarding->createPurchasePartner($data);
        $pendingApproval = $this->onboarding->awaitingSignupApproval($result['partner']);
        $payment = null;

        return response()->json([
            'message' => $pendingApproval
                ? 'Đã xác nhận đăng ký. Sau khi ' . config('app.name') . ' duyệt, tài khoản và mật khẩu sẽ được gửi về email của bạn.'
                : 'Đã ghi nhận đăng ký. Hồ sơ đã được duyệt trước đó — vui lòng kiểm tra email hoặc liên hệ ' . config('app.name') . '.',
            'data'    => ['purchase_token' => $result['token'], ...$this->status($result['partner']->fresh(), $payment)],
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => $this->status($this->onboarding->findByToken($token))]);
    }

    private function status(Partner $partner, ?Pay $payment = null): array
    {
        $payment ??= Pay::query()->with('plan')->where('partner_id', $partner->id)->latest('id')->first();
        $paid = $payment?->status === Pay::STATUS_PAID;
        $hasAccount = $partner->users()->exists();
        $subscription = $partner->subscription;

        return [
            'stage'        => match (true) {
                $paid && $hasAccount => 'active',
                $paid                => 'paid',
                $hasAccount && $subscription?->state() === PartnerSubscription::STATE_TRIAL => 'trial',
                $partner->verification_status === 'rejected' => 'rejected',
                $this->onboarding->awaitingSignupApproval($partner) && ! in_array($payment?->status, [Pay::STATUS_CANCELLED, Pay::STATUS_EXPIRED], true) => 'pending_approval',
                $payment?->status === Pay::STATUS_CANCELLED => 'cancelled',
                $payment?->status === Pay::STATUS_EXPIRED   => 'expired',
                default              => 'pending_payment',
            },
            'partner'      => $partner->only(['name', 'phone', 'email', 'address']),
            'payment'      => $payment?->loadMissing('plan')->toApi(),
            'subscription' => $subscription ? [
                'expires_at'    => $subscription->expires_at?->toIso8601String(),
                'status'        => $subscription->state(),
                'is_trial'      => (bool) $subscription->is_trial,
                'landing_addon' => (bool) $subscription->landing_addon,
                'days_left'     => $subscription->daysLeft(),
            ] : null,
            'account'      => [
                'created'   => $hasAccount,
                'email'     => $hasAccount ? $partner->email : null,
                'login_url' => $hasAccount ? $this->onboarding->loginUrl() : null,
            ],
        ];
    }
}
