<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Partner;
use App\Models\PartnerSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Cổng kiểm soát GÓI DỊCH VỤ: đối tác dùng đầy đủ chức năng khi gói còn hạn; hết hạn/bị huỷ thì bị khoá (API trả 402, trang quản trị
// chuyển về trang "Gói dịch vụ"). Miễn trừ: super admin, tài khoản không thuộc đối tác, đối tác nền tảng, và đối tác CHƯA có đăng ký
// gói (tạo tay trước khi có luồng mua gói).
class SubscriptionGate
{
    /** @var array<string, array{exempt: bool, sub: ?PartnerSubscription, locked: bool}> */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** @return array{exempt: bool, sub: ?PartnerSubscription, locked: bool} */
    public static function stateFor(User $user): array
    {
        $key = (string) $user->getKey() . '|' . (string) $user->partner_id;

        return self::$cache[$key] ??= self::compute($user);
    }

    private static function compute(User $user): array
    {
        $exempt = ['exempt' => true, 'sub' => null, 'locked' => false];

        if (! $user->partner_id || $user->isSuperAdmin()) {
            return $exempt;
        }

        $partner = Partner::withTrashed()->find($user->partner_id);
        if (! $partner || $partner->isPlatformPartner()) {
            return $exempt;
        }

        $sub = $partner->subscription()->with('plan')->first();
        if (! $sub) {
            return $exempt;
        }

        return ['exempt' => false, 'sub' => $sub, 'locked' => ! $sub->isUsable()];
    }

    /** Tài khoản đang đăng nhập có đang bị khoá do gói hết hạn/huỷ không (dùng trong view/Livewire). */
    public static function lockedCurrent(): bool
    {
        $user = auth()->user();

        return $user instanceof User && self::stateFor($user)['locked'];
    }

    /** Chặn request API admin khi gói hết hạn (402), trừ các nhóm luôn cho phép. Trả response lỗi, hoặc null nếu được phép. */
    public static function checkRequest(Request $request, User $user): ?JsonResponse
    {
        $state = self::stateFor($user);

        if ($state['exempt'] || ! $state['locked']) {
            return null;
        }

        $group = explode('/', trim($request->path(), '/'))[2] ?? '';
        if (in_array($group, config('subscription.always_allowed_api'), true)) {
            return null;
        }

        return response()->json([
            'code'       => 'SUBSCRIPTION_EXPIRED',
            'message'    => 'Gói dịch vụ đã hết hạn. Vui lòng thanh toán phí để tiếp tục sử dụng.',
            'expires_at' => $state['sub']?->expires_at?->toIso8601String(),
        ], 402);
    }
}
