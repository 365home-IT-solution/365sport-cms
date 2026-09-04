<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenMatchParticipant extends Model
{
    public const STATUS_JOINED = 'joined';
    public const STATUS_LEFT   = 'left';

    protected $fillable = [
        'open_match_id',
        'customer_id',
        'status',
    ];

    public function openMatch(): BelongsTo
    {
        return $this->belongsTo(OpenMatch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
