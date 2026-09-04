<?php

declare(strict_types=1);

namespace Modules\Dashboard\App\Services\Report;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Modules\Dashboard\App\Services\OverviewService;

/**
 * BÁO CÁO ĐẶT SÂN: đặt sân theo thời gian, top 5 nhân viên đặt sân nhiều nhất, tỷ lệ đơn bị
 * huỷ theo lý do — trong kỳ đã chọn (mặc định 7 ngày gần nhất). Lọc theo created_at của đơn.
 *
 * ADAPTATION (so với 365home-cms gốc): "huỷ" ở sport KHÔNG còn gọn trong 1 cột status như home
 * (status='cancelled_payment'/'failed' cho đơn thanh toán thất bại). Từ migration
 * 2026_08_11_100000_add_customer_cancellation_to_orders_table, khách/admin huỷ đơn ĐÃ thanh toán
 * (paid/deposit) KHÔNG đổi cột `status` nữa (giữ nguyên để không lệch báo cáo doanh thu cũ) — chỉ
 * set cancelled_at/cancelled_by ('customer'|'admin'). cancel_reason cũng là text tự do (nhập tay ở
 * form huỷ đơn admin), KHÔNG còn enum cố định 'no_show'/'other' như home, nên không thể tách đúng
 * "khách không đến" — thay bằng tách theo AI huỷ (khách tự huỷ / admin huỷ) + thanh toán thất bại,
 * là 3 nhóm thực sự tồn tại trong dữ liệu sport. Xem thêm RoomCardsService (comment dòng ~86-89) —
 * cùng quy ước cancelled_at NOT NULL = không còn chiếm chỗ dù status vẫn paid/deposit.
 */
class BookingReportService
{
    /** Thanh toán không thành — KHÔNG bao gồm huỷ sau khi đã thanh toán (xem cancelled_at bên dưới). */
    private const PAYMENT_FAILED_STATUSES = ['cancelled_payment', 'failed'];

    /** Số ngày tối đa để vẫn chia theo TỪNG NGÀY; dài hơn thì gộp theo THÁNG. */
    private const MAX_DAYS_FOR_DAILY_BREAKDOWN = 62;

    public static function getData($user, string $filter, ?string $customStart, ?string $customEnd, ?array $branchCategoryIds, int $limit = 5): array
    {
        [$start, $end] = OverviewService::resolveRange($filter, $customStart, $customEnd);

        $baseQuery = ReportScope::orderQuery($user, $branchCategoryIds)
            ->whereBetween('created_at', [$start, $end]);

        return [
            'period'             => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'bookings_over_time' => static::bookingsOverTime(clone $baseQuery, $start, $end),
            'top_staff'          => static::topStaff(clone $baseQuery, $limit),
            'cancellation'       => static::cancellation(clone $baseQuery),
        ];
    }

    private static function bookingsOverTime($query, Carbon $start, Carbon $end): array
    {
        $totalDays = max(1, (int) $start->diffInDays($end) + 1);
        $byMonth   = $totalDays > self::MAX_DAYS_FOR_DAILY_BREAKDOWN;

        if ($byMonth) {
            $rows = (clone $query)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as d, COUNT(*) as c")
                ->groupBy('d')
                ->pluck('c', 'd');

            $series = [];
            foreach (CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end) as $m) {
                $key = $m->format('Y-m');
                $series[] = ['date' => $key, 'count' => (int) ($rows[$key] ?? 0)];
            }

            return $series;
        }

        $rows = (clone $query)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $series = [];
        foreach (CarbonPeriod::create($start, '1 day', $end) as $day) {
            $series[] = ['date' => $day->toDateString(), 'count' => (int) ($rows[$day->toDateString()] ?? 0)];
        }

        return $series;
    }

    private static function topStaff($query, int $limit): array
    {
        $rows = (clone $query)
            ->whereNotNull('created_by')
            ->selectRaw('created_by, COUNT(*) as bookings_count')
            ->groupBy('created_by')
            ->orderByDesc('bookings_count')
            ->limit($limit)
            ->get();

        $staffIds = $rows->pluck('created_by');
        $names    = \App\Models\User::whereIn('id', $staffIds)->pluck('fullname', 'id');

        return $rows->map(fn ($r) => [
            'user_id'        => $r->created_by,
            'name'           => $names[$r->created_by] ?? 'N/A',
            'bookings_count' => (int) $r->bookings_count,
        ])->toArray();
    }

    private static function cancellation($query): array
    {
        $total = (clone $query)->count();

        $paymentFailed = (clone $query)->whereIn('status', self::PAYMENT_FAILED_STATUSES)->count();

        // Đơn ĐÃ thanh toán (paid/deposit) nhưng sau đó bị huỷ — status giữ nguyên, chỉ cancelled_at
        // NOT NULL là dấu hiệu thật (xem docblock class này + RoomCardsService).
        $customerCancelled = (clone $query)->whereNotNull('cancelled_at')->where('cancelled_by', 'customer')->count();
        $adminCancelled    = (clone $query)->whereNotNull('cancelled_at')->where('cancelled_by', 'admin')->count();

        $stayed = (clone $query)->whereNotNull('checked_in_at')->count();

        // Khách ĐÃ đặt (paid/deposit) nhưng KHÔNG đến nhận sân — xem migration
        // 2026_09_03_000004_add_no_show_to_orders_table + Order::markNoShow(). Tách riêng khỏi
        // customer_cancelled_count/admin_cancelled_count vì đây KHÔNG phải huỷ chủ động trước giờ
        // nhận (cancelled_at) mà là khung giờ đã trôi qua, khách lặng lẽ không tới — không cộng vào
        // cancelled_rate_pct (đơn này vẫn "occupied" đúng nghĩa slot, chỉ là khách không dùng).
        $noShow = (clone $query)->whereNotNull('no_show_at')->count();

        $cancelledTotal = $paymentFailed + $customerCancelled + $adminCancelled;

        return [
            'total_bookings'          => $total,
            'payment_failed_count'    => $paymentFailed,
            'customer_cancelled_count' => $customerCancelled,
            'admin_cancelled_count'   => $adminCancelled,
            'no_show_count'           => $noShow,
            'stayed_count'            => $stayed,
            'cancelled_rate_pct'      => $total > 0 ? round(($cancelledTotal / $total) * 100, 2) : 0,
        ];
    }
}
