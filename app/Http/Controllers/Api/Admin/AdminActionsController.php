<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\Customer;
use App\Models\MembershipTier;
use App\Models\Partner;
use App\Models\PartnerStatusLog;
use App\Models\User;
use App\Services\ExtraChargeService;
use App\Services\MembershipService;
use App\Services\NotificationFcmService;
use App\Services\OrderRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Modules\AuditLog\Services\AuditLogger;
use Modules\Payment\Entities\Order;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomType;
use Modules\Promotion\App\Models\Coupon;
use Modules\Warehouse\App\Exports\WarehouseItemExport;
use Modules\Warehouse\App\Exports\WarehouseStockCheckExport;
use Modules\Warehouse\App\Exports\WarehouseStockInExport;
use Modules\Warehouse\App\Exports\WarehouseStockOutExport;
use Modules\Warehouse\App\Models\WarehouseItem;
use Modules\Warehouse\App\Models\WarehouseStockCheck;
use Modules\Warehouse\App\Models\WarehouseStockIn;
use Modules\Warehouse\App\Models\WarehouseStockOut;
use Modules\Zns\App\Models\ZnsNotification;

// Các hành động trên màn quản trị (nút duyệt / huỷ / thu tiền / xuất Excel / gán...) mà CRUD thuần không có — để mọi thao tác ở Filament đều gọi được qua API.
// Phạm vi theo đối tác: super admin thấy tất cả, tài khoản khác chỉ thao tác trên dữ liệu của đối tác mình (sai phạm vi trả 404).
class AdminActionsController extends Controller
{
    // ───────────────────────── Đơn hàng ─────────────────────────

