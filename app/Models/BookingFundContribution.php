<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Từng lượt chuyển khoản góp quỹ, kèm ảnh biên lai — xem ghi chú migration
// create_booking_fund_contributions_table.
class BookingFundContribution extends Model
{
    protected $fillable = [
        'booking_fund_id',
        'customer_id',
        'amount',
        'receipt_photo',
        'status',
        'note',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function fund()
    {
        return $this->belongsTo(BookingFund::class, 'booking_fund_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
