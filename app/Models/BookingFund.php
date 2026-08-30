<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;

// Quỹ nhóm góp tiền đặt sân — xem ghi chú đầy đủ tại migration create_booking_funds_table. TIỀN
// KHÔNG ĐI QUA HỆ THỐNG (chuyển thẳng vào STK cá nhân trưởng nhóm qua vietQrImageUrl() bên dưới) —
// model này chỉ theo dõi/minh bạch hoá việc góp quỹ.
class BookingFund extends Model
{
    protected $fillable = [
        'leader_customer_id',
        'invite_code',
        'title',
        'note',
        'target_amount',
        'category_id',
        'product_id',
        'slot_date',
        'slot_start_time',
        'slot_end_time',
        'bank_bin',
        'bank_account_number',
        'bank_account_holder',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'slot_date' => 'date',
    ];

    // 'status' có DEFAULT 'collecting' ở tầng DB (migration) nhưng Eloquent KHÔNG tự đọc lại giá
    // trị default đó vào object sau create() — $attributes ở đây áp dụng NGAY khi khởi tạo instance
    // mới (trước cả khi insert), nên $fund->status luôn đúng 'collecting' kể cả đọc ngay sau khi
    // tạo mà không cần refresh() (bug thật đã gặp: API trả về status=null ngay sau khi tạo quỹ).
    protected $attributes = [
        'status' => 'collecting',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $fund): void {
            if (empty($fund->invite_code)) {
                // Mã mời 8 ký tự alnum viết hoa — cùng kiểu vòng lặp while(...exists()) như
                // Order::$order_code (Modules/Payment/Entities/Order.php).
                do {
                    $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
                } while (self::where('invite_code', $code)->exists());
                $fund->invite_code = $code;
            }
        });
    }

    public function leader()
    {
        return $this->belongsTo(Customer::class, 'leader_customer_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Nhãn "sân X — 19:00-21:00, 26/08/2026" hiển thị cho thành viên (thuần thông tin, KHÔNG phải
    // booking thật — xem ghi chú migration add_slot_info_to_booking_funds_table).
    public function slotLabel(): ?string
    {
        if (! $this->product_id) {
            return null;
        }

        $parts = [$this->product?->name ?? 'Sân'];

        if ($this->slot_start_time && $this->slot_end_time) {
            $parts[] = substr($this->slot_start_time, 0, 5) . '-' . substr($this->slot_end_time, 0, 5);
        }

        if ($this->slot_date) {
            $parts[] = $this->slot_date->format('d/m/Y');
        }

        return implode(' — ', $parts);
    }

    public function members()
    {
        return $this->hasMany(BookingFundMember::class);
    }

    public function approvedMembers()
    {
        return $this->members()->where('status', 'approved');
    }

    public function contributions()
    {
        return $this->hasMany(BookingFundContribution::class);
    }

    public function messages()
    {
        return $this->hasMany(BookingFundMessage::class);
    }

    public function isLeader(?string $customerId): bool
    {
        return $customerId !== null && $this->leader_customer_id === $customerId;
    }

    // Tổng đã góp — tính ĐỘNG từ các khoản đã confirmed (xem ghi chú migration, không denormalize).
    public function collectedAmount(): int
    {
        return (int) $this->contributions()->where('status', 'confirmed')->sum('amount');
    }

    public function progressPercent(): int
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return (int) min(100, round($this->collectedAmount() / $this->target_amount * 100));
    }

    // Sinh URL ảnh QR VietQR — copy đúng logic App\Models\Partner::vietQrImageUrl() (dịch vụ công
    // khai img.vietqr.io, không cần API key), KHÔNG tách trait dùng chung để tránh động vào code
    // Partner đang chạy ổn định.
    public function vietQrImageUrl(int $amount, string $note): string
    {
        return sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
            $this->bank_bin,
            $this->bank_account_number,
            $amount,
            urlencode($note),
            urlencode($this->bank_account_holder)
        );
    }
}
