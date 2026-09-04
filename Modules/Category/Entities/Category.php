<?php

namespace Modules\Category\Entities;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Modules\DataPermission\Entities\UserBranchPermission;
use Modules\Post\Entities\Post;
use Modules\Product\App\Models\Product;
use Modules\Payment\Entities\Order;

class Category extends Model
{
    // CategoryObserver (app/Observers/CategoryObserver.php, đăng ký ở EventServiceProvider) tồn
    // tại nhưng KHÔNG gọi AuditLogger::log() — Category thực chất chưa từng được audit trước đây.
    // Gắn trait ở đây để lấp đúng lỗ hổng đó, không đụng gì tới CategoryObserver (observer đó vẫn
    // đang lo việc khác, xem file đó).
    use LogsAuditTrail;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
        'category_type',
        'status',
        'image',
        'partner_id',
        // Chi tiết vận hành (chỉ có ý nghĩa với category_type = product, xem
        // Modules/Category/App/Filament/Resources/CategoryResource/Pages/BranchDetail.php)
        'area_sqm',
        'established_year',
        'lodging_type',
        'timezone',
        'operation_manager_name',
        'checkin_time',
        'checkout_time',
        'default_policy',
    ];

    protected $casts = [
        'status'     => 'boolean',
        'area_sqm'   => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class);
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function posts()
    {
        return $this->morphedByMany(Post::class, 'categorizable');
    }

    public function products()
    {
        return $this->morphedByMany(Product::class, 'categorizable');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'category_id');
    }

    public function branchPermissions()
    {
        return $this->hasMany(UserBranchPermission::class, 'category_id');
    }

    // Tỉnh/Thành + Phường/Xã thật của chi nhánh (category_type=product) — dùng để lọc trang bản đồ
    // /ban-do (Modules\BladeThemeV1\Livewire\MapPage) và trang tìm kiếm (RoomSearchService). Chỉ
    // có ý nghĩa với chi nhánh, category "khu vực/bài viết" không cần quan hệ này.
    public function provinceBranch()
    {
        return $this->hasOne(\App\Models\ProvinceBranch::class, 'categorie_id');
    }

    public function isAssignedToUserPermission(): bool
    {
        return $this->branchPermissions()->exists();
    }
}
