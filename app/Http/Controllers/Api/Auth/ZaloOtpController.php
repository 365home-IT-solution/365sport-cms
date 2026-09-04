<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LockNotificationMail;
use App\Models\Customer;
use App\Models\CustomerCompanion;
use App\Models\GuestCustomer;
use App\Models\MembershipTier;
use App\Services\ZaloOtpService;
use App\Settings\GeneralSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Payment\App\Services\CccdScannerService;
use Modules\Promotion\App\Models\Coupon;

class ZaloOtpController extends Controller
{
    public function __construct(protected ZaloOtpService $otp) {}

    /**
     * Gửi OTP về Zalo của khách hàng.
     * Body: { phone: "0912345678" }
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^(0|\+84)[0-9]{9}$/'],
        ]);

        if ($this->otp->hasReachedDailyLimit($request->phone)) {
            return response()->json(['message' => 'Bạn đã gửi quá nhiều OTP hôm nay. Vui lòng thử lại vào ngày mai.'], 429);
        }

        if ($this->otp->hasCooldown($request->phone)) {
            return response()->json(['message' => 'Vui lòng đợi 60 giây trước khi gửi lại OTP.'], 429);
        }

        $sent = $this->otp->send($request->phone);

        if (! $sent) {
            return response()->json([
                'message' => 'Số điện thoại chưa đăng ký Zalo hoặc không thể gửi tin nhắn. Vui lòng kiểm tra lại.',
            ], 422);
        }

        return response()->json(['message' => 'OTP đã được gửi đến Zalo của bạn. Vui lòng kiểm tra ứng dụng Zalo.']);
    }

    /**
     * Xác nhận OTP.
     * - SĐT đã có tài khoản → đăng nhập, trả Sanctum token.
     * - SĐT chưa có tài khoản → trả phone_token để tiếp tục đăng ký.
     * Body: { phone, otp }
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^(0|\+84)[0-9]{9}$/'],
            'otp'   => 'required|string|size:6',
        ]);

        if (! $this->otp->verify($request->phone, $request->otp)) {
            return response()->json(['message' => 'OTP không đúng hoặc đã hết hạn.'], 422);
        }

        $normalizedPhone = $this->otp->normalizePhone($request->phone);
        $customer        = Customer::withTrashed()->where('phone', $normalizedPhone)->first();

        if ($customer) {
            // Tài khoản bị xoá vĩnh viễn hoặc soft-delete → không cho đăng nhập/đăng ký lại
            if ($customer->trashed()) {
                return response()->json([
                    'message' => 'Tài khoản này đã bị xoá. Vui lòng liên hệ hỗ trợ.',
                ], 403);
            }

            if ($customer->status === Customer::STATUS_INACTIVE) {
                return response()->json([
                    'message' => 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ hỗ trợ để được kích hoạt lại.',
                ], 403);
            }

            $customer->phone_verified_at = now();
            $customer->save();

            $customer->tokens()->delete();
            $expiresAt = now()->addDays(30);
            $token     = $customer->createToken('mobile', ['*'], $expiresAt)->plainTextToken;

            return response()->json([
                'is_new_user' => false,
                'token'       => $token,
                'expires_at'  => $expiresAt->toIso8601String(),
                'user'        => $this->customerResource($customer),
            ]);
        }

        $phoneToken = $this->otp->storePhoneToken($normalizedPhone);

        return response()->json([
            'is_new_user' => true,
            'phone_token' => $phoneToken,
            'expires_in'  => 1800,
        ]);
    }

    /**
     * Đăng nhập bằng số điện thoại + mật khẩu.
     * Body: { phone, password }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone'    => ['required', 'string', 'regex:/^(0|\+84)[0-9]{9}$/'],
            'password' => 'required|string',
        ]);

        $normalizedPhone = $this->otp->normalizePhone($request->phone);
        $customer        = Customer::where('phone', $normalizedPhone)->first();

        if (! $customer || ! $customer->password) {
            return response()->json([
                'message' => 'Số điện thoại hoặc mật khẩu không đúng.',
            ], 401);
        }

        if ($customer->status === Customer::STATUS_INACTIVE) {
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ hỗ trợ để được kích hoạt lại.',
            ], 403);
        }

        if (! password_verify($request->password, $customer->password)) {
            return response()->json([
                'message' => 'Số điện thoại hoặc mật khẩu không đúng.',
            ], 401);
        }

        $customer->tokens()->delete();
        $expiresAt = now()->addDays(30);
        $token     = $customer->createToken('mobile', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'is_new_user' => false,
            'token'       => $token,
            'expires_at'  => $expiresAt->toIso8601String(),
            'user'        => $this->customerResource($customer),
        ]);
    }

    /**
     * Đăng nhập bằng Email + mật khẩu — PHƯƠNG THỨC THÊM cho tài khoản đã tồn tại (phone vẫn là
     * định danh chính), chỉ dùng được sau khi khách đã gắn + xác thực email qua
     * sendEmailVerificationCode()/verifyEmail() bên dưới. Cùng pattern cấp token với login().
     * Body: { email, password }
     */
    public function emailLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::where('email', $request->email)->whereNotNull('email_verified_at')->first();

