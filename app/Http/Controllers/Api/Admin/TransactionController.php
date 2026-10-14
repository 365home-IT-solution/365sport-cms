<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Sổ giao dịch tiền vào/ra theo đơn đặt sân để đối soát. Mỗi sự kiện tiền là MỘT dòng (không cộng trùng một đơn nhiều lần):
//   deposit   = tiền cọc đã nhận (deposit_paid_at)
//   remaining = phần còn lại sau cọc (remaining_paid_at)
//   payment   = thanh toán một lần (đơn không cọc, paid_at)
//   extra     = thu thêm phát sinh (extra_charge_paid_at)
//   refund    = đã hoàn tiền cho khách (extra_refund_paid_at, số âm)
// Đối tác chỉ thấy giao dịch của đơn thuộc mình; super_admin lọc theo partner_id.
class TransactionController extends Controller
{
    private const TYPES = ['deposit', 'remaining', 'payment', 'extra', 'refund'];

    // GET /api/admin/transactions — ?type=, ?date_from=, ?date_to=, ?search= (mã đơn/tên/SĐT), ?partner_id= (super_admin), ?per_page=
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'sometimes|in:' . implode(',', self::TYPES),
            'date_from' => 'sometimes|date_format:Y-m-d', 'date_to' => 'sometimes|date_format:Y-m-d',
            'search' => 'sometimes|string|max:100', 'partner_id' => 'sometimes|uuid', 'per_page' => 'sometimes|integer|min:1|max:200',
        ]);
        /** @var User $user */
        $user = $request->user();
        $partnerId = $user->isSuperAdmin() ? ($data['partner_id'] ?? null) : $user->partner_id;
        if (! $user->isSuperAdmin() && ! $partnerId) {
            return response()->json(['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 0, 'money_in' => 0, 'money_out' => 0, 'net' => 0]]);
        }

        $base = fn (string $type, string $at, string $amount, ?string $where = null, string $sign = '') => DB::table('orders')
            ->selectRaw("id as order_id, order_code, buyer_name, buyer_phone, partner_id, payment_method as method, '{$type}' as type, {$at} as at, {$sign}({$amount}) as amount")
            ->whereRaw("{$at} IS NOT NULL")
            ->whereRaw($where ?? '1=1')
            ->when($partnerId, fn ($q) => $q->where('partner_id', $partnerId));

        $types = isset($data['type']) ? [$data['type']] : self::TYPES;
        $parts = [
            'deposit' => fn () => $base('deposit', 'deposit_paid_at', 'COALESCE(deposit_paid_amount, 0)'),
            'remaining' => fn () => $base('remaining', 'remaining_paid_at', 'COALESCE(full_amount, amount, 0) - COALESCE(deposit_paid_amount, 0)'),
            'payment' => fn () => $base('payment', 'paid_at', 'COALESCE(amount, full_amount, 0)', 'deposit_paid_at IS NULL'),
            'extra' => fn () => $base('extra', 'extra_charge_paid_at', 'COALESCE(extra_charge_amount, 0)'),
            'refund' => fn () => $base('refund', 'extra_refund_paid_at', 'COALESCE(extra_refund_amount, 0)', null, '-'),
        ];

        $union = null;
        foreach ($types as $t) {
            $q = $parts[$t]();
            $union = $union ? $union->unionAll($q) : $q;
        }

        $rows = DB::query()->fromSub($union, 't')
            ->when(isset($data['date_from']), fn ($q) => $q->where('at', '>=', $data['date_from'] . ' 00:00:00'))
            ->when(isset($data['date_to']), fn ($q) => $q->where('at', '<=', $data['date_to'] . ' 23:59:59'))
            ->when(isset($data['search']), function ($q) use ($data) {
                $s = '%' . $data['search'] . '%';
                $q->where(fn ($w) => $w->where('order_code', 'like', $s)->orWhere('buyer_name', 'like', $s)->orWhere('buyer_phone', 'like', $s));
            });

        $totals = (clone $rows)->selectRaw("COALESCE(SUM(CASE WHEN amount > 0 THEN amount END), 0) as money_in, COALESCE(SUM(CASE WHEN amount < 0 THEN -amount END), 0) as money_out, COUNT(*) as entries")->first();
        $page = $rows->orderByDesc('at')->orderByDesc('order_id')->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => collect($page->items())->map(fn ($r) => [
                'order_code' => $r->order_code, 'buyer_name' => $r->buyer_name, 'buyer_phone' => $r->buyer_phone, 'type' => $r->type,
                'amount' => (int) $r->amount, 'method' => $r->method, 'at' => \Carbon\Carbon::parse($r->at)->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
                'money_in' => (int) $totals->money_in, 'money_out' => (int) $totals->money_out, 'net' => (int) ($totals->money_in - $totals->money_out)],
        ]);
    }
}
