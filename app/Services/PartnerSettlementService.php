<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerSettlement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Payment\Entities\Order;

// Lập và thu đối soát hoa hồng nền tảng theo tháng.
//   Thực thu của đơn = amount (đơn đã trả đủ, gồm phụ phí phát sinh) hoặc tiền cọc đã nhận (đơn cọc). Tháng của đơn tính theo ngày thanh toán
//   (paid_at, nếu không có thì ngày tạo). Đơn loại khỏi thống kê (exclude_from_stats) không tính.
//   Hoàn tiền = số tiền hoàn cho khách (extra_refund_amount) của các đơn đã huỷ/hoàn trong tháng đó — trừ khỏi doanh thu tính hoa hồng.
//   Hoa hồng = doanh thu ròng × tỉ lệ trong hợp đồng (partners.commission_rate, chuỗi tự do "10"/"10%").
// Bảng đã thu (paid) bị khoá; bảng chờ thu (pending) có thể lập lại để tính lại hoặc điều chỉnh tay.
class PartnerSettlementService
{
    /** @return array<string, int|float> số liệu tháng $month (YYYY-MM) của một đối tác, chưa lưu */
    public function compute(Partner $partner, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $when = DB::raw('COALESCE(paid_at, created_at)');

        $orders = Order::query()->withoutGlobalScopes()
            ->where('partner_id', $partner->id)
            ->whereIn('status', ['paid', 'deposit', 'refunded'])
            ->where('exclude_from_stats', 0)
            ->whereBetween($when, [$start, $end])
            ->get(['id', 'status', 'amount', 'full_amount', 'deposit_paid_amount', 'extra_refund_amount']);

        $gross = 0;
        $refunds = 0;
        foreach ($orders as $o) {
            $gross += $o->status === 'deposit'
                ? (int) ($o->deposit_paid_amount ?? $o->amount ?? 0)
                : (int) ($o->amount ?? $o->full_amount ?? 0);
            $refunds += (int) ($o->extra_refund_amount ?? 0);
        }

        $net = max(0, $gross - $refunds);
        $rate = $this->rate($partner->commission_rate);

        return [
            'order_count' => $orders->count(),
            'gross_revenue' => $gross,
            'refunds' => $refunds,
            'net_revenue' => $net,
            'commission_rate' => $rate,
            'commission_amount' => (int) round($net * $rate / 100),
        ];
    }

    /** @return array{ok: true, settlement: PartnerSettlement}|array{ok: false, message: string} */
    public function generate(Partner $partner, string $month, User|string|null $actor): array
    {
        if (Carbon::createFromFormat('Y-m-d', $month . '-01')->endOfMonth()->isFuture()) {
            return ['ok' => false, 'message' => 'The month has not ended yet; settle it after the last day.'];
        }

        return DB::transaction(function () use ($partner, $month, $actor): array {
            $existing = PartnerSettlement::query()->where('partner_id', $partner->id)->where('month', $month)->lockForUpdate()->first();
            if ($existing?->isPaid()) {
                return ['ok' => false, 'message' => 'This settlement has already been paid.'];
            }

            $row = $this->compute($partner, $month);
            $adjustment = (int) ($existing->adjustment_amount ?? 0);
            $row['amount_due'] = max(0, $row['commission_amount'] + $adjustment);

            $settlement = PartnerSettlement::query()->updateOrCreate(
                ['partner_id' => $partner->id, 'month' => $month],
                $row + ['status' => 'pending', 'created_by' => $actor instanceof User ? (string) $actor->getKey() : ($actor ?: 'system')]
            );

            return ['ok' => true, 'settlement' => $settlement->fresh()];
        });
    }

    /** Điều chỉnh tay (+/-) cho bảng chờ thu. */
    public function adjust(PartnerSettlement $settlement, int $amount, ?string $note): array
    {
        if ($settlement->isPaid()) {
            return ['ok' => false, 'message' => 'A paid settlement cannot be adjusted.'];
        }

        $settlement->update([
            'adjustment_amount' => $amount,
            'adjustment_note' => $note,
            'amount_due' => max(0, (int) $settlement->commission_amount + $amount),
        ]);

        return ['ok' => true, 'settlement' => $settlement->fresh()];
    }

    public function markPaid(PartnerSettlement $settlement, string $method, ?string $reference): array
    {
        if ($settlement->isPaid()) {
            return ['ok' => false, 'message' => 'This settlement has already been paid.'];
        }

        $settlement->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => $method, 'payment_reference' => $reference]);

        return ['ok' => true, 'settlement' => $settlement->fresh()];
    }

    // commission_rate lưu chuỗi tự do (vd "10", "10%") — chỉ lấy phần số, giống CommissionSummaryService.
    private function rate(?string $rate): float
    {
        return blank($rate) ? 0.0 : min(100.0, (float) preg_replace('/[^0-9.]/', '', $rate));
    }
}
