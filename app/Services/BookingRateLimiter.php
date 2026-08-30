<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Modules\Payment\Entities\Order;

// Chặn phá hoại kiểu "đặt liên tục nhiều lần đè khung giờ, chờ hết hạn (60p chuyển khoản / 15p
// PayOS) rồi đặt lại ngay" — chặn theo IP TRÌNH DUYỆT (Order.creator_ip), KHÔNG theo số điện
// thoại: luồng đặt sân không xác minh OTP, đổi số vô hạn để né giới hạn theo SĐT (đã xác nhận với
// người dùng, quyết định chuyển sang chặn theo IP).
//
// 2 điều kiện, VI PHẠM 1 TRONG 2 LÀ CHẶN:
//   (a) IP này đang có QUÁ NHIỀU đơn 'pending'/'deposit' CHƯA thanh toán cùng lúc.
//   (b) IP này vừa tạo đơn cách đây CHƯA ĐỦ lâu (cooldown).
//
// Ngưỡng cố ý KHÔNG quá chặt — nhiều khách hàng thật ở VN dùng chung 1 IP công khai (NAT nhà mạng
// di động, wifi văn phòng/quán net...), chặn quá tay sẽ chặn nhầm khách thật đứng sau cùng IP với
// người khác đang đặt.
class BookingRateLimiter
{
    // Tối đa 3 đơn CHƯA thanh toán cùng lúc từ 1 IP — vượt ngưỡng này gần như chắc chắn không phải
    // 1 khách đang đặt bình thường (khách thật hiếm khi mở nhiều đơn treo cùng lúc).
    private const MAX_PENDING_PER_IP = 3;

    // Phải cách lần tạo đơn GẦN NHẤT (từ CÙNG IP) ít nhất 20 giây — đủ chặn spam-click liên tục,
    // không đủ ngắn để chặn nhầm 2 khách khác nhau cùng IP đặt gần nhau.
    private const COOLDOWN_SECONDS = 20;

    /**
     * @return string|null  null = cho phép đặt, string = lý do bị chặn (hiện cho khách).
     */
    public function checkBlocked(?string $ip): ?string
    {
        if (! $ip) {
            // Không lấy được IP (proxy cấu hình sai...) — không có gì để đối chiếu, cho qua thay vì
            // chặn nhầm toàn bộ khách vì lỗi hạ tầng.
            return null;
        }

        $pendingCount = Order::where('creator_ip', $ip)
            ->whereIn('status', ['pending', 'deposit'])
            ->count();

        if ($pendingCount >= self::MAX_PENDING_PER_IP) {
            return 'Bạn đang có quá nhiều đơn chờ thanh toán. Vui lòng hoàn tất hoặc chờ các đơn cũ hết hạn trước khi đặt thêm.';
        }

        $lastOrderAt = Order::where('creator_ip', $ip)
            ->latest('created_at')
            ->value('created_at');

        if ($lastOrderAt && Carbon::parse($lastOrderAt)->diffInSeconds(now()) < self::COOLDOWN_SECONDS) {
            return 'Bạn vừa đặt sân xong, vui lòng đợi ít giây trước khi đặt tiếp.';
        }

        return null;
    }
}
