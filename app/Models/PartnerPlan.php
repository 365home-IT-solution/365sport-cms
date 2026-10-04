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
        'period_months',
        'trial_months',
        'period_discounts',
        'landing_addon_price',
        'is_default',
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
        'period_months'  => 'integer',
        'trial_months'   => 'integer',
        'period_discounts' => 'array',
        'landing_addon_price' => 'integer',
        'is_default'     => 'boolean',
        'max_branches'   => 'integer',
    ];

    protected static function booted(): void
    {
        // Chỉ có 1 gói mặc định (gói được chọn sẵn khi khách đăng ký không chọn gói).
        static::saved(function (self $plan) {
            if ($plan->is_default) {
                static::query()->where('id', '!=', $plan->id)->update(['is_default' => false]);
            }
        });
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PartnerSubscription::class, 'plan_id');
    }

    /** % giảm giá của kỳ mua $periods (0 nếu chưa cấu hình). */
    public function discountPercent(int $periods): int
    {
        return max(0, min(100, (int) (((array) $this->period_discounts)[$periods] ?? 0)));
    }

    /** Gói có bán thêm trang landing subdomain không (đã nhập giá > 0 và gói chưa gồm sẵn tính năng này). */
    public function sellsLandingAddon(): bool
    {
        return ($this->landing_addon_price ?? 0) > 0 && ! $this->hasFeature('landing_page');
    }

    /** Số tiền phải trả khi mua $periods kỳ = (giá gói + giá landing nếu mua kèm) x số kỳ, đã trừ % ưu đãi của kỳ đó. */
    public function amountFor(int $periods, bool $landingAddon = false): int
    {
        $perPeriod = $this->price + ($landingAddon && $this->sellsLandingAddon() ? (int) $this->landing_addon_price : 0);

        return (int) round($perPeriod * $periods * (100 - $this->discountPercent($periods)) / 100);
    }

    /** Các kỳ mua và số tiền tương ứng theo config subscription.period_options. */
    public function periodOptions(): array
    {
        return collect(config('subscription.period_options'))->map(fn (int $n) => [
            'months'           => $n * $this->period_months,
            'periods'          => $n,
            'original_vnd'     => $this->price * $n,
            'discount_percent' => $this->discountPercent($n),
            'amount_vnd'       => $this->amountFor($n),
            'amount_with_landing_vnd' => $this->sellsLandingAddon() ? $this->amountFor($n, true) : null,
        ])->values()->all();
    }

    public function toApi(): array
    {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'name'          => $this->name,
            'description'   => $this->description,
            'price_vnd'     => $this->price,
            'period_months' => $this->period_months,
            'trial_months'  => $this->trial_months,
            'max_branches'  => $this->max_branches,
            'features'      => (array) $this->features,
            'is_highlighted' => $this->is_highlighted,
            'landing_addon' => [
                'included'  => $this->hasFeature('landing_page'),
                'available' => $this->sellsLandingAddon(),
                'price_vnd' => $this->sellsLandingAddon() ? $this->landing_addon_price : null,
            ],
            'periods'       => $this->periodOptions(),
        ];
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features ?? [], true);
    }
}