    // POST /api/admin/orders/{order_code}/cancel — body: reason, refund_amount (0..full_amount). Giống nút "Huỷ đơn" ở màn đơn.
    public function cancelOrder(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->order($request, $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $data = $request->validate(['reason' => 'required|string|max:500', 'refund_amount' => 'required|integer|min:0']);
        if ($data['refund_amount'] > (int) ($order->full_amount ?? $order->amount ?? 0)) {
            return response()->json(['message' => 'The refund cannot exceed the order total.', 'errors' => ['refund_amount' => ['The refund cannot exceed the order total.']]], 422);
        }
        if (! is_null($order->cancelled_at) || ! in_array($order->status, ['paid', 'deposit'], true)) {
            return response()->json(['message' => 'Only a paid or deposit order that is not yet cancelled can be cancelled.'], 422);
        }

        try {
            $refund = DB::transaction(function () use ($order, $data, $request): int {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
                if (! is_null($locked->cancelled_at)) {
                    throw new \RuntimeException('Order already cancelled.');
                }

                $paidByPackage = \App\Models\PackageRedemption::query()->where('order_id', $locked->id)->whereNull('refunded_at')->exists();
                $locked->update(['cancelled_at' => now(), 'cancelled_by' => 'admin', 'cancel_reason' => strip_tags($data['reason'])]);

                $refundAmount = (int) $data['refund_amount'];
                if ($paidByPackage) {
                    \App\Models\CustomerPackage::refundRedemptionsForOrder($locked);
                    // Chỉ phần tiền mặt khách thật sự đã trả mới hoàn được (đơn trả hoàn toàn bằng gói = 0).
                    $refundAmount = min($refundAmount, $locked->packageCashAmount());
                }
                if ($refundAmount > 0) {
                    app(ExtraChargeService::class)->recordPendingRefund($locked, $refundAmount);
                }

                return $refundAmount;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->refresh()->loadMissing('items');
        $byProduct = $order->items->whereNotNull('product_id')->groupBy('product_id');
        Order::broadcastSlotsReleasedByProduct($byProduct);
        Order::notifyWaitlistByProduct($byProduct);
        app(OrderRealtimeService::class)->broadcastOrderUpdate($order->order_code, ['status' => $order->status, 'cancelled_at' => $order->cancelled_at->toIso8601String(), 'refund_amount' => $refund], $order->customer_id);
        AuditLogger::log('update', 'Order', $order, ['cancelled_at' => null], ['cancelled_at' => now()->toDateTimeString(), 'cancelled_by' => 'admin', 'cancel_reason' => $data['reason'], 'refund_amount' => $refund], 'Order #' . $order->order_code . ' (admin API)');

        return response()->json(['message' => 'Order cancelled.', 'data' => ['order_code' => $order->order_code, 'cancelled_at' => $order->cancelled_at->toIso8601String(), 'pending_refund' => $refund]]);
    }

    // POST /api/admin/orders/{order_code}/collect-extra-cash — ghi nhận đã thu tiền mặt khoản phát sinh của đơn đã thanh toán.
    public function collectExtraCash(Request $request, string $orderCode): JsonResponse
    {
        $order = $this->order($request, $orderCode);
        if (! $order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        if (! $order->extra_charge_amount || ! is_null($order->extra_charge_paid_at) || $order->status !== 'paid') {
            return response()->json(['message' => 'There is no unpaid extra charge on this paid order.'], 422);
        }

        $amount = (int) $order->extra_charge_amount;
        app(ExtraChargeService::class)->markExtraChargeAsCash($order, $amount);

        try {
            if ($order->customer_id && ($customer = Customer::find($order->customer_id))) {
                app(NotificationFcmService::class)->sendToCustomer($customer, "Đơn #{$order->order_code}: khoản phát sinh đã thanh toán", 'Khoản phát sinh ' . number_format($amount, 0, ',', '.') . 'đ đã được ghi nhận (tiền mặt).', 'booking', ['order_code' => (string) $order->order_code, 'type' => 'order_extra_charge_paid']);
            }
            app(OrderRealtimeService::class)->broadcastOrderUpdate((string) $order->order_code, ['extra_charge' => ['is_paid' => true, 'paid_at' => now()->toISOString(), 'payment_method' => 'cod']], $order->customer_id);
        } catch (\Throwable) {
            // Thông báo lỗi không làm hỏng việc đã ghi nhận.
        }

        return response()->json(['message' => 'Extra charge recorded as cash.', 'data' => ['order_code' => $order->order_code, 'extra_charge_amount' => $amount]]);
    }

    // GET /api/admin/orders/export — xlsx; ?date_from= ?date_to= ?status= ?payment_method=
    // POST /api/admin/device-token {token} — lưu FCM token của thiết bị quản trị để nhận thông báo đẩy.
    public function saveDeviceToken(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|min:20|max:512']);
        \App\Models\FcmToken::upsertForUser((string) $request->user()->id, $data['token']);

        return response()->json(['message' => 'Device token saved.']);
    }

    // DELETE /api/admin/device-token {token?} — bỏ token (đăng xuất / tắt thông báo); không gửi token = bỏ mọi token của tài khoản.
    public function deleteDeviceToken(Request $request): JsonResponse
    {
        $q = \App\Models\FcmToken::where('user_id', (string) $request->user()->id);
        if ($request->filled('token')) {
            $q->where('token_hash', hash('sha256', (string) $request->input('token')));
        }
        $q->delete();

        return response()->json(['message' => 'Device token removed.']);
    }

    public function exportOrders(Request $request)
    {
        $user = $request->user();
        $f = $request->validate(['date_from' => 'nullable|date', 'date_to' => 'nullable|date', 'status' => 'nullable|string|max:30', 'payment_method' => 'nullable|string|max:30']);

        $q = Order::query()->withoutGlobalScope('partner')->where('exclude_from_stats', false)
            ->when(! $user->isSuperAdmin(), fn ($w) => $w->where('partner_id', $user->partner_id))
            ->when(! empty($f['date_from']), fn ($w) => $w->where('created_at', '>=', $f['date_from']))
            ->when(! empty($f['date_to']), fn ($w) => $w->where('created_at', '<=', $f['date_to']))
            ->when(! empty($f['status']), fn ($w) => $w->where('status', $f['status']))
            ->when(! empty($f['payment_method']), fn ($w) => $w->where('payment_method', $f['payment_method']))
            ->orderByDesc('created_at')->limit(20000);

        $rows = $q->get()->map(fn (Order $o) => [
            $o->order_code, $o->buyer_name, $o->buyer_phone, $o->status, $o->payment_method, (int) $o->full_amount, (int) $o->amount,
            (int) ($o->coupon_discount ?? 0), $o->created_at?->format('d/m/Y H:i'), $o->paid_at?->format('d/m/Y H:i'),
        ])->all();

        $export = new class($rows) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return ['Mã đơn', 'Khách', 'SĐT', 'Trạng thái', 'Thanh toán', 'Tổng tiền', 'Đã thu', 'Giảm mã', 'Tạo lúc', 'Thanh toán lúc'];
            }
        };

        return Excel::download($export, 'orders_' . now()->format('Y-m-d_His') . '.xlsx');
    }

    // ───────────────────────── Đối tác (chỉ super admin) ─────────────────────────

    // POST /api/admin/partners/{id}/status — body: status = suspended|approved (nút "Tạm dừng hồ sơ" / "Phê duyệt chính thức")
    public function partnerStatus(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->superOnly($request)) {
            return $denied;
        }
        $partner = Partner::find($id);
        if (! $partner) {
            return response()->json(['message' => 'Partner not found.'], 404);
        }

        $data = $request->validate(['status' => 'required|in:suspended,approved', 'note' => 'nullable|string|max:255']);
        $from = $partner->verification_status;
        $partner->update(['verification_status' => $data['status'], 'status' => $data['status'] === 'approved']);
        PartnerStatusLog::create(['partner_id' => $partner->id, 'from_status' => $from, 'to_status' => $data['status'], 'note' => $data['note'] ?? ($data['status'] === 'approved' ? 'Phê duyệt chính thức' : 'Tạm dừng hồ sơ'), 'changed_by' => $request->user()->id]);

        return response()->json(['data' => ['id' => $partner->id, 'verification_status' => $partner->verification_status]]);
    }

    // GET /api/admin/partners/{id}/contract-versions
    public function contractVersions(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->superOnly($request)) {
            return $denied;
        }
        $partner = Partner::find($id);
        if (! $partner) {
            return response()->json(['message' => 'Partner not found.'], 404);
        }

        return response()->json(['data' => $partner->contractVersions()->orderByDesc('id')->get()->map(fn ($v) => [
            'id' => $v->id, 'version_label' => $v->version_label, 'change_note' => $v->change_note, 'created_at' => $v->created_at?->toIso8601String(),
            'document_url' => $v->getFirstMediaUrl('document') ?: null,
        ])->all(), 'contract_status' => $partner->contract_status, 'contract_expires_at' => $partner->contract_expires_at?->toIso8601String()]);
    }

    // POST /api/admin/partners/{id}/contract-versions — multipart: version_label, change_note?, document? (pdf/ảnh)
    public function addContractVersion(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->superOnly($request)) {
            return $denied;
        }
        $partner = Partner::find($id);
        if (! $partner) {
            return response()->json(['message' => 'Partner not found.'], 404);
        }

        $data = $request->validate(['version_label' => 'required|string|max:50', 'change_note' => 'nullable|string|max:1000', 'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $version = $partner->contractVersions()->create(['version_label' => $data['version_label'], 'change_note' => $data['change_note'] ?? null, 'changed_by' => $request->user()->id]);
        if ($request->hasFile('document')) {
            $version->addMediaFromRequest('document')->toMediaCollection('document');
        }

        return response()->json(['data' => ['id' => $version->id, 'version_label' => $version->version_label]], 201);
    }

    // POST /api/admin/partners/{id}/contract/renew — gia hạn thêm 12 tháng (từ ngày hết hạn hiện tại hoặc từ hôm nay nếu đã hết hạn)
    public function renewContract(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->superOnly($request)) {
            return $denied;
        }
        $partner = Partner::find($id);
        if (! $partner) {
            return response()->json(['message' => 'Partner not found.'], 404);
        }

        $base = $partner->contract_expires_at && $partner->contract_expires_at->isFuture() ? $partner->contract_expires_at : now();
        $partner->update(['contract_expires_at' => $base->copy()->addYear(), 'contract_status' => 'active']);

        return response()->json(['data' => ['contract_status' => 'active', 'contract_expires_at' => $partner->contract_expires_at->toIso8601String()]]);
    }

    // POST /api/admin/partners/{id}/contract/terminate
    public function terminateContract(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->superOnly($request)) {
            return $denied;
        }
        $partner = Partner::find($id);
        if (! $partner) {
            return response()->json(['message' => 'Partner not found.'], 404);
        }

        $partner->update(['contract_status' => 'terminated']);

        return response()->json(['data' => ['contract_status' => 'terminated']]);
    }

