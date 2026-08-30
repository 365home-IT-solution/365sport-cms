<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Tin nhắn chat nhóm trong 1 quỹ — xem ghi chú migration create_booking_fund_messages_table.
class BookingFundMessage extends Model
{
    protected $fillable = [
        'booking_fund_id',
        'customer_id',
        'body',
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
