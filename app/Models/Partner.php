<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Category\Entities\Category;
use Modules\Employee\Entities\Employee;
use Modules\Product\App\Models\Product;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Partner extends Model implements HasMedia
{
    use HasUuids, SoftDeletes, InteractsWithMedia, LogsAuditTrail;

    protected $fillable = [
        // Cơ bản
        'name',
        'slug',
        'landing_primary_color',
        // Chọn 1 trong nhiều mẫu giao diện trang landing — xem
        // BladeThemeV1Controller::partnerLandingPage() (chọn view theo giá trị này) và
        // PartnerForm::landingPageTab() (Select cho admin chọn, có xem trước từng mẫu).
        'landing_template',
        'landing_page_enabled',
        'landing_tagline',
        'landing_description',
        'landing_facebook_url',
        'landing_hero_video_url',
        'landing_features',
        'landing_services',
        'landing_stats',
        'landing_testimonials',
        'landing_pricing_plans',
        'landing_faqs',
        'landing_news',
        // null = theo GeneralSettings::loyalty_discount_enabled (cài đặt chung) — chỉ khác null khi
        // đối tác tự ghi đè riêng. Xem effectiveLoyaltyDiscountEnabled()/Rate2ndOrder()/Rate3rdPlus().
        'loyalty_discount_enabled',
        'loyalty_discount_rate_2nd_order',
        'loyalty_discount_rate_3rd_plus',
        'tax_code',
        'phone',
        'email',
        'address',
        'status',
        'created_by',

        // Người đại diện
        'representative_name',
        'representative_dob',
        'representative_id_number',
        'representative_phone_secondary',

        // Doanh nghiệp
        'legal_name',
        'business_license_date',
        'business_license_issuer',

        // Tài chính
        'bank_name',
        'bank_bin',
        'payos_client_id',
        'payos_api_key',
        'payos_checksum_key',
        'payment_method_preference',
        'bank_branch',
        'bank_account_number',
        'bank_account_holder',
        'momo_phone',
        'zalopay_id',
        'vnpay_id',
        'paypal_email',
        'wise_account',
        'swift_code',
        'payment_cycle',

        // Xác minh & vận hành
        'verification_status',
        'is_platform_partner',
        'partner_plan_id',
        'onboarding_token',
        'signup_plan_id',
        'signup_periods',
        'signup_landing_addon',

        // Hợp đồng
        'contract_code',
        'contract_type',
        'contract_status',
        'contract_signed_at',
        'contract_expires_at',
        'commission_rate',
        'cancellation_policy',
    ];

    protected $casts = [
        'status'                => 'boolean',
        'is_platform_partner'   => 'boolean',
        'signup_landing_addon'  => 'boolean',
        'landing_page_enabled'  => 'boolean',
        // nullable — GIỮ NGUYÊN null khi chưa cấu hình (không ép về false), xem
        // effectiveLoyaltyDiscountEnabled().
        'loyalty_discount_enabled'        => 'boolean',
        'loyalty_discount_rate_2nd_order' => 'float',
        'loyalty_discount_rate_3rd_plus'  => 'float',
        // encrypted: Laravel tự mã hoá/giải mã khi ghi/đọc — không bao giờ lưu API key/checksum
        // PayOS dạng plain text trong DB (xem PayOSCredentialResolver, migration
        // add_payos_credentials_to_partners_table).
        'payos_client_id'       => 'encrypted',
        'payos_api_key'         => 'encrypted',
        'payos_checksum_key'    => 'encrypted',
        'landing_features'      => 'array',
        'landing_services'      => 'array',
        'landing_stats'         => 'array',
        'landing_testimonials'  => 'array',
        'landing_pricing_plans' => 'array',
        'landing_faqs'          => 'array',
        'landing_news'          => 'array',
        'representative_dob'    => 'date',
        'business_license_date' => 'date',
        'contract_signed_at'    => 'date',
        'contract_expires_at'   => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('representative_id_front')->singleFile();
        $this->addMediaCollection('representative_id_back')->singleFile();
        $this->addMediaCollection('bank_card_image')->singleFile();
        $this->addMediaCollection('contract_file')->singleFile();
        $this->addMediaCollection('verification_documents');
        // Logo hiển thị ở header/footer trang landing page riêng theo subdomain (xem
        // BladeThemeV1Controller::partnerLandingPage(), pages/partner-landing.blade.php).
        $this->addMediaCollection('landing_logo')->singleFile();
        // Ảnh hero (banner lớn đầu trang landing) — xem pages/partner-landing.blade.php. 2 ảnh cho
        // bố cục bento (tham khảo mẫu Mona Pickleball): landing_hero_image = ảnh cao bên phải (đi
        // kèm nút Play video), landing_hero_secondary = ảnh ngang phía trên cột giữa.
        $this->addMediaCollection('landing_hero_image')->singleFile();
        $this->addMediaCollection('landing_hero_secondary')->singleFile();
        // Ảnh minh hoạ cạnh khối FAQ (bố cục 2 cột: ảnh trái + danh sách câu hỏi phải).
        $this->addMediaCollection('landing_faq_image')->singleFile();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Tài khoản chủ đối tác + toàn bộ nhân viên đăng nhập được của đối tác này.
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Tài khoản đăng nhập của chủ đối tác (role 'partner') — dùng hiển thị "Email đăng nhập hệ
    // thống" ở tab Người đại diện, không lưu trùng lặp email đăng nhập trên bảng partners.
    public function owner(): ?User
    {
        return $this->users()->whereHas('roles', fn ($q) => $q->where('name', 'partner'))->first();
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function isPlatformPartner(): bool
    {
        return (bool) $this->is_platform_partner;
    }

    // Chi nhánh (categories loại 'product') do đối tác này quản lý.
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function contractVersions(): HasMany
    {
        return $this->hasMany(PartnerContractVersion::class)->latest();
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(PartnerStatusLog::class)->latest();
    }

    // Đăng ký gói hiện tại (mỗi đối tác đúng 1 dòng) — xem SubscriptionService.
    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PartnerSubscription::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PartnerPlan::class, 'partner_plan_id');
    }

    // Đối tác đã tự cấu hình đủ 3 khoá PayOS riêng — xem PayOSCredentialResolver::resolve(), nơi
    // QUYẾT ĐỊNH THỨ TỰ ưu tiên thật (PayOS riêng > chuyển khoản > PayOS chung nền tảng).
    public function hasPayosConfigured(): bool
    {
        return (bool) ($this->payos_client_id && $this->payos_api_key && $this->payos_checksum_key);
    }

    // PayOS được coi là "khả dụng" cho đối tác này khi: (a) đối tác tự cấu hình PayOS riêng, HOẶC
    // (b) đối tác CHƯA cấu hình chuyển khoản ngân hàng — trường hợp (b) sẽ rơi xuống PayOS CHUNG
    // của nền tảng (PayOSCredentialResolver, fallback cuối, giữ nguyên hành vi cũ cho đối tác chưa
    // đụng tới tính năng nào trong 2 tính năng mới này). Nếu đối tác ĐÃ cấu hình chuyển khoản
    // nhưng CHƯA cấu hình PayOS riêng → false (yêu cầu người dùng: "đối tác không setup payos thì
    // dùng [chuyển khoản] để thanh toán" — ép hẳn về chuyển khoản, KHÔNG lặng lẽ dùng PayOS chung).
    public function payosAvailable(): bool
    {
        return $this->hasPayosConfigured() || ! $this->hasBankTransferInfo();
    }

    // Phương thức thanh toán THẬT SỰ áp dụng cho đối tác này — ĐIỂM DUY NHẤT quyết định (dùng ở cả
    // ProductDetail::resolveEffectivePaymentMethod() và view hiển thị, đảm bảo client/server luôn
    // khớp nhau). Admin chọn CHÍNH XÁC 1 trong 3 lựa chọn qua 'payment_method_preference' (PayOS /
    // Chuyển khoản-SePay tự động / Chuyển khoản-xác nhận ảnh thủ công) — KHÔNG còn tự suy luận
    // ngầm dựa theo đã cấu hình gì hay chưa (trước đây hễ có STK ngân hàng nhưng thiếu PayOS riêng
    // là TỰ ĐỘNG ép sang chuyển khoản dù admin không chọn vậy, gây cảm giác "trộn 2 loại" — bỏ hẳn
    // theo yêu cầu). Duy nhất 1 fallback AN TOÀN: chọn chuyển khoản (SePay/thủ công) nhưng CHƯA
    // điền đủ STK/tên chủ TK/mã ngân hàng thì rơi về 'payos' — còn hơn không có QR nào để hiện cho
    // khách. 'bank_transfer' (giá trị CŨ, 2 lựa chọn trước khi tách SePay/thủ công riêng) vẫn được
    // coi là "muốn chuyển khoản" để không phá dữ liệu đối tác đã cấu hình từ trước.
    public function effectivePaymentMethod(): string
    {
        $wantsBankTransfer = in_array(
            $this->payment_method_preference,
            ['bank_transfer', 'bank_transfer_sepay', 'bank_transfer_manual'],
            true
        );

        if ($wantsBankTransfer && $this->hasBankTransferInfo()) {
            return 'bank_transfer';
        }

        return 'payos';
    }

    // Đối tác có bật/tắt/chỉnh tỷ lệ "giảm giá khách quen" RIÊNG cho mình hay không — 3 cột đều
    // nullable, null = CHƯA ghi đè, dùng nguyên cài đặt chung (GeneralSettings, xem
    // ManageGeneral.php tab "Giảm giá khách quen"). Đọc bằng 3 hàm effective*() dưới đây thay vì
    // đọc GeneralSettings trực tiếp — dùng ở ProductDetail::getLoyaltyDiscountRate().
    public function effectiveLoyaltyDiscountEnabled(): bool
    {
        if ($this->loyalty_discount_enabled !== null) {
            return $this->loyalty_discount_enabled;
        }

        return (bool) app(\App\Settings\GeneralSettings::class)->loyalty_discount_enabled;
    }

    public function effectiveLoyaltyDiscountRate2ndOrder(): float
    {
        if ($this->loyalty_discount_rate_2nd_order !== null) {
            return (float) $this->loyalty_discount_rate_2nd_order;
        }

        return (float) app(\App\Settings\GeneralSettings::class)->loyalty_discount_rate_2nd_order;
    }

    public function effectiveLoyaltyDiscountRate3rdPlus(): float
    {
        if ($this->loyalty_discount_rate_3rd_plus !== null) {
            return (float) $this->loyalty_discount_rate_3rd_plus;
        }

        return (float) app(\App\Settings\GeneralSettings::class)->loyalty_discount_rate_3rd_plus;
    }

    // 'sepay' | 'manual' — CHỈ có ý nghĩa khi effectivePaymentMethod() === 'bank_transfer'. Rơi về
    // 'manual' (bắt khách upload ảnh biên lai, admin duyệt tay) nếu admin chọn "SePay tự động"
    // nhưng gói hiện tại KHÔNG (còn) có tính năng 'sepay_auto_confirm' — tránh trường hợp khách chờ
    // hệ thống tự xác nhận nhưng thực ra đối tác không có quyền dùng SePay (hạn mức giao dịch/tháng
    // của tài khoản SePay nền tảng có giới hạn, xem PartnerPlanResource::FEATURE_OPTIONS).
    public function bankTransferMode(): string
    {
        if ($this->payment_method_preference === 'bank_transfer_sepay' && $this->hasFeature('sepay_auto_confirm')) {
            return 'sepay';
        }

        return 'manual';
    }

    // Đủ thông tin để hiện mã QR chuyển khoản thủ công (xem vietQrImageUrl()) — thiếu bất kỳ 1
    // trong 3 field này thì luồng "Chuyển khoản ngân hàng" phải tự ẩn ở trang đặt sân (không có
    // QR để hiện, khách không biết chuyển vào đâu).
    public function hasBankTransferInfo(): bool
    {
        return (bool) ($this->bank_bin && $this->bank_account_number && $this->bank_account_holder);
    }

    // Sinh URL ảnh QR VietQR (dịch vụ công khai, miễn phí, không cần đăng ký merchant) theo đúng
    // STK/tên/số tiền/nội dung — dùng thẳng làm src ảnh, không cần gọi API/lưu cache gì thêm.
    // $amount tính bằng VNĐ nguyên (không có phần thập phân). $note nên là order_code để đối chiếu
    // thủ công khi admin xem sao kê.
    public function vietQrImageUrl(int $amount, string $note): ?string
    {
        if (! $this->hasBankTransferInfo()) {
            return null;
        }

        return sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?amount=%d&addInfo=%s&accountName=%s',
            $this->bank_bin,
            $this->bank_account_number,
            $amount,
            urlencode($note),
            urlencode($this->bank_account_holder)
        );
    }

    // Chưa gán gói (partner_plan_id null) = coi như KHÔNG giới hạn — tránh khoá tính năng đột
    // ngột của các đối tác đã tồn tại từ trước khi có hệ thống gói này (xem ghi chú ở migration
    // add_partner_plan_id_to_partners_table). Việc giới hạn chỉ áp dụng SAU KHI admin chủ động
    // gán 1 gói cụ thể ở tab "Hợp đồng" (PartnerForm::contractEditableFieldsSection()).
    public function hasFeature(string $key): bool
    {
        if (! $this->partner_plan_id) {
            return true;
        }

        // Trang landing subdomain có thể MUA THÊM kèm gói (PartnerSubscription::landing_addon) dù gói không gồm sẵn.
        if ($key === 'landing_page' && $this->subscription?->landing_addon && $this->subscription->isUsable()) {
            return true;
        }

        return $this->plan?->hasFeature($key) ?? true;
    }

    // null = không giới hạn số chi nhánh (chưa gán gói, hoặc gói đang gán có max_branches = null).
    public function branchLimit(): ?int
    {
        if (! $this->partner_plan_id) {
            return null;
        }

        return $this->plan?->max_branches;
    }
}
