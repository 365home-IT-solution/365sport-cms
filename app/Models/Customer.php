<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Category\Entities\Category;
use Modules\Payment\Entities\Order;
use Modules\Promotion\App\Models\Coupon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, LogsAuditTrail;

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'fullname',
        'date_of_birth',
        'phone',
        'phone_verified_at',
        'email',
        'email_verified_at',
        'referral_code',
        'referred_by_customer_id',
        'referrer_rewarded_at',
        'status',
        'password',
        'password_updated_at',
        'token_device',
        'cccd_front',
        'cccd_back',
        'cccd_data',
        'membership_tier_id',
        'total_spending',
        // Đếm dồn số lần bị đánh dấu "không đến" (Order::markNoShow()) — xem migration
        // 2026_09_03_000003_add_no_show_count_to_customers_table. Không có ở đây do bị tăng trực
        // tiếp qua increment(), nhưng vẫn khai báo fillable để phòng khi cần set tay/import dữ liệu.
        'no_show_count',
        'welcome_coupon_sent_at',
        'province_id',
        'province_name',
        // Trình độ chơi tự khai — dùng để lọc/hiển thị ở tính năng "Tìm bạn chơi" (App\Models\OpenMatch).
        // Giá trị tự do, validate đúng tập OpenMatch::SKILL_LEVELS ở tầng API — xem migration
        // add_skill_level_to_customers_table.
        'skill_level',
        // Hồ sơ người chơi cơ bản — xem migration add_player_profile_to_customers_table.
        'height_cm',
        'weight_kg',
        'special_note',
        'favorite_position',
        'sport_levels',
        'play_goals',
        'play_frequency',
    ];

    // Mục tiêu chơi thể thao & tần suất chơi: mã => nhãn hiển thị. Validate đúng tập mã này ở tầng API; app lấy
    // danh sách để dựng giao diện qua GET /api/v1/player-profile-options.
    public const PLAY_GOALS = [
        'health'      => 'Rèn luyện sức khoẻ',
        'weight_loss' => 'Giảm cân',
        'social'      => 'Giao lưu, kết bạn',
        'improve'     => 'Nâng cao kỹ năng',
        'competition' => 'Thi đấu',
        'fun'         => 'Giải trí',
    ];

    public const PLAY_FREQUENCIES = [
        'rarely'   => 'Thỉnh thoảng',
        '1_2_week' => '1-2 buổi/tuần',
        '3_4_week' => '3-4 buổi/tuần',
        'daily'    => 'Gần như mỗi ngày',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'phone_verified_at'      => 'datetime',
        'email_verified_at'      => 'datetime',
        'referrer_rewarded_at'   => 'datetime',
        'date_of_birth'          => 'date',
        'password'               => 'hashed',
        'password_updated_at'    => 'datetime',
        'cccd_data'              => 'array',
        'total_spending'         => 'decimal:2',
        'no_show_count'          => 'integer',
        'height_cm'              => 'integer',
        'weight_kg'              => 'float',
        'sport_levels'           => 'array',
        'play_goals'             => 'array',
        'welcome_coupon_sent_at' => 'datetime',
    ];

    // Filament gọi $user->name — map về fullname để tránh TypeError
    public function getNameAttribute(): string
    {
        return $this->fullname ?? '';
    }

    // Cùng lý do như getNameAttribute() ở trên: Customer dùng chung Sanctum nên đôi lúc bị code
    // dùng chung cho cả User lẫn Customer gọi nhầm các method chỉ có ở User (vd isSuperAdmin()),
    // gây BadMethodCallException/500. Khách hàng KHÔNG BAO GIỜ là super admin nên trả về false là
    // đúng về nghĩa — chỉ chặn crash, không đổi hành vi phân quyền thật nào của User.
    public function isSuperAdmin(): bool
    {
        return false;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }

            if (empty($model->referral_code)) {
                do {
                    $code = strtoupper(Str::random(6));
                } while (self::where('referral_code', $code)->exists());
                $model->referral_code = $code;
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('status') && $model->status === self::STATUS_INACTIVE) {
                $model->tokens()->delete();
            }
        });
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function membershipTier(): BelongsTo
    {
        return $this->belongsTo(MembershipTier::class, 'membership_tier_id');
    }

    // Chi nhánh GỐC (parent_id=null) mà khách hàng thuộc về — tự động gán TOÀN BỘ chi nhánh gốc
    // trong phạm vi quyền của user tạo (CustomerController::store(), cùng nguồn với field
    // "categories" ở POST /api/admin/login — xem User::rootProductCategoryIds()), KHÔNG cho FE tự
    // chọn tay. KHÔNG dùng để giới hạn ai xem được khách hàng này, xem ghi chú tại
    // CustomerController::index().
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'customer_categories', 'customer_id', 'category_id')
            ->withTimestamps();
    }

    public function membershipLogs(): HasMany
    {
        return $this->hasMany(CustomerMembershipLog::class, 'customer_id');
    }

    public function personalCoupons(): HasMany
    {
        return $this->hasMany(Coupon::class, 'customer_id');
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_customers', 'customer_id', 'coupon_id')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class, 'customer_id');
    }

    public function waitlists(): HasMany
    {
        return $this->hasMany(SlotWaitlist::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    // Kèo "Tìm bạn chơi" mà khách này ĐANG là host — xem App\Models\OpenMatch.
    public function openMatches(): HasMany
    {
        return $this->hasMany(OpenMatch::class, 'host_customer_id');
    }

    // CCCD người đi cùng đã lưu sẵn, tái sử dụng cho các lần đặt sân qua đêm sau này — xem
    // migration 2026_07_20_000002_create_customer_companions_table.
    public function companions(): HasMany
    {
        return $this->hasMany(CustomerCompanion::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_customer_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_customer_id');
    }

    // Lịch sử được gán làm nhân viên của (các) tài khoản doanh nghiệp B2B — xem
    // App\Models\CorporateAccount/CorporateAccountEmployee. 1 khách có thể từng thuộc nhiều công
    // ty khác nhau theo thời gian (đổi việc), removed_at đánh dấu lượt nào đã bị gỡ.
    public function corporateAccountEmployments(): HasMany
    {
        return $this->hasMany(CorporateAccountEmployee::class);
    }
}
