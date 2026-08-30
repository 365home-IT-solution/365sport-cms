<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Command cũ - giữ nguyên
        $schedule->command('access-codes:auto-delete-expired')
            ->dailyAt('02:00')
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping();

        // ✅ Thêm mới - tự động hủy đơn hàng hết hạn sau 15 phút
        $schedule->command('orders:expire-pending')
            ->everyMinute()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping(1)
            ->appendOutputTo(storage_path('logs/expire-orders.log'));

        $schedule->command('manual-lock-passwords:check-expired')
            ->hourly()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/expire-manual-lock-passwords.log'));

        $schedule->command('notifications:send-scheduled')
            ->everyMinute()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping(2)
            ->appendOutputTo(storage_path('logs/scheduled-notifications.log'));

        $schedule->command('notifications:checkin-reminder')
            ->everyFiveMinutes()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping(2)
            ->appendOutputTo(storage_path('logs/checkin-reminders.log'));

        $schedule->command('guests:link-registered')
            ->hourly()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/link-registered-guests.log'));

        $schedule->command('housekeeping:mark-cleaning')
            ->everyFiveMinutes()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/housekeeping-mark-cleaning.log'));

        // Hold khung giờ (xem TimeslotHoldService) hết hạn TTL (5 phút) mà không ai chủ động bỏ
        // chọn/lưu đơn — dọn + báo real-time "released" định kỳ, tránh khách hàng kẹt mãi ở trạng
        // thái "đang khóa" (xem resources/js/echo-client.js, cập nhật DOM trực tiếp không tự làm
        // mới nếu không có sự kiện released).
        $schedule->command('timeslot-holds:release-expired')
            ->everyMinute()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping();

        // Khung giờ khoá dài hạn (BlockTimeslotModal/Dashboard "Lịch" — settings['blocked_dates'])
        // đã qua giờ kết thúc thì tự gỡ khoá — không cần admin tự tay dọn ngày đã qua.
        $schedule->command('timeslot-blocks:prune-expired')
            ->everyFiveMinutes()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping();

        // Safety-net: tự trả sân khi khách quá giờ checkout mà không tự mở khoá lần 2.
        $schedule->command('orders:sync-lifecycle')
            ->everyMinute()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping(1)
            ->appendOutputTo(storage_path('logs/sync-order-lifecycle.log'));

        // Backup tự động (spatie/laravel-backup) — dump DB + file khách hàng upload
        // (storage/app/public, xem config/backup.php) hằng ngày, dọn backup cũ theo lịch riêng.
        // Lưu ý: hiện lưu backup trên CÙNG server (disk 'local') — chưa đẩy sang nơi khác (S3...),
        // nên chỉ chống mất dữ liệu do lỗi ứng dụng/DB, KHÔNG chống được mất cả server.
        $schedule->command('backup:run')
            ->dailyAt('03:00')
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/backup-run.log'));

        $schedule->command('backup:clean')
            ->dailyAt('03:30')
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/backup-clean.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
