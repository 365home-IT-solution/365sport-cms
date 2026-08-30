<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Lượt xin tham gia quỹ nhóm — xem ghi chú migration create_booking_fund_members_table.
class BookingFundMember extends Model
{
    protected $fillable = [
        'booking_fund_id',
        'customer_id',
        'status',
        'requested_at',
        'decided_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at'   => 'datetime',
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
