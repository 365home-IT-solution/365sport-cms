<?php

declare(strict_types=1);

namespace Modules\Product\App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;

class RoomType extends Model
{
    use LogsAuditTrail;

    protected $fillable = [
        'slug',
        'name',
        // sport_type/surface_type: text tự do (KHÔNG phải enum cố định) — mỗi đối tác có thể có
        // môn thể thao/loại mặt sân khác nhau, Filament form chỉ gợi ý sẵn 1 số lựa chọn phổ biến.
        'sport_type',
        'surface_type',
        'is_outdoor',
        'icon',
        'icon_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_outdoor' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
