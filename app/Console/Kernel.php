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
        // ✅ Thêm mới - tự động hủy đơn hàng hết hạn sau 15 phút
        $schedule->command('orders:expire-pending')
            ->everyMinute()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping(1)
            ->appendOutputTo(storage_path('logs/expire-orders.log'));

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

        // Nhắc khách (push FCM) khi sắp hết giờ chơi (15 phút) / đã hết giờ; cửa sổ 2 phút nên phải chạy MỖI PHÚT (cờ expiry_notified chống gửi trùng).
        $schedule->command('lock:notify-expiry')
            ->everyMinute()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping(2)
            ->appendOutputTo(storage_path('logs/booking-expiry.log'));

        $schedule->command('guests:link-registered')
            ->hourly()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/link-registered-guests.log'));

        // Gói dịch vụ đối tác: nhắc hạn, báo hết hạn, link tự gia hạn, dọn giao dịch quá hạn.
        $schedule->command('subscriptions:process')
            ->dailyAt('08:00')
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/subscriptions-process.log'));

        $schedule->command('camera-alerts:poll')
            ->everyFiveMinutes()
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/camera-alerts-poll.log'));

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

        // Port từ 365home-cms — bắt các mốc chuyển ngày của bảng giá (Tết, khuyến mãi, đối tác...)
        // — áp bảng đang hiệu lực hôm nay xuống products/room_time_slots, hoặc khôi phục về bảng
        // mặc định khi hết hạn. Xem App\Services\PriceBoardSyncService.
        $schedule->command('price-boards:sync-due')
            ->dailyAt('00:05')
            ->timezone('Asia/Ho_Chi_Minh')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/price-boards-sync.log'));

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
