<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employee\Entities\Employee;

class CameraAlert extends Model
{
    protected $fillable = [
        'camera_id',
        'frigate_event_id',
        'detected_at',
        'label',
        'acknowledged_by',
        'acknowledged_at',
        'note',
    ];

    protected $casts = [
        'detected_at'     => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function camera(): BelongsTo
    {
        return $this->belongsTo(Camera::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'acknowledged_by');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
