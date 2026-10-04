<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;

class Province extends Model
{
    use HasFactory, LogsAuditTrail;

    protected $fillable = [
        'name',
        'slug',
        'image',
        'lat',
        'lng',
        'code',
        'division_type',
        'codename',
        'phone_code',
    ];

    protected $casts = [
        'lat'        => 'float',
        'lng'        => 'float',
        'code'       => 'integer',
        'phone_code' => 'integer',
    ];

    /**
     * Mã các tỉnh/thành CÓ SÂN THẬT: có ít nhất 1 chi nhánh đang hoạt động (province_branches.status),
     * chi nhánh đó có ít nhất 1 sân đang bán (is_activated + is_in_stock, không tính HLV). Đây là nguồn
     * DUY NHẤT cho mọi nơi cần "tỉnh có sân" — API /provinces, bộ lọc Khu vực, chip tỉnh ở trang chủ —
     * để các nơi không bao giờ lệch nhau; tỉnh không có sân thì không hiển thị.
     *
     * @return list<int>
     */
    public static function idsWithCourts(): array
    {
        $activeBranches = ProvinceBranch::where('status', true)->get(['province_id', 'categorie_id']);

        $childCategoriesByParent = Category::whereIn('parent_id', $activeBranches->pluck('categorie_id')->unique()->values())
            ->get(['id', 'parent_id'])
            ->groupBy('parent_id');

        return $activeBranches
            ->filter(function (ProvinceBranch $branch) use ($childCategoriesByParent) {
                $categoryIds = collect([$branch->categorie_id])
                    ->merge($childCategoriesByParent->get($branch->categorie_id, collect())->pluck('id'));

                return Product::where('is_activated', true)->excludingCoaches()
                    ->where('is_in_stock', true)
                    ->whereHas('categories', fn ($q) => $q->whereIn('category_id', $categoryIds))
                    ->exists();
            })
            ->pluck('province_id')
            ->unique()
            ->values()
            ->all();
    }

    public function scopeWithCourts(Builder $query): Builder
    {
        return $query->whereIn('id', static::idsWithCourts());
    }

    public function branches(): HasMany
    {
        return $this->hasMany(ProvinceBranch::class);
    }

    public function wards(): HasMany
    {
        return $this->hasMany(Ward::class, 'province_code', 'code');
    }
}
