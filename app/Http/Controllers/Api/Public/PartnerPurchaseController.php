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
 * ĐĂNG KÝ ĐỐI TÁC = MUA GÓI (không hồ sơ giấy tờ/hợp đồng). Khách chọn gói + số tháng (+ trang landing nếu muốn), nhập thông tin
 * liên hệ → hệ thống tạo hồ sơ chờ duyệt (lần đầu được tặng dùng thử) hoặc đơn thanh toán PayOS (QR/link). Thanh toán xong (webhook
 * PayOS hoặc Super Admin xác nhận) hoặc Super Admin duyệt → kích hoạt gói, tạo tài khoản chủ đối tác và gửi email đăng nhập.
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
            'plan_id'               => ['nullable', 'integer', 'exists:partner_plans,id'],
            'periods'               => ['nullable', 'integer', Rule::in(config('subscription.period_options', [1, 3, 6, 9, 12]))],
            'landing_addon'         => ['nullable', 'boolean'],
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

        // Đăng ký lần đầu (được tặng dùng thử) không cần chọn gói: dùng gói mặc định/đầu tiên đang bán.
        $plan = filled($data['plan_id'] ?? null) ? PartnerPlan::query()->findOrFail($data['plan_id']) : $this->onboarding->defaultPlan();
        abort_if(! $plan, 422, 'Chưa có gói đang bán. Vui lòng liên hệ ' . config('app.name') . '.');
        abort_unless($plan->is_active, 422, 'Gói này hiện không được bán.');
        $data['periods'] = (int) ($data['periods'] ?? 1);
        $landing = (bool) ($data['landing_addon'] ?? false);
        abort_if($landing && ! $plan->sellsLandingAddon(), 422, 'Gói này không bán thêm trang landing (đã gồm sẵn hoặc chưa mở bán).');

        $data['address'] = $this->onboarding->composeAddress($data);
        $result = $this->onboarding->createPurchasePartner($data);

        // Lần đầu (đủ điều kiện tặng dùng thử): chỉ XÁC NHẬN ĐĂNG KÝ — ghi nhận gói khách chọn, KHÔNG tạo đơn thanh toán/QR; Super Admin duyệt xong mới tặng dùng thử.
        // Không đủ điều kiện (đã từng đăng ký / tính năng tắt): tạo đơn thanh toán + QR, phải thanh toán mới được dùng.
        $pendingApproval = $this->onboarding->awaitingSignupApproval($result['partner']);
        if ($pendingApproval) {
            $result['partner']->update(['signup_plan_id' => $plan->id, 'signup_periods' => $data['periods'], 'signup_landing_addon' => $landing]);
            $payment = null;
        } else {
            $payment = $this->subscriptions->createCheckout($result['partner'], $plan, $data['periods'], false, 'payos', $landing);
        }

        return response()->json([
            'message' => $pendingApproval
                ? 'Đã xác nhận đăng ký. Sau khi ' . config('app.name') . ' duyệt, tài khoản dùng thử và mật khẩu sẽ được gửi về email của bạn.'
                : 'Đã tạo đơn mua gói. Quét QR hoặc mở link để thanh toán; sau khi thanh toán, tài khoản đăng nhập sẽ được gửi về email của bạn.',
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
