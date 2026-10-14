<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    protected $fillable = ['customer_id', 'type', 'points', 'balance_after', 'order_id', 'coupon_code', 'note', 'created_by'];

    protected $casts = ['points' => 'integer', 'balance_after' => 'integer'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
