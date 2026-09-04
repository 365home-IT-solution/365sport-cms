<?php

declare(strict_types=1);

namespace Modules\AppPage\App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    use LogsAuditTrail;

    protected $fillable = [
        'title',
        'url',
        'description',
        'image',
        'disk',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        return Storage::disk($this->disk ?? 'public')->url($this->image);
    }
}
