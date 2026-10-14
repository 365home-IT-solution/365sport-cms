<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payment\Entities\Order;
use Modules\Product\App\Models\Product;

class RoomRating extends Model
{
    protected $fillable = ['customer_id', 'room_id', 'order_id', 'star', 'comment', 'is_hidden', 'reply', 'replied_at', 'replied_by'];

    protected $casts = [
        'star' => 'integer',
        'is_hidden' => 'boolean',
        'replied_at' => 'datetime',
    ];

    // Đánh giá đang hiển thị công khai (chưa bị chủ sân ẩn).
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    // Tính lại điểm trung bình của sân từ các đánh giá đang hiển thị (đánh giá bị ẩn không tính). Dùng chung cho khách và quản trị.
    public static function recalcRoomScore(string $roomId): void
    {
        $avg = static::query()->where('room_id', $roomId)->visible()->avg('star');

        Product::query()->where('id', $roomId)->update(['rating_score' => $avg !== null ? round((float) $avg, 1) : null]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'room_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
