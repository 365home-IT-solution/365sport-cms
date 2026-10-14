<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerPlan;
use App\Models\PartnerStatusLog;
use App\Models\Province;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\Ward;
use App\Mail\LockNotificationMail;
use App\Services\Concerns\NotifiesUsers;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

// ĐĂNG KÝ ĐỐI TÁC = MUA GÓI (port từ luồng MiniHouse, không có hồ sơ giấy tờ/hợp đồng):
// khách chọn gói + số tháng, nhập thông tin liên hệ → tạo hồ sơ đối tác CHƯA kích hoạt.
//  • Đủ điều kiện tặng dùng thử (lần đầu, tính năng bật): hồ sơ CHỜ SUPER ADMIN DUYỆT — duyệt xong mới tặng dùng thử,
//    kích hoạt đối tác, tạo tài khoản và gửi email đăng nhập (approveSignup). Không tạo đơn thanh toán ở bước này.
//  • Không đủ điều kiện: tạo đơn thanh toán + QR PayOS; thanh toán xong tự kích hoạt (provisionPurchasedAccount).
// Khách theo dõi đơn bằng mã đơn trả về lúc đăng ký (DB chỉ lưu sha256 của mã).
class PartnerOnboardingService
{
    use NotifiesUsers;

    public const LABELS = [
        'plan_id' => 'gói dịch vụ', 'periods' => 'số tháng', 'full_name' => 'họ tên người đăng ký', 'phone' => 'số điện thoại',
        'email' => 'email', 'business_name' => 'tên cơ sở kinh doanh', 'address' => 'địa chỉ',
        'address_street' => 'số nhà, tên đường/phố', 'address_province_code' => 'tỉnh/thành phố', 'address_ward_code' => 'phường/xã',
        'address_unit' => 'căn hộ/tầng', 'address_building' => 'tên toà nhà', 'postal_code' => 'mã bưu điện',
        'landing_addon' => 'trang landing',
    ];

    /** Email đã thuộc tài khoản đăng nhập còn dùng? Tài khoản "mồ côi" của đối tác đã xoá không tính (sẽ được giải phóng khi cấp tài khoản mới). */
    public function emailTaken(?string $email, ?string $exceptPartnerId = null): bool
    {
        if (blank($email)) {
            return false;
        }
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return false;
        }
        if ($exceptPartnerId && $user->partner_id === $exceptPartnerId) {
            return false;
        }