    // ───────────────────────── Khách doanh nghiệp / ZNS / kho ─────────────────────────

    // POST /api/admin/corporate-accounts/{id}/close-cycle — reset số giờ đã dùng, sang kỳ mới (tháng kế tiếp)
    public function closeCorporateCycle(Request $request, int $id): JsonResponse
    {
        $acc = $this->scoped($request, CorporateAccount::query())->find($id);
        if (! $acc) {
            return response()->json(['message' => 'Corporate account not found.'], 404);
        }

        $acc->closeCycle();

        return response()->json(['data' => ['id' => $acc->id, 'hours_used_current_cycle' => $acc->hours_used_current_cycle, 'cycle_start_date' => $acc->cycle_start_date?->toDateString()]]);
    }

    // POST /api/admin/zns-notifications/{id}/retry
    public function retryZns(Request $request, int $id): JsonResponse
    {
        $n = ZnsNotification::find($id);
        $order = $n ? Order::query()->withoutGlobalScope('partner')->find($n->order_id) : null;
        if (! $n || ($order && ! $request->user()->isSuperAdmin() && $order->partner_id !== $request->user()->partner_id) || (! $order && ! $request->user()->isSuperAdmin())) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }
        if (! $n->canRetry()) {
            return response()->json(['message' => 'This notification cannot be retried (not failed, retry limit reached or retry not due yet).'], 422);
        }

