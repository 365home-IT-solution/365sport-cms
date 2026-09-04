<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Category\Entities\Category;
use Modules\Payment\Entities\Order;
use Modules\Product\App\Models\Product;

// "Tìm bạn chơi" — khách đã đặt sân (Order) mở đơn của mình cho khách khác xin ghép cùng chơi. Xem
// ghi chú đầy đủ tại migration create_open_matches_table. Đây là tính năng của KHÁCH, không thuộc
// phạm vi partner nào — hiển thị xuyên suốt nền tảng (giống cách khách duyệt sân/chi nhánh không bị
// giới hạn theo partner ở tầng khách hàng, xem BelongsToPartner: global scope chỉ áp dụng trong
// Filament admin, KHÔNG áp dụng ở API khách hàng).
class OpenMatch extends Model
{
    public const STATUS_OPEN      = 'open';
    public const STATUS_FULL      = 'full';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    // Tập giá trị hợp lệ cho min/max_skill_level và Customer::skill_level — KHÔNG phải enum DB
    // (cột là string tự do, xem ghi chú migration), validate đúng tập này ở tầng API.
    public const SKILL_LEVELS = ['beginner', 'intermediate', 'advanced', 'pro'];

    protected $fillable = [
        'order_id',
        'host_customer_id',
        'product_id',
        'category_id',
        'play_date',
        'start_time',
        'end_time',
        'players_needed',
        'players_joined',
        'min_skill_level',
        'max_skill_level',
        'price_per_player',
        'note',
        'status',
        'bank_bin',
        'bank_account_number',
        'bank_account_holder',
        'booking_fund_id',
    ];

    protected $casts = [
        'play_date'         => 'date',
        'players_needed'    => 'integer',
        'players_joined'    => 'integer',
        'price_per_player'  => 'integer',
    ];

    protected $attributes = [
        'status'         => self::STATUS_OPEN,
        'players_joined' => 0,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'host_customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function bookingFund(): BelongsTo
    {
        return $this->belongsTo(BookingFund::class, 'booking_fund_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(OpenMatchParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->where('status', OpenMatchParticipant::STATUS_JOINED);
    }

    public function isHost(?string $customerId): bool
    {
        return $customerId !== null && $this->host_customer_id === $customerId;
    }

    public function isFull(): bool
    {
        return (int) $this->players_joined >= (int) $this->players_needed;
    }

    // Tạo tự động App\Models\BookingFund khi kèo vừa đủ người — CHỈ khi host đã khai đủ 3 field
    // bank lúc tạo match (bank_bin/bank_account_number/bank_account_holder), coi đây là host CHỦ
    // ĐỘNG chọn dùng cơ chế thu tiền có sẵn thay vì tự thu xếp ngoài app. target_amount = giá/người
    // (nếu có) nhân với TỔNG số người chơi (host + số cần thêm) — chỉ là số gợi ý ban đầu, trưởng
    // nhóm (host) vẫn có thể sửa quỹ như bình thường qua API booking-funds sau khi tạo. Best-effort:
    // không throw nếu tạo quỹ lỗi, chỉ bỏ qua — KHÔNG được để lỗi tạo quỹ chặn luồng join/full chính.
    public function maybeCreateBookingFund(): void
    {
        if ($this->booking_fund_id) {
            return;
        }

        if (! $this->bank_bin || ! $this->bank_account_number || ! $this->bank_account_holder) {
            return;
        }

        try {
            $totalPlayers = (int) $this->players_needed + 1;
            $targetAmount = $this->price_per_player
                ? (int) $this->price_per_player * $totalPlayers
                : 1000;

            $fund = BookingFund::create([
                'leader_customer_id'  => $this->host_customer_id,
                'title'               => 'Quỹ ghép kèo — Tìm bạn chơi #' . $this->id,
                'note'                => $this->note,
                'target_amount'       => max(1000, $targetAmount),
                'category_id'         => $this->category_id,
                'product_id'          => $this->product_id,
                'slot_date'           => $this->play_date,
                'slot_start_time'     => $this->start_time,
                'slot_end_time'       => $this->end_time,
                'bank_bin'            => $this->bank_bin,
                'bank_account_number' => $this->bank_account_number,
                'bank_account_holder' => $this->bank_account_holder,
            ]);

            $this->update(['booking_fund_id' => $fund->id]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('OpenMatch: maybeCreateBookingFund failed', [
                'open_match_id' => $this->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
