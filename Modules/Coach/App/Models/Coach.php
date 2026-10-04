<?php

namespace Modules\Coach\App\Models;

use App\Models\Concerns\BelongsToPartner;
use Illuminate\Database\Eloquent\Model;
use Modules\Category\Entities\Category;
use Illuminate\Support\Str;

class Coach extends Model
{
    use BelongsToPartner;

    protected $fillable = [
        'partner_id', 'category_id', 'name', 'slug', 'phone', 'email', 'photo',
        'specialties', 'certifications', 'bio', 'years_experience', 'hourly_rate', 'is_active',
    ];

    protected $casts = [
        'years_experience' => 'integer',
        'hourly_rate' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $coach): void {
            $coach->slug ??= Str::slug($coach->name) . '-' . Str::lower(Str::random(6));
            $coach->partner_id ??= Category::query()->whereKey($coach->category_id)->value('partner_id');
        });
    }

    public function branch()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function bookings()
    {
        return $this->hasMany(OrderCoach::class);
    }
}
