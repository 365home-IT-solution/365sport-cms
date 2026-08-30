<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\App\Models\Product;

class SlotWaitlist extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'date',
        'timeslot_id',
        'checkin_date',
        'checkout_date',
        'notified_at',
    ];

    protected $casts = [
        'product_id'    => 'string',
        'date'          => 'date',
        'checkin_date'  => 'datetime',
        'checkout_date' => 'datetime',
        'notified_at'   => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
