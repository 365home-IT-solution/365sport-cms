<?php

namespace Modules\Product\App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Promotion\App\Models\Promotion;

class RoomTimeSlot extends Model
{
    // Bổ sung sau khi phát hiện lúc port SettingBook::save() (bảng giá) từ 365home-cms — bản gốc
    // gọi RoomTimeSlot::withoutAuditLog() (chỉ tồn tại nếu model dùng trait này) để tự ghi 1 dòng
    // TÓM TẮT duy nhất thay vì hàng loạt dòng vụn mỗi lần xoá-tạo-lại khung giờ. Đây là bảng bị
    // sửa/xoá/tạo liên tục nhất hệ thống (mọi lần đặt sân/đổi giá) — rất đáng audit.
    use HasFactory, LogsAuditTrail;

    protected $fillable = [
        'room_id',
        'timeslot_id',
        'price',
        'checkin',
        'checkout',
        'status',
        'over_night',
        'settings',
        // Đánh dấu dòng này đang được áp bởi bảng giá đặt tên nào (null = giá tay/gốc) — xem
        // App\Services\PriceBoardSyncService. Thiếu field này trong $fillable khiến mass-assignment
        // ÂM THẦM bỏ qua, không báo lỗi gì (bug thật đã gặp lúc test: cột luôn NULL dù code có set).
        'synced_from_price_board_id',
    ];

    protected $casts = [
        'date'     => 'date',
        'settings' => 'array', // ← Fix: đổi từ 'setting' → 'settings', json → array
    ];

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class, 'timeslot_id');
    }

    public function promotions()
    {
        return $this->belongsToMany(
            Promotion::class,
            'promotion_room_time_slot',
            'room_time_slot_id',
            'promotion_id'
        )->withPivot(['min_quantity', 'custom_value'])->withTimestamps();
    }

    public function room()
    {
        return $this->belongsTo(Product::class, 'room_id');
    }

    // RoomTimeSlot không có field "name" nên auditLabel() mặc định của LogsAuditTrail chỉ ra được
    // "#<id>" — không đủ để biết đang nói về khung giờ của sân nào. Ghép rõ tên sân + nhãn khung
    // giờ (TimeSlot::label, vd "7h-9h") để trang "Lịch sử thao tác" đọc hiểu ngay không cần tra thêm.
    protected function auditLabel(): string
    {
        $roomName  = $this->room?->name ?? ('#' . $this->room_id);
        $slotLabel = $this->timeSlot?->label ?? ('#' . $this->timeslot_id);

        return "Sân {$roomName} — Khung giờ {$slotLabel}";
    }

    public function coupons()
    {
        return $this->belongsToMany(
            \Modules\Promotion\App\Models\Coupon::class,
            'coupon_room_time_slot',
            'room_time_slot_id',
            'coupon_id'
        );
    }

    /**
     * Kiểm tra khung giờ có bị block vào 1 ngày cụ thể không
     */
    public function isBlockedOn(string $date): bool
    {
        $blocked = $this->settings['blocked_dates'] ?? [];
        return in_array($date, $blocked);
    }

    /**
     * Lấy tất cả ngày bị block
     */
    public function getBlockedDates(): array
    {
        return $this->settings['blocked_dates'] ?? [];
    }
}