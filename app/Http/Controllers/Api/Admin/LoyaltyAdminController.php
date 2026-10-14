<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Quản trị điểm thưởng: xem sổ cái và điều chỉnh tay. Khách hàng là dữ liệu dùng chung toàn hệ thống nên chỉ super admin (không cấp cho đối tác).
class LoyaltyAdminController extends Controller
{
    // GET /api/admin/loyalty — ?customer_id=, ?type=, ?per_page=
    public function index(Request $request): JsonResponse
    {
        $page = LoyaltyTransaction::query()->with('customer:id,fullname,phone')
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->query('customer_id')))
            ->when(in_array($request->query('type'), ['earn', 'revoke', 'redeem', 'adjust'], true), fn ($q) => $q->where('type', $request->query('type')))
            ->orderByDesc('id')->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $page->through(fn (LoyaltyTransaction $t) => [
            'id' => $t->id, 'customer_id' => $t->customer_id, 'customer_name' => $t->customer?->fullname, 'customer_phone' => $t->customer?->phone,
            'type' => $t->type, 'points' => $t->points, 'balance_after' => $t->balance_after, 'order_id' => $t->order_id,
            'coupon_code' => $t->coupon_code, 'note' => $t->note, 'created_at' => $t->created_at?->toIso8601String(),
        ]);

        return response()->json($page);
    }

    // POST /api/admin/loyalty/adjust — body: customer_id, points (≠0, âm = trừ), note
    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|string', 'points' => 'required|integer|not_in:0|between:-10000000,10000000', 'note' => 'required|string|max:255',
        ]);

        $customer = Customer::find($data['customer_id']);
        if (! $customer) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        $result = app(LoyaltyService::class)->adjust($customer, (int) $data['points'], $data['note'], $request->user());
        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => 'Points adjusted.', 'data' => ['points' => $result['transaction']->points, 'balance_after' => $result['transaction']->balance_after]], 201);
    }
}