        return ! ($user->partner_id && Partner::onlyTrashed()->whereKey($user->partner_id)->exists());
    }

    private function assertEmailFree(?string $email, ?string $exceptPartnerId = null): void
    {
        if ($this->emailTaken($email, $exceptPartnerId)) {
            throw ValidationException::withMessages(['email' => 'Email này đã được dùng cho một tài khoản khác. Vui lòng dùng email khác.']);
        }
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function findByToken(string $token): Partner
    {
        return Partner::query()->where('onboarding_token', self::hashToken($token))->firstOrFail();
    }

    /**
     * Ghép địa chỉ đầy đủ từ các phần: "Căn hộ, Toà nhà, Số nhà đường, Phường/Xã, Tỉnh/TP". Không có phần có cấu trúc thì giữ nguyên `address`.
     * Phường/xã phải thuộc đúng tỉnh/thành đã chọn.
     */
    public function composeAddress(array $data): string
    {
        if (blank($data['address_street'] ?? null)) {
            return (string) ($data['address'] ?? '');
        }

        $province = Province::query()->where('code', $data['address_province_code'])->first();
        $ward = Ward::query()->where('code', $data['address_ward_code'])->first();
        if (! $province || ! $ward || (int) $ward->province_code !== (int) $province->code) {
            throw ValidationException::withMessages(['address_ward_code' => 'Phường/xã không thuộc tỉnh/thành phố đã chọn.']);
        }

        return collect([$data['address_unit'] ?? null, $data['address_building'] ?? null, $data['address_street'], $ward->name, $province->name])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(', ');
    }

    /**
     * Tạo hồ sơ đối tác CHƯA kích hoạt (chờ duyệt/chờ thanh toán) hoặc dùng lại hồ sơ chưa kích hoạt cùng SĐT.
     *
     * @return array{partner: Partner, token: string}
     */
    public function createPurchasePartner(array $data): array
    {
        $phone = preg_replace('/^\+84/', '0', $data['phone']);

        $existing = Partner::query()->where('phone', $phone)->first();
        if ($existing && ($existing->status || $existing->users()->exists())) {
            throw ValidationException::withMessages(['phone' => 'Số điện thoại này đã có tài khoản đối tác. Vui lòng đăng nhập trang quản trị để gia hạn gói.']);
        }
        $this->assertEmailFree($data['email'], $existing?->id);

        $token = Str::random(48);
        $attributes = [
            'name'                => $data['business_name'],
            'legal_name'          => $data['business_name'],
            'representative_name' => $data['full_name'],
            'phone'               => $phone,
            'email'               => $data['email'],
            'address'             => $data['address'],
            'onboarding_token'    => self::hashToken($token),
        ];

        // Đăng ký CHỈ nhập thông tin (không chọn gói, không thanh toán): hồ sơ luôn CHỜ SUPER ADMIN DUYỆT. Duyệt xong mới tạo tài khoản + gửi mật khẩu;
        // tặng tháng đầu hay không do lúc duyệt quyết định (xem approveSignup). Hết thời gian dùng thử, đối tác tự chọn gói và thanh toán trong trang quản trị.
        $needsApproval = true;

        $partner = $existing
            ? tap($existing)->update($attributes + ($existing->verification_status === 'rejected' ? [] : ['verification_status' => $needsApproval ? 'pending' : 'approved']))
            : Partner::create($attributes + [
                'slug'                => $this->uniqueSlug($data['business_name']),
                'status'              => false,
                'verification_status' => $needsApproval ? 'pending' : 'approved',
                'contract_status'     => 'draft',
            ]);

        if (! $existing) {
            PartnerStatusLog::create(['partner_id' => $partner->id, 'to_status' => $needsApproval ? 'pending' : 'approved', 'note' => $needsApproval
                ? 'Đăng ký đối tác trên website — chờ Super Admin duyệt (được tặng tháng đầu nếu đủ điều kiện).'
                : 'Đối tác mua gói trên website (chờ thanh toán).']);
            if ($needsApproval) {
                $this->notifySuperAdmins('Có đăng ký đối tác chờ duyệt', $this->partnerLabel($partner) . ' vừa đăng ký — duyệt để tặng dùng thử và gửi tài khoản đăng nhập.', 'info', 'heroicon-o-user-plus');
                $this->mailPartner($partner, 'Đã nhận đăng ký đối tác — chờ ' . config('app.name') . ' duyệt', '<p>Xin chào <strong>' . e($partner->representative_name ?: $partner->name) . '</strong>,</p><p>' . e(config('app.name')) . ' đã nhận đăng ký đối tác của <strong>' . e($partner->name) . '</strong>. Sau khi được duyệt, tài khoản đăng nhập và mật khẩu sẽ được gửi về email này.</p>');
            }
        }

        return ['partner' => $partner->fresh(), 'token' => $token];
    }

    /** Có tặng dùng thử cho SĐT/email này không: bật tính năng, và chưa từng có đối tác (kể cả đã xoá) dùng gói với cùng SĐT/email. */
    public function signupTrialEligible(?string $phone, ?string $email, ?string $exceptPartnerId = null): bool
    {
        if ((int) config('partner_flow.signup_trial_months', 0) < 1) {
            return false;
        }
        $match = fn ($q) => $q->where(fn ($w) => $w->where('phone', $phone)->orWhere('email', $email));

        return ! Partner::onlyTrashed()->where($match)->exists()
            && ! Partner::query()->when($exceptPartnerId, fn ($q) => $q->whereKeyNot($exceptPartnerId))
                ->where($match)->whereHas('subscription')->exists();
    }

    /** Đối tác đang chờ Super Admin duyệt đăng ký (chưa có tài khoản, chưa thanh toán). */
    public function awaitingSignupApproval(Partner $partner): bool
    {
        return filled($partner->onboarding_token) && $partner->verification_status === 'pending' && ! $partner->users()->exists();
    }

    /**
     * Super Admin DUYỆT đăng ký → tặng dùng thử (mặc định 1 tháng), kích hoạt đối tác, tạo tài khoản và gửi email đăng nhập.
     * Gói lấy theo đơn khách đã chọn; khi khách thanh toán sau đó, hạn gói được cộng NỐI TIẾP sau ngày hết hạn dùng thử.
     *
     * @return array{created: bool, email: ?string, mail_sent: bool, reason: ?string, trial_expires_at: ?string}
     */
    public function approveSignup(Partner $partner, ?User $admin = null): array
    {
        if (! $this->awaitingSignupApproval($partner)) {
            throw ValidationException::withMessages(['partner' => 'Đăng ký này không ở trạng thái chờ duyệt.']);
        }
        $months = (int) config('partner_flow.signup_trial_months', 0);

        $plan = ($partner->signup_plan_id ? PartnerPlan::query()->find($partner->signup_plan_id) : null)
            ?? SubscriptionPayment::query()->where('partner_id', $partner->id)->latest('id')->first()?->plan
            ?? $this->defaultPlan();
        if (! $plan) {
            throw ValidationException::withMessages(['partner' => 'Chưa có gói đang bán để gán dùng thử.']);
        }

        $trialMonths = $plan->trial_months > 0 ? $plan->trial_months : $months;
        $by = 'Super Admin' . ($admin ? " {$admin->fullname}" : '');

        if ($trialMonths >= 1 && $this->signupTrialEligible($partner->phone, $partner->email, $partner->id)) {
            $expires = now()->addMonthsNoOverflow($trialMonths);
            app(SubscriptionService::class)->assignPlan($partner, $plan, $expires, true, (bool) $partner->signup_landing_addon);
            $this->log($partner, "{$by} duyệt đăng ký — tặng dùng thử {$trialMonths} tháng đến " . $expires->format('d/m/Y') . '.');
        } else {
            // Không được tặng (đã từng dùng thử / tính năng tắt): vẫn tạo tài khoản nhưng gói hết hạn ngay — đăng nhập sẽ vào trang "Gói dịch vụ" để chọn gói và thanh toán.
            $expires = now();
            app(SubscriptionService::class)->assignPlan($partner, $plan, $expires, false, false);
            $this->log($partner, "{$by} duyệt đăng ký — không tặng dùng thử (đã từng dùng thử hoặc tính năng tắt); đối tác cần chọn gói và thanh toán để sử dụng.");
            $trialMonths = 0;
        }

        $partner->update(['status' => true, 'verification_status' => 'approved']);
        $result = $this->provisionAccount($partner->fresh());

        return $result + ['trial_expires_at' => $trialMonths > 0 ? $expires->toIso8601String() : null, 'trial_months' => $trialMonths];
    }

    /** Super Admin TỪ CHỐI đăng ký: khoá hồ sơ, gửi email lý do (đối tác vẫn có thể thanh toán gói bằng đăng ký mới). */
    public function rejectSignup(Partner $partner, string $reason, ?User $admin = null): void
    {
        if (! $this->awaitingSignupApproval($partner)) {
            throw ValidationException::withMessages(['partner' => 'Đăng ký này không ở trạng thái chờ duyệt.']);
        }
        $partner->update(['verification_status' => 'rejected', 'status' => false]);
        $this->log($partner, 'Super Admin từ chối đăng ký. Lý do: ' . $reason);
        $this->mailPartner($partner, 'Đăng ký đối tác chưa được chấp nhận', '<p>Xin chào <strong>' . e($partner->representative_name ?: $partner->name) . '</strong>,</p><p>Đăng ký đối tác của bạn chưa được ' . e(config('app.name')) . ' chấp nhận.</p><p>Lý do: ' . e($reason) . '</p>');
    }

    /** Thanh toán gói xong → kích hoạt đối tác, tạo tài khoản chủ đối tác và gửi email đăng nhập. */
    public function provisionPurchasedAccount(Partner $partner): array
    {
        $partner->update(['status' => true, 'verification_status' => 'approved']);
        $this->log($partner, 'Đối tác đã thanh toán gói — kích hoạt.');

        return $this->provisionAccount($partner);
    }

    public function defaultPlan(): ?PartnerPlan
    {
        $q = PartnerPlan::query()->where('is_active', true);

        return (clone $q)->where('is_default', true)->first() ?? $q->orderBy('sort_order')->orderBy('id')->first();
    }

    /**
     * Tự tạo tài khoản chủ đối tác (vai trò "partner") và gửi thông tin đăng nhập qua email.
     * Bỏ qua nếu đối tác đã có tài khoản; email đã thuộc tài khoản khác → không tạo, ghi chú để admin xử lý.
     *
     * @return array{created: bool, email: ?string, mail_sent: bool, reason: ?string}
     */
    public function provisionAccount(Partner $partner): array
    {
        if ($partner->users()->exists()) {
            return ['created' => false, 'email' => null, 'mail_sent' => false, 'reason' => 'Đối tác đã có tài khoản.'];
        }

        $email = $partner->email;

        // Tài khoản "mồ côi" của đối tác đã bị xoá không được giữ email: giải phóng để cấp cho hồ sơ mới.
        if (filled($email)) {
            $orphan = User::query()->where('email', $email)->first();
            if ($orphan && $orphan->partner_id && $orphan->partner_id !== $partner->id && Partner::onlyTrashed()->whereKey($orphan->partner_id)->exists()) {
                $orphan->tokens()->delete();
                $orphan->delete();
                $this->log($partner, "Đã giải phóng email {$email} từ tài khoản của đối tác đã xoá.");
            }
        }

        if (blank($email) || User::query()->where('email', $email)->exists()) {
            $reason = blank($email) ? 'Hồ sơ không có email.' : "Email {$email} đã thuộc một tài khoản khác.";

            return $this->accountFailed($partner, $email, $reason);
        }

        $role = Role::query()->where('name', 'partner')->where('guard_name', 'web')->first();
        if (! $role) {
            return $this->accountFailed($partner, $email, 'Chưa có vai trò "partner".');
        }

        $password = Str::password(12, true, true, false);

        // SĐT người dùng là UNIQUE: đã thuộc tài khoản khác thì tạo tài khoản KHÔNG kèm SĐT thay vì lỗi 500.
        $phone = filled($partner->phone) && ! User::query()->where('phone', $partner->phone)->exists() ? $partner->phone : null;
        if ($phone === null && filled($partner->phone)) {
            $this->log($partner, "SĐT {$partner->phone} đã thuộc tài khoản khác nên tài khoản đối tác được tạo không kèm SĐT.");
        }

        DB::transaction(function () use ($partner, $email, $password, $role, $phone) {
            $user = User::create([
                'fullname'          => $partner->representative_name ?: ($partner->legal_name ?: $partner->name),
                'email'             => $email,
                'phone'             => $phone,
                'password'          => $password,
                'partner_id'        => $partner->id,
                'email_verified_at' => now(),
            ]);
            $user->roles()->syncWithoutDetaching([$role->id]);
        });

        $mailSent = $this->sendWelcomeMail($partner, $email, $password);
        $this->log($partner, "Đã tự tạo tài khoản đối tác {$email}" . ($mailSent ? ' và gửi email kèm thông tin đăng nhập.' : '. Gửi email thất bại — dùng "Gửi lại tài khoản đăng nhập" ở trang đối tác sau khi kiểm tra cấu hình mail.'));
        if (! $mailSent) {
            $this->notifySuperAdmins('Đã tạo tài khoản nhưng GỬI EMAIL THẤT BẠI', ($partner->legal_name ?: $partner->name) . " — {$email}. Kiểm tra cấu hình mail rồi gửi lại tài khoản đăng nhập.", 'danger', 'heroicon-o-exclamation-triangle');
        }

        return ['created' => true, 'email' => $email, 'mail_sent' => $mailSent, 'reason' => null];
    }

    private function accountFailed(Partner $partner, ?string $email, string $reason): array
    {
        $this->log($partner, "Không tự tạo được tài khoản đối tác: {$reason} Vui lòng tạo tài khoản thủ công.");
        $this->notifySuperAdmins('Không tự tạo được tài khoản đối tác', $this->partnerLabel($partner) . ": {$reason} Hãy xử lý rồi gửi lại tài khoản đăng nhập.", 'danger', 'heroicon-o-exclamation-triangle');

        return ['created' => false, 'email' => $email, 'mail_sent' => false, 'reason' => $reason];
    }

    /** Gửi lại thông tin đăng nhập: chưa có tài khoản thì tạo; đã có thì đặt mật khẩu mới cho tài khoản chủ đối tác rồi gửi email. */
    public function resendCredentials(Partner $partner): array
    {
        $user = $partner->users()->orderBy('created_at')->first();
        if (! $user) {
            return $this->provisionAccount($partner);
        }

        $password = Str::password(12, true, true, false);
        $user->forceFill(['password' => $password])->save();
        $mailSent = $this->sendWelcomeMail($partner, $user->email, $password);
        $this->log($partner, "Admin gửi lại thông tin đăng nhập cho {$user->email}" . ($mailSent ? '.' : ' — gửi email thất bại.'));

        return ['created' => false, 'email' => $user->email, 'mail_sent' => $mailSent, 'reason' => null];
    }

    private function sendWelcomeMail(Partner $partner, string $email, string $password): bool
    {
        $loginUrl = $this->loginUrl();
        $name = e($partner->legal_name ?: $partner->name);
        $sub = $partner->subscription;
        $trial = (bool) $sub?->is_trial;

        try {
            Mail::to($email)->send(new LockNotificationMail(
                $trial ? 'Đăng ký đối tác đã được duyệt — tài khoản đăng nhập của bạn' : 'Kích hoạt gói đối tác — tài khoản đăng nhập của bạn',
                "<p>Xin chào <strong>{$name}</strong>,</p>"
                . ($trial
                    ? '<p>' . e(config('app.name')) . ' đã <strong>duyệt đăng ký đối tác</strong> của bạn và tặng dùng thử đến ngày <strong>' . $sub->expires_at->format('d/m/Y') . '</strong>. Vui lòng thanh toán gói trước ngày này để tiếp tục sử dụng (thời hạn đã mua được cộng nối tiếp sau thời gian dùng thử).</p>'
                    : '<p>' . e(config('app.name')) . ' đã nhận thanh toán và kích hoạt gói dịch vụ cho bạn.</p>')
                . "<p>Thông tin đăng nhập trang quản trị:<br>Địa chỉ: <a href=\"{$loginUrl}\">{$loginUrl}</a><br>Email đăng nhập: <strong>" . e($email) . '</strong><br>Mật khẩu: <strong>' . e($password) . '</strong></p>'
                . '<p>Sau khi đăng nhập, việc đầu tiên là vào <strong>Quản lý → Thiết lập thanh toán</strong> để nhập tài khoản ngân hàng (hoặc khoá PayOS) — chưa thiết lập thì khách chưa đặt được sân online và bạn chưa bật bán sân được. Sau đó tạo chi nhánh, sân và bảng giá để bắt đầu nhận khách.</p><p>Vui lòng đổi mật khẩu sau khi đăng nhập lần đầu.</p>'
            ));

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    public function loginUrl(): string
    {
        try {
            return Filament::getPanel('admin')->getLoginUrl();
        } catch (\Throwable) {
            return url('/admin/login');
        }
    }

    private function log(Partner $partner, string $note): void
    {
        PartnerStatusLog::create([
            'partner_id'  => $partner->id,
            'from_status' => $partner->verification_status,
            'to_status'   => $partner->verification_status,
            'note'        => $note,
            'changed_by'  => auth()->id(),
        ]);
    }

    private function partnerLabel(Partner $partner): string
    {
        return ($partner->legal_name ?: $partner->name) . ' (' . $partner->phone . ')';
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'doi-tac';
        $slug = $base;
        $i = 2;
        while (Partner::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
