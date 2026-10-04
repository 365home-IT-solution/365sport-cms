<?php

namespace Modules\Coach\App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Payment\Entities\Order;

class OrderCoach extends Model
{
    protected $fillable = [
        'order_id', 'coach_id', 'coach_name', 'hourly_rate', 'minutes', 'subtotal', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'hourly_rate' => 'integer', 'minutes' => 'integer', 'subtotal' => 'integer',
        'starts_at' => 'datetime', 'ends_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function coach() { return $this->belongsTo(Coach::class); }
}
