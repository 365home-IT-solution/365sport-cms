<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\SkillLevels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerSkillLevel extends Model
{
    public const SOURCE_SELF = 'self';
    public const SOURCE_STAFF = 'staff';

    protected $fillable = ['customer_id', 'sport_type', 'level', 'source', 'assessed_by', 'assessed_at', 'note', 'assessment_requested_at'];

    protected $casts = ['assessed_at' => 'datetime', 'assessment_requested_at' => 'datetime'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }

    public function getLevelLabelAttribute(): ?string
    {
        return SkillLevels::label($this->level);
    }
}
