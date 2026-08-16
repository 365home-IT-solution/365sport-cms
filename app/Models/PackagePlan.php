<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Product\App\Models\Product;

// Gói nhiều buổi / thẻ trả trước do ADMIN tạo cho 1 sân cụ thể — xem CustomerPackage (khách đã
// mua) và PackageRedemption (nhật ký trừ buổi). Xem ghi chú đầy đủ tại migration
// create_package_plans_table.
class PackagePlan extends Model
{
    protected $fillable = [
        'product_id',
        'partner_id',
        'name',
        'sessions_count',
        'price',
        'validity_days',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function customerPackages()
    {
        return $this->hasMany(CustomerPackage::class);
    }

    // Giá 1 buổi lẻ (không mua gói) của sân — dùng để hiện "tiết kiệm X%" khi chào bán gói. Sân
    // style=1 (khung giờ) giá có thể khác nhau theo từng khung giờ (room_time_slots.price) — lấy
    // giá NIÊM YẾT gốc của sân (product.price) làm mốc so sánh đơn giản, không tính khuyến mãi.
    public function perSessionListPrice(): int
    {
        return (int) ($this->product?->price ?? 0);
    }

    public function savingsPercent(): int
    {
        $listTotal = $this->perSessionListPrice() * $this->sessions_count;
        if ($listTotal <= 0) {
            return 0;
        }

        return (int) round((1 - ($this->price / $listTotal)) * 100);
    }
}
