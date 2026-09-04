<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerPlan extends Model
{
    use LogsAuditTrail;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'price',
        'max_branches',
        'features',
        'extra_features',
        'sort_order',
        'is_active',
        'is_highlighted',
    ];

    protected $casts = [
        'features'       => 'array',
        'extra_features' => 'array',
        'is_active'      => 'boolean',
        'is_highlighted' => 'boolean',
        'price'          => 'integer',
        'max_branches'   => 'integer',
    ];

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features ?? [], true);
    }
}
