<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Ví điểm thưởng của khách: xem số dư + quy tắc, lịch sử, đổi điểm lấy mã giảm giá riêng (chỉ chủ tài khoản dùng được).
class WalletController extends Controller
{
    // GET /api/wallet
    public function show(): JsonResponse
    {
        /** @var Customer $c */
        $c = auth('sanctum')->user()->fresh();

        $points = (int) $c->loyalty_points;
        $value = (int) config('loyalty.point_value_vnd');

        return response()->json(['data' => [
            'points' => $points, 'points_value_vnd' => $points * $value,
            'rules' => [
                'earn_vnd_per_point' => (int) config('loyalty.earn_vnd_per_point'), 'point_value_vnd' => $value,
                'min_redeem_points' => (int) config('loyalty.min_redeem_points'), 'coupon_days' => (int) config('loyalty.coupon_days'),
            ],
        ]]);
    }

    // GET /api/wallet/transactions — ?type=earn|revoke|redeem|adjust, ?per_page=
    public function transactions(Request $request): JsonResponse
    {
        $page = LoyaltyTransaction::query()->where('customer_id', auth('sanctum')->id())
            ->when(in_array($request->query('type'), ['earn', 'revoke', 'redeem', 'adjust'], true), fn ($q) => $q->where('type', $request->query('type')))
            ->orderByDesc('id')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $page->through(fn (LoyaltyTransaction $t) => [
            'id' => $t->id, 'type' => $t->type, 'points' => $t->points, 'balance_after' => $t->balance_after,
            'coupon_code' => $t->coupon_code, 'note' => $t->note, 'created_at' => $t->created_at?->toIso8601String(),
        ]);

        return response()->json($page);
    }

    // POST /api/wallet/redeem — body: points. Trả về mã giảm giá riêng (dùng 1 lần, có hạn).
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate(['points' => 'required|integer|min:1|max:10000000']);

        $result = app(LoyaltyService::class)->redeem(auth('sanctum')->user(), (int) $data['points']);
        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $coupon = $result['coupon'];

        return response()->json(['message' => 'Points redeemed.', 'data' => [
            'coupon_code' => $coupon->code, 'discount_vnd' => (int) $coupon->value, 'expires_at' => $coupon->end_at?->toIso8601String(),
            'points_left' => (int) $result['transaction']->balance_after,
        ]], 201);
    }
}
