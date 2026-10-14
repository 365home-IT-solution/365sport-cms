<?php

declare(strict_types=1);

namespace App\Support\ApiDocs;

use App\Models\Customer;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Payment\Entities\Order;
use Modules\Payment\Entities\OrderItem;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomTimeSlot;

// Dữ liệu mẫu dựng NGAY TRONG transaction của từng lần gọi thử khi sinh tài liệu API (command api:docs-data) — transaction bị rollback
// nên không để lại gì. Mỗi hàm trả về các giá trị cần thay vào đường dẫn / body / query / người gọi của endpoint.
class DocsFixtures
{
    public const BUYER_PHONE = '0912345678';

    public function __construct(private Customer $customer, private User $admin) {}

    /** @return array{partner:Partner, room:Product, owner:?User, slots:\Illuminate\Support\Collection}|null */
    public function booking(): ?array
    {
        static $cache = null;
        $partner = Partner::where('verification_status', 'approved')->get()->first(
            fn ($p) => ! $p->isPlatformPartner() && Product::withoutGlobalScope('partner')->where('partner_id', $p->id)->where('type', '!=', 'coach')->whereHas('roomTimeSlots')->exists()
        );
        if (! $partner) {
            return null;
        }
        // Đối tác có ngân hàng + gói dịch vụ còn hạn (trong transaction → tự trả lại).
        DB::table('partners')->where('id', $partner->id)->update([
            'payment_method_preference' => 'bank_transfer_manual', 'bank_bin' => '970436', 'bank_name' => 'Vietcombank',
            'bank_account_number' => '0123456789', 'bank_account_holder' => 'CONG TY MAU',
        ]);
        DB::table('partner_subscriptions')->where('partner_id', $partner->id)->update(['status' => 'active', 'is_trial' => 0, 'expires_at' => now()->addYear()]);
        \App\Support\SubscriptionGate::flush();

        $room = Product::withoutGlobalScope('partner')->where('partner_id', $partner->id)->where('type', '!=', 'coach')->whereHas('roomTimeSlots')->first();
        Product::withoutEvents(fn () => $room->update(['is_activated' => true]));
        $slots = RoomTimeSlot::where('room_id', $room->id)->whereNull('date')->orderBy('timeslot_id')->limit(3)->get();
        $owner = User::where('partner_id', $partner->id)->whereHas('roles', fn ($q) => $q->where('name', 'partner'))->first();

        return $cache = ['partner' => $partner->fresh(), 'room' => $room, 'owner' => $owner, 'slots' => $slots];
    }

    /** Body đặt sân hợp lệ (khung giờ trống, ngày tương lai). */
    public function bookingBody(int $daysAhead = 20, array $extra = []): array
    {
        $b = $this->booking();
        $slot = $b['slots'][0];
        $date = now()->addDays($daysAhead)->toDateString();

        return array_merge([
            'type' => 'slot', 'room_id' => $b['room']->id, 'guest_count' => 2, 'date' => $date,
            'checkin_date' => $date, 'checkout_date' => $date,
            'slots' => [['timeslot_id' => $slot->timeslot_id, 'date' => $date]],
            'payment_method' => 'bank_transfer', 'payment_type' => 'full',
        ], $extra);
    }

    /**
     * Tạo đơn mẫu.
     *
     * @param  string  $kind  pending (chờ CK, đã có biên lai) | pending_new (chưa gửi biên lai) | paid | deposit | failed | cancelled | extra_due | checked_in
     */
    public function order(string $kind = 'paid', bool $guest = false, int $daysAhead = 6): Order
    {
        $b = $this->booking();
        $room = $b['room'];
        $amount = 300000;
        $status = match ($kind) {
            'pending', 'pending_new' => 'pending', 'deposit' => 'deposit', 'failed' => 'failed',
            'cancelled' => 'cancelled_payment', default => 'paid',
        };

        $attrs = [
            'order_code' => 'DOC' . random_int(1000000, 9999999),
            'order_type' => 'slot',
            'partner_id' => $room->partner_id,
            'category_id' => $room->category_id ?? null,
            'customer_id' => $guest ? null : $this->customer->id,
            'buyer_name' => $guest ? 'Khách vãng lai' : $this->customer->fullname,
            'buyer_phone' => $guest ? self::BUYER_PHONE : $this->customer->phone,
            'amount' => $kind === 'deposit' ? 100000 : $amount,
            'full_amount' => $amount,
            'status' => $status,
            'payment_method' => in_array($kind, ['pending', 'pending_new'], true) ? 'bank_transfer' : 'PayOS',
            'paid_at' => in_array($status, ['paid', 'deposit'], true) ? now() : null,
        ];
        if ($kind === 'pending') {
            $attrs['bank_transfer_status'] = 'pending';
            $attrs['payment_proof'] = 'payment-proofs/sample.png';
        }
        if ($kind === 'cancelled') {
            $attrs['cancelled_at'] = now();
        }
        if ($kind === 'extra_due') {
            $attrs['extra_charge_amount'] = 50000;
        }
        if ($kind === 'checked_in') {
            $attrs['checked_in_at'] = now();
            $attrs['order_status'] = 'checked_in';
        }
        if ($kind === 'completed') {
            $attrs['checked_in_at'] = now()->subDays(2);
            $attrs['checked_out_at'] = now()->subDays(2)->addHour();
            $attrs['order_status'] = 'checked_out';
        }
        if ($kind === 'weather') {
            // Sân ngoài trời: đánh dấu loại sân là ngoài trời trong transaction hiện tại.
            DB::table('room_types')->where('id', $room->room_type_id ?? 0)->update(['is_outdoor' => 1]);
        }
        if ($kind === 'package') {
            $planId = DB::table('package_plans')->insertGetId([
                'product_id' => $room->id, 'partner_id' => $room->partner_id, 'name' => 'Gói 4 buổi', 'sessions_count' => 4,
                'price' => 400000, 'validity_days' => 60, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $attrs['order_type'] = 'package';
            $attrs['package_plan_id'] = $planId;
        }

        $order = Order::withoutGlobalScopes()->create(array_filter($attrs, fn ($v) => $v !== null));

        $start = match ($kind) {
            'checked_in' => now()->subMinutes(10),
            'checkin_ready' => now()->addMinutes(5),
            'completed' => now()->subDays(2)->setTime(18, 0),
            'weather' => now()->addDay()->setTime(18, 0),
            default => now()->addDays($daysAhead)->setTime(18, 0),
        };
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $room->id, 'name' => $room->name, 'price' => $amount, 'quantity' => 1,
            'checkin_date' => $start, 'checkout_date' => $start->copy()->addHour(), 'slot_label' => '18:00',
        ]);

        return $order->fresh();
    }

    /** Khách nhận được yêu cầu xuất hoá đơn cho đơn đã thanh toán. */
    public function invoiceRequest(Order $order): ?int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('invoice_requests')) {
            return null;
        }

        return DB::table('invoice_requests')->insertGetId([
            'order_id' => $order->id, 'partner_id' => $order->partner_id, 'customer_id' => $order->customer_id,
            'company_name' => 'Công ty TNHH Mẫu', 'tax_code' => '0312345678', 'address' => '1 Nguyễn Huệ, Q1, TP.HCM',
            'email' => 'ketoan@example.com', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function firstId(string $table, string $col = 'id'): mixed
    {
        return \Illuminate\Support\Facades\Schema::hasTable($table) ? DB::table($table)->orderBy($col)->value($col) : null;
    }

    public function newCode(): string
    {
        return strtoupper(Str::random(8));
    }
}