        $n->resetForRetry();
        $result = app(\Modules\BladeThemeV1\Services\Zns\ZaloZnsService::class)->sendZns($n->order_id, $n->phone_number, $n->recipient_name, $n->template_id, $n->notification_type, $n->template_data);

        return $result['success'] ?? false
            ? response()->json(['message' => 'ZNS resent.'])
            : response()->json(['message' => 'Resend failed.', 'error' => $result['error'] ?? null], 422);
    }

    // POST /api/admin/warehouse/stock-checks/{id}/confirm-handover — body: lines: [{check_item_id, handover_quantity}], note?
    public function confirmHandover(Request $request, int $id): JsonResponse
    {
        $check = $this->scoped($request, WarehouseStockCheck::query())->with('items')->find($id);
        if (! $check) {
            return response()->json(['message' => 'Stock check not found.'], 404);
        }

        $data = $request->validate(['lines' => 'sometimes|array', 'lines.*.check_item_id' => 'required|integer', 'lines.*.handover_quantity' => 'required|numeric|min:0', 'note' => 'nullable|string|max:500']);
        $byId = collect($data['lines'] ?? [])->keyBy('check_item_id');
        $unknown = $byId->keys()->diff($check->items->pluck('id'));
        if ($unknown->isNotEmpty()) {
            return response()->json(['message' => 'Some lines do not belong to this stock check.', 'errors' => ['lines' => ['Unknown check_item_id: ' . $unknown->implode(', ')]]], 422);
        }

        $discrepancy = false;
        DB::transaction(function () use ($check, $byId, $request, $data, &$discrepancy) {
            foreach ($check->items as $line) {
                $qty = (float) ($byId[$line->id]['handover_quantity'] ?? $line->actual_quantity);
                $diff = round($qty - (float) $line->actual_quantity, 2);
                $line->updateQuietly(['handover_quantity' => $qty, 'handover_difference' => $diff]);
                $discrepancy = $discrepancy || $diff !== 0.0;
            }
            $check->update([
                'handover_status' => $discrepancy ? WarehouseStockCheck::HANDOVER_DISCREPANCY : WarehouseStockCheck::HANDOVER_CONFIRMED,
                'handover_confirmed_by' => $request->user()->id, 'handover_confirmed_at' => now(), 'handover_note' => $data['note'] ?? null,
            ]);
        });

        return response()->json(['data' => ['handover_status' => $check->handover_status, 'has_discrepancy' => $discrepancy]]);
    }

    // GET /api/admin/warehouse/{items|stock-ins|stock-outs|stock-checks}/export — xlsx
    public function exportWarehouse(Request $request, string $kind)
    {
        $user = $request->user();
        $map = [
            'items' => [WarehouseItem::class, WarehouseItemExport::class], 'stock-ins' => [WarehouseStockIn::class, WarehouseStockInExport::class],
            'stock-outs' => [WarehouseStockOut::class, WarehouseStockOutExport::class], 'stock-checks' => [WarehouseStockCheck::class, WarehouseStockCheckExport::class],
        ];
        abort_unless(isset($map[$kind]), 404, 'Unknown export.');
        [$model, $export] = $map[$kind];

        $query = $this->scoped($request, $model::query());

        return Excel::download(new $export($query, showPartnerColumn: $user->isSuperAdmin()), "warehouse_{$kind}_" . now()->format('Y-m-d_His') . '.xlsx');
    }

    // ───────────────────────── Gán dữ liệu ─────────────────────────

    // POST /api/admin/customers/{id}/assign-tier — body: membership_tier_id (cấp mã giảm giá của hạng nếu hạng có cấu hình)
    public function assignTier(Request $request, string $id): JsonResponse
    {
        if (! ($customer = Customer::find($id))) {
            return response()->json(['message' => 'Customer not found.'], 404);
        }

        $data = $request->validate(['membership_tier_id' => 'required|integer|exists:membership_tiers,id']);
        $tier = MembershipTier::find($data['membership_tier_id']);
        if (! $tier->is_active) {
            return response()->json(['message' => 'This membership tier is not active.'], 422);
        }
        if ((int) $customer->membership_tier_id === (int) $tier->id) {
            return response()->json(['message' => 'The customer already has this tier.', 'data' => ['membership_tier_id' => $tier->id]]);
        }

        $coupon = app(MembershipService::class)->assignManually($customer, $tier->id, $customer->membership_tier_id);

        return response()->json(['data' => ['membership_tier_id' => $tier->id, 'coupon_code' => $coupon?->code]]);
    }

    // POST /api/admin/customers/assign-coupons — body: customer_ids[], coupon_ids[]. Mã được gán chỉ khách đó dùng được.
    public function assignCoupons(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['customer_ids' => 'required|array|min:1|max:500', 'customer_ids.*' => 'string', 'coupon_ids' => 'required|array|min:1|max:50', 'coupon_ids.*' => 'integer']);

        $coupons = Coupon::query()->withoutGlobalScope('partner')->whereIn('id', $data['coupon_ids'])->where('is_active', true)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('partner_id', $user->partner_id))->pluck('id');
        if ($coupons->count() !== count(array_unique($data['coupon_ids']))) {
            return response()->json(['message' => 'Some coupons do not exist, are inactive or belong to another partner.'], 422);
        }

        $pivot = $coupons->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all();
        $customers = Customer::whereIn('id', $data['customer_ids'])->get();
        foreach ($customers as $c) {
            $c->coupons()->syncWithoutDetaching($pivot);
        }

        return response()->json(['message' => 'Coupons assigned.', 'data' => ['customers' => $customers->count(), 'coupons' => $coupons->count()]]);
    }

    // PUT /api/admin/room-types/{id}/products — body: product_ids[] (sân thuộc loại này; sân khác trong loại bị gỡ)
    public function assignRoomTypeProducts(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $type = RoomType::find($id);
        if (! $type) {
            return response()->json(['message' => 'Room type not found.'], 404);
        }

        $data = $request->validate(['product_ids' => 'present|array', 'product_ids.*' => 'string']);
        $ids = $data['product_ids'];
        $products = Product::query()->withoutGlobalScope('partner')->when(! $user->isSuperAdmin(), fn ($q) => $q->where('partner_id', $user->partner_id));
        if ($products->clone()->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
            return response()->json(['message' => 'Some courts do not exist or belong to another partner.'], 422);
        }

        DB::transaction(function () use ($products, $ids, $type) {
            $products->clone()->whereIn('id', $ids)->update(['room_type_id' => $type->id]);
            $products->clone()->where('room_type_id', $type->id)->whereNotIn('id', $ids ?: [''])->update(['room_type_id' => null]);
        });

        return response()->json(['data' => ['room_type_id' => $type->id, 'product_ids' => $ids]]);
    }

    // POST /api/admin/coupons/bulk-status — body: ids[], is_active (bật / tắt hàng loạt)
    public function couponsBulkStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer', 'is_active' => 'required|boolean']);

        $n = Coupon::query()->withoutGlobalScope('partner')->whereIn('id', $data['ids'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('partner_id', $user->partner_id))
            ->update(['is_active' => (bool) $data['is_active']]);

        return response()->json(['data' => ['updated' => $n]]);
    }

    // ───────────────────────── helper ─────────────────────────

    private function order(Request $request, string $code): ?Order
    {
        $user = $request->user();

        return Order::query()->withoutGlobalScope('partner')->where('order_code', $code)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('partner_id', $user->partner_id))->first();
    }

    private function scoped(Request $request, $query)
    {
        $user = $request->user();

        return $user->isSuperAdmin() ? $query->withoutGlobalScope('partner') : $query->withoutGlobalScope('partner')->where('partner_id', $user->partner_id);
    }

    private function superOnly(Request $request): ?JsonResponse
    {
        return $request->user()->isSuperAdmin() ? null : response()->json(['message' => 'Forbidden.'], 403);
    }
}
