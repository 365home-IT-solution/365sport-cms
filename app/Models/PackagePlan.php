<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToPartner;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Modules\Category\Entities\Category;
use Modules\Product\App\Models\Product;

// Gói nhiều buổi / thẻ trả trước do ADMIN tạo — áp dụng cho ĐÚNG 1 TRONG 2 phạm vi (không cả 2):
// product_id (1 sân cụ thể, hành vi gốc ban đầu) HOẶC category_id (toàn bộ sân thuộc 1 chi nhánh —
// mở rộng theo yêu cầu thực tế, xem migration add_category_scope_to_package_tables). Xem
// CustomerPackage (khách đã mua) và PackageRedemption (nhật ký trừ buổi).
class PackagePlan extends Model
{
    // Cột partner_id đã có sẵn từ migration gốc (comment "lọc theo đối tác... giống các resource
    // khác") nhưng trait CHƯA từng được gắn — đối tác A trước đây thấy được TOÀN BỘ gói do đối tác
    // khác tạo (lộ dữ liệu chéo đối tác), phát hiện khi rà audit lọc chi nhánh. Global scope chỉ áp
    // trong admin panel nên không ảnh hưởng gì tới luồng khách hàng tra cứu gói.
    use BelongsToPartner, LogsAuditTrail;

    protected $fillable = [
        'product_id',
        'category_id',
        'product_ids',
        'partner_id',
        'name',
        'sessions_count',
        'price',
        'validity_days',
        'is_active',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'product_ids' => 'array',
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

    // Nhiều sân CỤ THỂ trong 1 chi nhánh (product_ids khác rỗng, kèm category_id) — xem migration
    // add_product_ids_to_package_tables. Load trực tiếp qua whereIn (không dùng belongsToMany/pivot
    // — product_ids chỉ là JSON snapshot, không cần bảng trung gian cho 1 danh sách ngắn, cố định).
    public function products()
    {
        if (empty($this->product_ids)) {
            return Product::query()->whereRaw('1 = 0');
        }

        return Product::whereIn('id', $this->product_ids);
    }

    // true = gói áp dụng cho TOÀN CHI NHÁNH (mọi sân thuộc category_id, product_ids rỗng) — dùng ở
    // form admin/blade hiển thị phạm vi. false khi là "1 sân cụ thể" HOẶC "một số sân trong chi
    // nhánh" (product_ids có danh sách) — dùng scopeType() bên dưới để phân biệt chính xác 3 loại.
    public function isBranchWide(): bool
    {
        return $this->category_id !== null && empty($this->product_ids);
    }

    // 3 phạm vi CÓ THỂ CÓ (đúng 1 trong 3, loại trừ lẫn nhau) — xem migration
    // add_product_ids_to_package_tables cho quy ước đầy đủ.
    public function scopeType(): string
    {
        if ($this->product_id !== null) {
            return 'product';
        }

        if (! empty($this->product_ids)) {
            return 'selected_products';
        }

        return 'branch';
    }

    // Kiểm tra 1 sân CỤ THỂ có nằm trong phạm vi áp dụng của gói này không — dùng ở mọi nơi khớp
    // gói ↔ sân đang xem (Book::getAvailablePackagePlans(), ProductDetail::buyPackage()...), thay
    // vì mỗi nơi tự viết lại 3 nhánh if/else dễ lệch nhau.
    public function appliesToProduct(string $productId, array $productCategoryIds = []): bool
    {
        return match ($this->scopeType()) {
            'product'           => $this->product_id === $productId,
            'selected_products' => in_array($productId, $this->product_ids, true),
            'branch'            => $this->category_id !== null && in_array($this->category_id, $productCategoryIds, true),
        };
    }

    // Giá 1 buổi lẻ (không mua gói) — dùng để hiện "tiết kiệm X%" khi chào bán gói. Trung bình giá
    // các KHUNG GIỜ THẬT (RoomTimeSlot, chỉ tính khung giờ LẶP LẠI/tiêu chuẩn — date IS NULL, không
    // tính ngày override riêng lẻ) của (các) sân liên quan — KHÔNG dùng cột products.price (giá
    // niêm yết chung 1 mốc duy nhất cho cả sân). PHÁT HIỆN THỰC TẾ (bug thật đã gặp): products.price
    // bị bỏ mặc định 120.000đ CỐ ĐỊNH cho MỌI sân, không hề đồng bộ theo giá khung giờ thật (thực tế
    // dao động 20.000–50.000đ tuỳ giờ) — dùng cột đó khiến savingsPercent() SAI LỆCH NGHIÊM TRỌNG
    // (hiện "tiết kiệm 83%" trong khi tính đúng theo giá khung giờ thật chỉ ~35%).
    public function perSessionListPrice(): int
    {
        $productIds = match ($this->scopeType()) {
            'product'           => array_filter([$this->product_id]),
            'selected_products' => $this->product_ids ?? [],
            'branch'            => $this->category?->products()->where('is_activated', true)->pluck('id')->all() ?? [],
        };

        if (empty($productIds)) {
            return 0;
        }

        // avg() trả về CHUỖI SỐ qua PDO (không phải float/int) — round() ở PHP 8+ ném TypeError nếu
        // truyền thẳng chuỗi vào, phải ép kiểu (float) trước (bug thật đã gặp lúc test).
        $avg = \Modules\Product\App\Models\RoomTimeSlot::whereIn('room_id', $productIds)
            ->whereNull('date')
            ->avg('price');

        return (int) round((float) ($avg ?? 0));
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
