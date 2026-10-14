<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Payment\Entities\Order;
use Modules\Promotion\App\Models\Coupon;

// Điểm thưởng: tích khi đơn thanh toán, thu hồi khi đơn bị huỷ/xoá, đổi sang mã giảm giá RIÊNG của khách, admin chỉnh tay.
// Mọi thay đổi số dư đi qua apply() — khoá dòng khách trong transaction để 2 request cùng lúc không làm lệch số dư.
class LoyaltyService
{
    public function pointsForAmount(float $amount): int
    {
        $per = max(1, (int) config('loyalty.earn_vnd_per_point'));

        return (int) floor(max(0, $amount) / $per);
    }

    // Tích điểm cho đơn đã thanh toán. Idempotent: mỗi đơn chỉ tích 1 lần (kể cả khi observer chạy lại).
    public function earnForOrder(Order $order): ?LoyaltyTransaction
    {
        if (! $order->customer_id || $order->exclude_from_stats) {
            return null;
        }

        $points = $this->pointsForAmount((float) ($order->full_amount ?? $order->amount ?? 0));
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $points) {
            $customer = Customer::query()->lockForUpdate()->find($order->customer_id);
            if (! $customer || $this->netEarned((string) $order->id) > 0) {
                return null;
            }

            return $this->apply($customer, 'earn', $points, ['order_id' => (string) $order->id, 'note' => 'Tích điểm đơn #' . $order->order_code]);
        });
    }

    // Thu hồi phần điểm đã tích của đơn (đơn bị huỷ/hoàn tiền/xoá). Không làm số dư âm: thu hồi tối đa bằng số dư hiện có.
    public function revokeForOrder(Order $order): ?LoyaltyTransaction
    {
        if (! $order->customer_id) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $customer = Customer::query()->lockForUpdate()->find($order->customer_id);
            $earned = $this->netEarned((string) $order->id);
            if (! $customer || $earned <= 0) {
                return null;
            }

            $take = min($earned, (int) $customer->loyalty_points);
            if ($take <= 0) {
                return null;
            }

            return $this->apply($customer, 'revoke', -$take, ['order_id' => (string) $order->id, 'note' => 'Thu hồi điểm đơn #' . $order->order_code]);
        });
    }

    // Đổi điểm lấy mã giảm giá cố định, chỉ chủ tài khoản dùng được, dùng 1 lần.
    public function redeem(Customer $customer, int $points): array
    {
        $min = (int) config('loyalty.min_redeem_points');
        if ($points < $min) {
            return ['ok' => false, 'message' => "Minimum redemption is {$min} points."];
        }

        return DB::transaction(function () use ($customer, $points) {
            $locked = Customer::query()->lockForUpdate()->find($customer->id);
            if (! $locked || (int) $locked->loyalty_points < $points) {
                return ['ok' => false, 'message' => 'Not enough points.'];
            }

            $value = $points * (int) config('loyalty.point_value_vnd');
            $coupon = Coupon::create([
                'code' => 'DIEM' . strtoupper(Str::random(8)), 'name' => "Đổi {$points} điểm thưởng", 'description' => 'Mã tạo từ điểm thưởng',
                'type' => 'fixed', 'value' => $value, 'apply_type' => 'all_rooms', 'usage_limit' => 1, 'used_count' => 0,
                'start_at' => now(), 'end_at' => now()->addDays((int) config('loyalty.coupon_days')), 'is_active' => true,
                'customer_id' => $locked->id, 'created_by' => User::role(config('filament-shield.super_admin.name'))->value('id'),
            ]);

            $tx = $this->apply($locked, 'redeem', -$points, ['coupon_code' => $coupon->code, 'note' => "Đổi điểm lấy mã {$coupon->code}"]);

            return ['ok' => true, 'coupon' => $coupon, 'transaction' => $tx];
        });
    }

    // Admin cộng/trừ điểm thủ công (points khác 0). Trừ không vượt quá số dư.
    public function adjust(Customer $customer, int $points, string $note, ?User $by = null): array
    {
        if ($points === 0) {
            return ['ok' => false, 'message' => 'Points must not be zero.'];
        }

        return DB::transaction(function () use ($customer, $points, $note, $by) {
            $locked = Customer::query()->lockForUpdate()->find($customer->id);
            if (! $locked) {
                return ['ok' => false, 'message' => 'Customer not found.'];
            }
            if ($points < 0 && (int) $locked->loyalty_points < -$points) {
                return ['ok' => false, 'message' => 'Cannot deduct more points than the customer has.'];
            }

            return ['ok' => true, 'transaction' => $this->apply($locked, 'adjust', $points, ['note' => $note, 'created_by' => $by?->id])];
        });
    }

    // Điểm đã tích cho đơn trừ phần đã thu hồi.
    private function netEarned(string $orderId): int
    {
        return (int) LoyaltyTransaction::query()->where('order_id', $orderId)->whereIn('type', ['earn', 'revoke'])->sum('points');
    }

    private function apply(Customer $customer, string $type, int $points, array $extra): LoyaltyTransaction
    {
        $balance = max(0, (int) $customer->loyalty_points + $points);
        $customer->forceFill(['loyalty_points' => $balance])->saveQuietly();

        return LoyaltyTransaction::create(array_merge(['customer_id' => $customer->id, 'type' => $type, 'points' => $points, 'balance_after' => $balance], $extra));
    }
}