        if (! $customer || ! $customer->password) {
            return response()->json(['message' => 'Email hoặc mật khẩu không đúng.'], 401);
        }

        if ($customer->status === Customer::STATUS_INACTIVE) {
            return response()->json(['message' => 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ hỗ trợ để được kích hoạt lại.'], 403);
        }

        if (! password_verify($request->password, $customer->password)) {
            return response()->json(['message' => 'Email hoặc mật khẩu không đúng.'], 401);
        }

        $customer->tokens()->delete();
        $expiresAt = now()->addDays(30);
        $token     = $customer->createToken('mobile', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'is_new_user' => false,
            'token'       => $token,
            'expires_at'  => $expiresAt->toIso8601String(),
            'user'        => $this->customerResource($customer),
        ]);
    }

    /**
     * Gửi mã 6 số xác thực email tới hòm thư — bước 1 để KHÁCH ĐÃ ĐĂNG NHẬP (bằng phone) gắn thêm
     * email vào tài khoản. Cùng cơ chế cache-based với ZaloOtpService (OTP 6 số, TTL, cooldown,
     * giới hạn số lần thử) nhưng gửi qua Mail thay vì Zalo — rập khuôn
     * ContractOtpService::sendOtp() (gửi qua LockNotificationMail).
     * Body: { email }
     */
    public function sendEmailVerificationCode(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $customer = $request->user();

        if (Customer::where('email', $request->email)->where('id', '!=', $customer->id)->exists()) {
            return response()->json(['message' => 'Email này đã được dùng cho tài khoản khác.'], 422);
        }

        $cooldownKey = 'email_verify_cooldown:' . $customer->id;
        if (Cache::has($cooldownKey)) {
            return response()->json(['message' => 'Vui lòng đợi 60 giây trước khi gửi lại mã.'], 429);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put('email_verify_otp:' . $customer->id, ['email' => $request->email, 'otp' => $otp], now()->addMinutes(10));
        Cache::put('email_verify_attempts:' . $customer->id, 0, now()->addMinutes(10));
        Cache::put($cooldownKey, true, now()->addSeconds(60));

        try {
            Mail::to($request->email)->send(new LockNotificationMail(
                'Mã xác thực email — ' . config('app.name'),
                "<p>Mã xác thực email của bạn là:</p><h2 style=\"letter-spacing:4px;\">{$otp}</h2><p>Mã có hiệu lực trong 10 phút. Vui lòng không chia sẻ mã này cho bất kỳ ai.</p>"
            ));
        } catch (\Throwable $e) {
            Log::error('Email verification mail failed', ['customer_id' => $customer->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Không gửi được email xác thực. Vui lòng thử lại sau.'], 500);
        }

        return response()->json(['message' => 'Mã xác thực đã được gửi tới email của bạn.']);
    }

    /**
     * Xác nhận mã 6 số — bước 2 gắn email, set email_verified_at, kèm đặt mật khẩu MỚI cho phương
     * thức đăng nhập bằng email (không dùng chung ô mật khẩu phone để khách có thể đặt mật khẩu
     * khác nếu muốn — nếu để trống, giữ nguyên mật khẩu hiện có của tài khoản).
     * Body: { otp, password?, password_confirmation? }
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'otp'                    => ['required', 'string', 'size:6'],
            'password'               => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation'  => ['sometimes', 'nullable', 'string'],
        ]);

        $customer = $request->user();

        $attemptKey = 'email_verify_attempts:' . $customer->id;
        $attempts   = (int) Cache::get($attemptKey, 0);
        if ($attempts >= 5) {
            return response()->json(['message' => 'Bạn đã nhập sai quá nhiều lần. Vui lòng gửi lại mã mới.'], 422);
        }

        $stored = Cache::get('email_verify_otp:' . $customer->id);
        if (! $stored || $stored['otp'] !== $request->otp) {
            Cache::put($attemptKey, $attempts + 1, now()->addMinutes(10));
            return response()->json(['message' => 'Mã xác thực không đúng hoặc đã hết hạn.'], 422);
        }

        Cache::forget('email_verify_otp:' . $customer->id);
        Cache::forget($attemptKey);

        $updates = [
            'email'             => $stored['email'],
            'email_verified_at' => now(),
        ];
        if (filled($request->password)) {
            $updates['password']            = $request->password;
            $updates['password_updated_at'] = now();
        }
        $customer->update($updates);

        return response()->json([
            'message' => 'Đã liên kết email thành công.',
            'user'    => $this->customerResource($customer),
        ]);
    }

    /**
     * Điều hướng sang màn hình chọn tài khoản Google — CHỈ dùng cho khách hàng (storefront),
     * KHÔNG áp dụng cho đăng nhập admin (Filament vẫn chỉ dùng email/mật khẩu). Client ID/Secret
     * nhập ở admin (App\Filament\Pages\GoogleLoginSettings), không qua .env.
     */
    public function googleRedirect(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (! \App\Models\GoogleOauthConfiguration::first()?->enabled) {
            return redirect()->route('login.page', ['google_error' => 'Đăng nhập Google hiện đang tắt.']);
        }

        return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
    }

    /**
     * Google gọi lại URL này sau khi khách xác nhận. Vì đây là điều hướng cả trang (không phải
     * gọi API qua fetch), KHÔNG trả JSON trực tiếp được — tạo 1 mã trao đổi dùng 1 lần (giống
     * phone_token của luồng OTP) rồi điều hướng về /dang-nhap kèm mã, JS phía form.blade.php sẽ
     * gọi googleExchange() để đổi lấy token thật. Làm vậy để KHÔNG BAO GIỜ token thật lộ ra URL/
     * lịch sử trình duyệt/log server.
     *
     * Đăng nhập Google CHỈ hoạt động với tài khoản ĐÃ tồn tại và đã liên kết + xác thực đúng email
     * Google đó (qua mục "Liên kết email" ở trang Tài khoản) — không tự tạo tài khoản mới, vì
     * 'phone' là định danh bắt buộc (NOT NULL, unique) xuyên suốt hệ thống (loyalty, đơn hàng...)
     * mà đăng nhập Google không cung cấp được.
     */
    public function googleCallback(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (! \App\Models\GoogleOauthConfiguration::first()?->enabled) {
            return redirect()->route('login.page', ['google_error' => 'Đăng nhập Google hiện đang tắt.']);
        }

        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('login.page', ['google_error' => 'Đăng nhập Google thất bại. Vui lòng thử lại.']);
        }

        $email = $googleUser->getEmail();
        if (! $email) {
            return redirect()->route('login.page', ['google_error' => 'Không lấy được email từ tài khoản Google.']);
        }

        $customer = Customer::where('email', $email)->whereNotNull('email_verified_at')->first();

        if (! $customer) {
            return redirect()->route('login.page', [
                'google_error' => 'Email Google này chưa liên kết với tài khoản nào. Vui lòng đăng ký bằng số điện thoại, sau đó liên kết email trong trang Tài khoản.',
            ]);
        }

        if ($customer->status === Customer::STATUS_INACTIVE) {
            return redirect()->route('login.page', ['google_error' => 'Tài khoản của bạn đã bị vô hiệu hóa. Vui lòng liên hệ hỗ trợ.']);
        }

        $customer->tokens()->delete();
        $expiresAt = now()->addDays(30);
        $token     = $customer->createToken('mobile', ['*'], $expiresAt)->plainTextToken;

        $exchangeCode = \Illuminate\Support\Str::random(40);
        Cache::put('google_login_exchange:' . $exchangeCode, [
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => $this->customerResource($customer),
        ], now()->addMinutes(2));

        return redirect()->route('login.page', ['google_exchange' => $exchangeCode]);
    }

    /**
     * Đổi mã trao đổi 1 lần (từ googleCallback()) lấy token thật — xem ghi chú tại googleCallback().
     */
    public function googleExchange(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:40']]);

        $cacheKey = 'google_login_exchange:' . $request->code;
        $payload  = Cache::get($cacheKey);

        if (! $payload) {
            return response()->json(['message' => 'Mã đăng nhập Google không hợp lệ hoặc đã hết hạn.'], 422);
        }

        Cache::forget($cacheKey);

        return response()->json($payload);
    }

    /**
     * Mã giới thiệu bạn bè của khách đang đăng nhập + số bạn đã giới thiệu.
     */
    public function referral(Request $request): JsonResponse
    {
        $customer = $request->user();
        $settings = app(GeneralSettings::class);

        return response()->json([
            'enabled'          => $settings->referral_enabled,
            'referral_code'    => $customer->referral_code,
            'referred_count'   => $customer->referrals()->count(),
            'reward_referee_percent'  => $settings->referral_reward_referee_percent,
            'reward_referrer_percent' => $settings->referral_reward_referrer_percent,
        ]);
    }

    /**
     * Tạo tài khoản khách hàng sau khi xác thực OTP.
     * Body: { phone_token, fullname, date_of_birth, password, password_confirmation }
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'phone_token'          => 'required|string|size:64',
            'fullname'             => 'required|string|max:255',
            'date_of_birth'        => 'required|date_format:d-m-Y|before:today',
            'password'             => 'sometimes|nullable|string|min:8|confirmed',
            'password_confirmation'=> 'sometimes|nullable|string',
            'referral_code'        => 'sometimes|nullable|string|max:20',
        ]);

        $normalizedPhone = $this->otp->getPhoneByToken($request->phone_token);

        if (! $normalizedPhone) {
            return response()->json([
                'message' => 'Phiên đăng ký đã hết hạn (30 phút). Vui lòng thực hiện lại từ đầu.',
            ], 422);
        }

        if (Customer::where('phone', $normalizedPhone)->exists()) {
            return response()->json([
                'message'  => 'Số điện thoại này đã có tài khoản. Vui lòng đăng nhập.',
                'redirect' => 'login',
            ], 409);
        }

        $this->otp->consumePhoneToken($request->phone_token);

        $referrer = null;
        if (filled($request->referral_code)) {
            $referrer = Customer::where('referral_code', strtoupper($request->referral_code))->first();
        }

        $now      = now();
        $hasPassword = filled($request->password);
        $customer = Customer::create([
            'phone'                   => $normalizedPhone,
            'fullname'                => $request->fullname,
            'date_of_birth'           => Carbon::createFromFormat('d-m-Y', $request->date_of_birth)->toDateString(),
            'phone_verified_at'       => $now,
            'status'                  => Customer::STATUS_ACTIVE,
            'password'                => $hasPassword ? $request->password : null,
            'password_updated_at'     => $hasPassword ? $now : null,
            'referred_by_customer_id' => $referrer?->id,
        ]);

        // Liên kết khách vãng lai bằng SĐT
        GuestCustomer::where('phone', $normalizedPhone)
            ->whereNull('customer_id')
            ->update(['customer_id' => $customer->id]);

        if ($referrer) {
            $this->grantRefereeCoupon($customer);
        }

        $expiresAt = now()->addDays(30);
        $token     = $customer->createToken('mobile', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'token'       => $token,
            'expires_at'  => $expiresAt->toIso8601String(),
            'is_new_user' => true,
            'user'        => $this->customerResource($customer),
        ], 201);
    }

    // Cấp coupon cá nhân cho NGƯỜI ĐƯỢC GIỚI THIỆU ngay lúc đăng ký thành công — coupon cho NGƯỜI
    // GIỚI THIỆU chỉ được cấp sau khi người này có đơn đầu tiên thanh toán thành công, xem
    // Order::booted() hook 'updated' (chặn lạm dụng tạo tài khoản ảo để lấy thưởng).
    private function grantRefereeCoupon(Customer $customer): void
    {
        $settings = app(GeneralSettings::class);
        if (! $settings->referral_enabled) {
            return;
        }

        Coupon::create([
            'code'            => 'REF-' . strtoupper(\Illuminate\Support\Str::random(8)),
            'name'            => 'Ưu đãi chào mừng bạn mới',
            'description'     => 'Ưu đãi dành cho khách được giới thiệu tham gia.',
            'type'            => 'percentage',
            'value'           => $settings->referral_reward_referee_percent,
            'apply_type'      => 'all_rooms',
            'usage_limit'     => 1,
            'used_count'      => 0,
            'start_at'        => now(),
            'end_at'          => now()->addDays($settings->referral_reward_expiry_days),
            'is_active'       => true,
            'customer_id'     => $customer->id,
        ]);
    }

    /**
     * Đăng xuất — xóa Sanctum token hiện tại.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công.']);
    }

    /**
     * Thông tin khách hàng đang đăng nhập.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->customerResource($request->user()));
    }

    /**
     * Đổi mật khẩu.
     * Body: { current_password, password, password_confirmation }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $customer = $request->user();

        if (! $customer->password || ! password_verify($request->current_password, $customer->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng.',
                'errors'  => ['current_password' => ['Mật khẩu hiện tại không đúng.']],
            ], 422);
        }

        if ($request->current_password === $request->password) {
            return response()->json([
                'message' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
                'errors'  => ['password' => ['Mật khẩu mới phải khác mật khẩu hiện tại.']],
            ], 422);
        }

        $customer->update([
            'password'            => $request->password,
            'password_updated_at' => now(),
        ]);

        return response()->json(['message' => 'Đổi mật khẩu thành công.']);
    }

    /**
     * Vô hiệu hóa tài khoản — do khách tự yêu cầu.
     * Xoá toàn bộ token, đặt status = inactive.
     */
    public function deactivate(Request $request): JsonResponse
    {
        $customer = $request->user();

        $customer->tokens()->delete();
        $customer->update(['status' => Customer::STATUS_INACTIVE]);

        return response()->json(['message' => 'Tài khoản đã được vô hiệu hóa.']);
    }

    /**
     * Xoá tài khoản — do khách tự yêu cầu.
     * Xoá toàn bộ token Sanctum rồi soft-delete customer.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $request->user();

        $customer->tokens()->delete();
        $customer->delete();

        return response()->json(['message' => 'Tài khoản đã được xoá thành công.']);
    }

    /**
     * Cập nhật thông tin khách hàng (yêu cầu token).
     * Body (multipart/form-data): fullname?, date_of_birth?, cccd_front?, cccd_back?,
     * companions[i][cccd_front]?, companions[i][cccd_back]?, companions[i][full_name]?
     * (companions = CCCD người đi cùng lưu vào hồ sơ, tái sử dụng cho các lần đặt sân
     * qua đêm sau này — xem customer_companions).
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'fullname'                => 'sometimes|string|max:255',
            'date_of_birth'           => 'sometimes|date_format:d-m-Y|before:today',
            'cccd_front'              => 'sometimes|file|mimes:jpg,jpeg,png,webp|max:5120',
            'cccd_back'               => 'sometimes|file|mimes:jpg,jpeg,png,webp|max:5120',
            'companions'              => 'sometimes|array',
            'companions.*.cccd_front' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'companions.*.cccd_back'  => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
            'companions.*.full_name'  => 'nullable|string|max:255',
            // Trình độ chơi tự khai — dùng cho tính năng "Tìm bạn chơi" (App\Models\OpenMatch).
            'skill_level'             => ['sometimes', 'nullable', 'string', 'in:' . implode(',', \App\Models\OpenMatch::SKILL_LEVELS)],
        ]);

        $customer = $request->user();
        $data     = [];

        if ($request->filled('fullname')) {
            $data['fullname'] = $request->fullname;
        }

        if ($request->filled('date_of_birth')) {
            $data['date_of_birth'] = Carbon::createFromFormat('d-m-Y', $request->date_of_birth)->toDateString();
        }

        if ($request->has('skill_level')) {
            $data['skill_level'] = $request->skill_level;
        }

        // Lưu path file cũ để xoá sau khi xác nhận QR hợp lệ
        $oldCccdFront = $customer->cccd_front;
        $oldCccdBack  = $customer->cccd_back;

        if ($request->hasFile('cccd_front')) {
            $data['cccd_front'] = $request->file('cccd_front')->store('cccd', 'public');
        }

        if ($request->hasFile('cccd_back')) {
            $data['cccd_back'] = $request->file('cccd_back')->store('cccd', 'public');
        }

        // Nếu có upload CCCD thì bắt buộc quét QR xác thực
        if (isset($data['cccd_front']) || isset($data['cccd_back'])) {
            $tempCustomer = new Customer([
                'cccd_front' => $data['cccd_front'] ?? $customer->cccd_front,
                'cccd_back'  => $data['cccd_back']  ?? $customer->cccd_back,
            ]);

            $cccdData = app(CccdScannerService::class)->scanCustomer($tempCustomer);

            if (! $cccdData) {
                // QR không đọc được — xoá file mới, giữ nguyên file cũ
                if (isset($data['cccd_front'])) {
                    Storage::disk('public')->delete($data['cccd_front']);
                }
                if (isset($data['cccd_back'])) {
                    Storage::disk('public')->delete($data['cccd_back']);
                }

                return response()->json([
                    'message' => 'Không đọc được QR trên ảnh CCCD. Vui lòng upload ảnh gốc rõ nét, không chụp lại màn hình.',
                ], 422);
            }

            // QR hợp lệ — xoá file cũ và lưu dữ liệu
            if (isset($data['cccd_front']) && $oldCccdFront) {
                Storage::disk('public')->delete($oldCccdFront);
            }
            if (isset($data['cccd_back']) && $oldCccdBack) {
                Storage::disk('public')->delete($oldCccdBack);
            }

            $data['cccd_data'] = $cccdData;
        }

        if (! empty($data)) {
            $customer->update($data);
        }

        // Thêm CCCD người đi cùng vào hồ sơ.
        if ($request->has('companions')) {
            $uploadedPaths = [];
            $scanner       = app(CccdScannerService::class);

            try {
                DB::transaction(function () use ($request, $customer, $scanner, &$uploadedPaths) {
                    foreach ($request->file('companions') as $index => $files) {
                        $frontPath = $files['cccd_front']->store('cccd', 'public');
                        $backPath  = $files['cccd_back']->store('cccd', 'public');
                        $uploadedPaths[] = $frontPath;
                        $uploadedPaths[] = $backPath;

                        $cccdData = $scanner->scanPaths($frontPath, $backPath);

                        if (! $cccdData) {
                            throw new \RuntimeException(
                                'Không đọc được QR trên CCCD người đi cùng thứ ' . ($index + 1) . '. Vui lòng upload ảnh gốc rõ nét, không chụp lại màn hình.'
                            );
                        }

                        CustomerCompanion::create([
                            'customer_id' => $customer->id,
                            'full_name'   => $request->input("companions.$index.full_name") ?: ($cccdData['full_name'] ?? null),
                            'cccd_front'  => $frontPath,
                            'cccd_back'   => $backPath,
                            'cccd_data'   => $cccdData,
                        ]);
                    }
                });
            } catch (\RuntimeException $e) {
                foreach ($uploadedPaths as $path) {
                    Storage::disk('public')->delete($path);
                }

                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json($this->customerResource(
            Customer::find($customer->id) ?? $customer
        ));
    }

    /**
     * Xoá 1 CCCD người đi cùng khỏi hồ sơ (yêu cầu token).
     * DELETE /api/auth/companions/{id}
     */
    public function deleteCompanion(Request $request, int $id): JsonResponse
    {
        $customer = $request->user();

        $companion = $customer->companions()->find($id);

        if (! $companion) {
            return response()->json(['message' => 'Không tìm thấy người đi cùng.'], 404);
        }

        Storage::disk('public')->delete(array_filter([$companion->cccd_front, $companion->cccd_back]));

        $companion->delete();

        return response()->json($this->customerResource(
            Customer::find($customer->id) ?? $customer
        ));
    }

    private function customerResource(Customer $customer): array
    {
        $customer->loadMissing(['membershipTier', 'companions']);

        $tier     = $customer->membershipTier;
        $spending = (float) $customer->total_spending;

        $nextTier = MembershipTier::where('is_active', true)
            ->where('min_spending', '>', $spending)
            ->orderBy('min_spending')
            ->first();

        $coupons = Coupon::where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                  ->orWhereHas('customers', fn ($q2) => $q2->whereKey($customer->id));
            })
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_at')->orWhere('start_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('end_at')->orWhere('end_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->orderByDesc('created_at')
            ->get(['id', 'code', 'name', 'type', 'value', 'max_discount', 'min_order_value', 'end_at']);

        return [
            'id'                => $customer->id,
            'fullname'          => $customer->fullname,
            'date_of_birth'     => $customer->date_of_birth?->toDateString(),
            'phone'             => $customer->phone,
            'status'            => $customer->status,
            'phone_verified_at' => $customer->phone_verified_at?->toIso8601String(),
            'cccd_front'        => $customer->cccd_front
                ? Storage::disk('public')->url($customer->cccd_front)
                : null,
            'cccd_back'         => $customer->cccd_back
                ? Storage::disk('public')->url($customer->cccd_back)
                : null,
            'cccd_data'         => $customer->cccd_data,
            'companions'        => $customer->companions->map(fn (CustomerCompanion $c) => [
                'id'          => $c->id,
                'full_name'   => $c->full_name,
                'cccd_front'  => Storage::disk('public')->url($c->cccd_front),
                'cccd_back'   => Storage::disk('public')->url($c->cccd_back),
                'cccd_data'   => $c->cccd_data,
            ]),
            'membership'        => [
                'tier'           => $tier ? [
                    'id'                  => $tier->id,
                    'name'                => $tier->name,
                    'slug'                => $tier->slug,
                    'welcome_coupon_value'=> (float) $tier->welcome_coupon_value,
                ] : null,
                'total_spending' => $spending,
                'next_tier'      => $nextTier ? [
                    'id'           => $nextTier->id,
                    'name'         => $nextTier->name,
                    'min_spending' => (float) $nextTier->min_spending,
                    'remaining'    => max(0, (float) $nextTier->min_spending - $spending),
                ] : null,
                'coupons'        => $coupons->map(fn (Coupon $c) => [
                    'code'            => $c->code,
                    'name'            => $c->name,
                    'type'            => $c->type,
                    'value'           => (float) $c->value,
                    'max_discount'    => $c->max_discount ? (float) $c->max_discount : null,
                    'min_order_value' => $c->min_order_value ? (float) $c->min_order_value : null,
                    'expires_at'      => $c->end_at?->toDateString(),
                ]),
            ],
        ];
    }

}
