<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;

// Gói nhiều buổi / thẻ trả trước do ADMIN tạo — áp dụng cho ĐÚNG 1 TRONG 2 phạm vi (không cả 2):
// product_id (1 sân cụ thể, hành vi gốc ban đầu) HOẶC category_id (toàn bộ sân thuộc 1 chi nhánh —
// mở rộng theo yêu cầu thực tế, xem migration add_category_scope_to_package_tables). Xem
// CustomerPackage (khách đã mua) và PackageRedemption (nhật ký trừ buổi).
class PackagePlan extends Model
{
    protected $fillable = [
        'product_id',
        'category_id',
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

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function customerPackages()
    {
        return $this->hasMany(CustomerPackage::class);
    }

    // true = gói áp dụng cho TOÀN CHI NHÁNH (mọi sân thuộc category_id), false = gói riêng 1 sân
    // (product_id) — dùng ở form admin (ẩn/hiện đúng 1 trong 2 Select) và blade hiển thị phạm vi.
    public function isBranchWide(): bool
    {
        return $this->category_id !== null;
    }

    // Giá 1 buổi lẻ (không mua gói) — dùng để hiện "tiết kiệm X%" khi chào bán gói. Gói riêng 1 sân
    // lấy thẳng giá niêm yết sân đó; gói cả chi nhánh lấy giá TRUNG BÌNH các sân active trong chi
    // nhánh (không có 1 mốc giá duy nhất để so sánh như gói riêng 1 sân) — chỉ mang tính tham khảo,
    // không tính khuyến mãi ở cả 2 trường hợp.
    public function perSessionListPrice(): int
    {
        if ($this->product_id) {
            return (int) ($this->product?->price ?? 0);
        }

        if ($this->category_id) {
            // avg() trả về CHUỖI SỐ qua PDO (không phải float/int) — round() ở PHP 8+ ném TypeError
            // nếu truyền thẳng chuỗi vào, phải ép kiểu (float) trước (bug thật đã gặp lúc test).
            return (int) round((float) ($this->category?->products()->where('is_activated', true)->avg('price') ?? 0));
        }

        return 0;
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
