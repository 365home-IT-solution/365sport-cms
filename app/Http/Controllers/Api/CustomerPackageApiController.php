<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerPackage;
use App\Models\PackagePlan;
use App\Services\BookingRateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Payment\Entities\Order;
use Modules\Product\App\Models\Product;

// Gói nhiều buổi / thẻ trả trước cho khách (app/web client). Mua gói tạo Order order_type=package_purchase; thanh toán dùng lại endpoint đơn có sẵn
// (GET /api/orders/{code}/payment-qr cho PayOS, POST /api/orders/{code}/payment-proof cho chuyển khoản). Gói chỉ kích hoạt khi đơn được xác nhận đã thanh toán.
class CustomerPackageApiController extends Controller
{
    // GET /api/package-plans — ?room_id= (chỉ gói dùng được cho sân này), ?category_id= (chi nhánh), ?partner_id=
    public function plans(Request $request): JsonResponse
    {
        $plans = PackagePlan::query()->where('is_active', true)
            ->when($request->filled('partner_id'), fn ($q) => $q->where('partner_id', $request->query('partner_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', (int) $request->query('category_id')))
            ->orderBy('price')->get();

        if ($request->filled('room_id')) {
            $room = Product::query()->with('categories:id')->find($request->query('room_id'));
            $catIds = $room ? $room->categories->pluck('id')->all() : [];
            $plans = $plans->filter(fn (PackagePlan $p) => $room && $p->appliesToProduct((string) $room->id, $catIds))->values();
        }

        return response()->json(['data' => $plans->map(fn (PackagePlan $p) => $this->plan($p))->all()]);
    }

    // POST /api/package-plans/{id}/purchase — tạo đơn mua gói, trả về order_code + phương thức thanh toán của đối tác.
    public function purchase(Request $request, int $id): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('sanctum')->user();

        $plan = PackagePlan::query()->find($id);
        if (! $plan || ! $plan->is_active) {
            return response()->json(['message' => 'Package plan not found or no longer on sale.'], 404);
        }

        $blocked = app(BookingRateLimiter::class)->checkBlocked($request->ip());
        if ($blocked) {
            return response()->json(['message' => $blocked], 429);
        }

        $rep = $this->representativeProduct($plan);
        if (! $rep) {
            return response()->json(['message' => 'This package has no court it can be used at.'], 422);
        }

        if ($rep->partner && ! $rep->partner->hasUsablePaymentMethod()) {
            return response()->json(['message' => 'This court is not accepting online payments yet: the owner has not set up a payment method.', 'code' => 'payment_not_configured'], 422);
        }

        $method = ($rep->partner?->effectivePaymentMethod() ?? 'payos') === 'bank_transfer' ? 'bank_transfer' : 'PayOS';

        $order = Order::create([
            'order_code' => time() . rand(1000, 9999), 'order_type' => 'package_purchase', 'package_plan_id' => $plan->id,
            'customer_id' => $customer->id, 'buyer_name' => $customer->fullname, 'buyer_phone' => $customer->phone, 'buyer_email' => $customer->email ?? null,
            'amount' => $plan->price, 'full_amount' => $plan->price, 'creator_ip' => $request->ip(), 'status' => 'pending',
            'payment_method' => $method, 'description' => 'Mua goi: ' . $plan->name,
            'category_id' => $plan->category_id ?? $rep->categories->first()?->id, 'partner_id' => $rep->partner_id,
            'expired_at' => now()->addMinutes($method === 'bank_transfer' ? 60 : 15),
        ]);
        $order->items()->create(['product_id' => $plan->product_id ?? $rep->id, 'name' => $plan->name . ' - ' . $rep->name, 'price' => $plan->price, 'quantity' => 1, 'extra_fee' => 0]);

        return response()->json([
            'message' => 'Package order created.',
            'order_code' => $order->order_code, 'amount' => (int) $order->full_amount, 'payment_method' => $method, 'expired_at' => $order->expired_at,
            'next' => $method === 'PayOS'
                ? ['method' => 'GET', 'url' => "/api/orders/{$order->order_code}/payment-qr"]
                : ['method' => 'POST', 'url' => "/api/orders/{$order->order_code}/payment-proof", 'field' => 'payment_proof'],
        ], 201);
    }

    // GET /api/my-packages — ?active=1 chỉ gói còn buổi & còn hạn
    public function mine(Request $request): JsonResponse
    {
        $rows = $this->ownedQuery()->orderByDesc('id')->get()->filter(fn (CustomerPackage $p) => ! $request->boolean('active') || $p->isValid());

        return response()->json(['data' => $rows->map(fn (CustomerPackage $p) => $this->owned($p))->values()->all()]);
    }

    // GET /api/my-packages/{id} — kèm lịch sử trừ buổi
    public function showMine(int $id): JsonResponse
    {
        $p = $this->ownedQuery()->with('redemptions')->find($id);
        if (! $p) {
            return response()->json(['message' => 'Package not found.'], 404);
        }

        return response()->json(['data' => $this->owned($p) + ['redemptions' => $p->redemptions->map(fn ($r) => $r->toArray())->all()]]);
    }

    private function ownedQuery()
    {
        /** @var Customer $customer */
        $customer = auth('sanctum')->user();

        return CustomerPackage::query()->where(fn ($q) => $q->where('customer_id', $customer->id)->orWhere('buyer_phone', $customer->phone));
    }

    private function representativeProduct(PackagePlan $plan): ?Product
    {
        if ($plan->product_id) {
            return Product::query()->with(['partner', 'categories'])->find($plan->product_id);
        }
        if (! empty($plan->product_ids)) {
            return Product::query()->with(['partner', 'categories'])->whereIn('id', $plan->product_ids)->first();
        }

        return $plan->category?->products()->with(['partner', 'categories'])->where('is_activated', true)->first();
    }

    private function plan(PackagePlan $p): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'sessions_count' => (int) $p->sessions_count, 'price' => (int) $p->price, 'validity_days' => $p->validity_days,
            'scope' => $p->scopeType(), 'product_id' => $p->product_id, 'category_id' => $p->category_id, 'product_ids' => $p->product_ids ?? [],
            'per_session_price' => (int) round($p->price / max(1, (int) $p->sessions_count)), 'savings_percent' => $p->savingsPercent(),
        ];
    }

    private function owned(CustomerPackage $p): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'sessions_total' => (int) $p->sessions_total, 'sessions_remaining' => (int) $p->sessions_remaining,
            'price_paid' => (int) $p->price_paid, 'purchased_at' => $p->purchased_at?->toIso8601String(), 'expires_at' => $p->expires_at?->toIso8601String(),
            'is_valid' => $p->isValid(), 'scope' => $p->product_id ? 'product' : (! empty($p->product_ids) ? 'selected_products' : 'branch'),
            'product_id' => $p->product_id, 'category_id' => $p->category_id, 'product_ids' => $p->product_ids ?? [],
        ];
    }
}
