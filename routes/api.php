<?php

use App\Http\Controllers\Api\Auth\ZaloOtpController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\GraphQLController;
use App\Http\Controllers\Api\MembershipController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\GuestBookingController;
use App\Http\Controllers\Api\GuestNotificationController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderServiceController;
use App\Http\Controllers\Api\AskUserController;
use App\Http\Controllers\Api\PopupController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\ProvinceController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\WardController;
use App\Http\Controllers\Api\DailyRoomController;
use App\Http\Controllers\Api\DailyRoomHoldController;
use App\Http\Controllers\Api\DirectionsController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\WaitlistController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\BookingFundController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\CameraMediaProxyController;
use App\Http\Controllers\Api\OpenMatchController;
use Illuminate\Support\Facades\Route;
use Modules\AppPage\App\Http\Controllers\AppPageController;

/*
|--------------------------------------------------------------------------
| Zalo OTP Auth
| POST /api/auth/send-otp   → Gửi OTP về Zalo của khách
| POST /api/auth/verify-otp → Xác nhận OTP, trả Sanctum token
| POST /api/auth/logout     → Đăng xuất (xoá token hiện tại)
| GET  /api/auth/me         → Thông tin user đang đăng nhập
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Room Types
| GET /api/room-types → Danh sách danh mục sân (dùng cho select/dropdown)
|--------------------------------------------------------------------------
*/
// ĐĂNG KÝ ĐỐI TÁC = MUA GÓI (xem PartnerPurchaseController): xem gói, đăng ký/mua gói, theo dõi đơn bằng mã đơn.
Route::prefix('public')->name('api.partner-purchase.')->group(function () {
    Route::get('partner-plans', [\App\Http\Controllers\Api\Public\PartnerPurchaseController::class, 'plans'])->middleware('throttle:public-api')->name('plans');
    Route::get('partner-address', [\App\Http\Controllers\Api\Public\PartnerPurchaseController::class, 'address'])->middleware('throttle:public-api')->name('address');
    Route::post('partner-purchase', [\App\Http\Controllers\Api\Public\PartnerPurchaseController::class, 'purchase'])->middleware('throttle:partner-onboarding-register')->name('purchase');
    Route::get('partner-purchase/{token}', [\App\Http\Controllers\Api\Public\PartnerPurchaseController::class, 'show'])->middleware('throttle:partner-onboarding')->name('show');
});

Route::get('room-types',      [RoomTypeController::class, 'index'])->name('api.room-types.index');
// Lựa chọn hợp lệ cho hồ sơ người chơi (môn, trình độ, mục tiêu, tần suất) — app dùng để dựng form POST /auth/me.
Route::get('v1/player-profile-options', \App\Http\Controllers\Api\PlayerProfileOptionsController::class)
    ->name('api.v1.player-profile-options')->middleware('throttle:public-api');
Route::get('v1/room-types/{id}', [RoomTypeController::class, 'show'])->name('api.v1.room-types.show')->whereNumber('id');


/*
|--------------------------------------------------------------------------
| Branches (Chi nhánh)
| GET /api/v1/branches              → Danh sách chi nhánh (?province_id= để lọc theo tỉnh)
| GET /api/v1/branches/{id}         → Chi tiết 1 chi nhánh
|--------------------------------------------------------------------------
*/
Route::get('v1/branches',      [BranchController::class, 'index'])->name('api.v1.branches.index');
Route::get('v1/branches/{id}', [BranchController::class, 'show'])->name('api.v1.branches.show')->whereNumber('id');

/*
|--------------------------------------------------------------------------
| V1 — Home
| GET /api/v1/home                          → Full sections (banner + room_list)
| GET /api/v1/home?tab={id}                 → Banner + room_list lọc theo tab
| GET /api/v1/home?province={id}            → Sections lọc theo tỉnh/thành phố
|
| GET /api/v1/provinces                     → Danh sách tỉnh grouped (Phổ biến + A-Z)
| GET /api/v1/provinces/detect?lat=&lng=    → Tỉnh gần nhất theo GPS
| GET /api/v1/provinces/{id}/branches       → Chi nhánh theo tỉnh (có total_room)
| GET /api/v1/provinces/{slug}              → Chi tiết tỉnh + chi nhánh (slug)
|
| GET /api/v1/ask-user/{id}                 → Nội dung thông báo khi vào app
| GET /api/v1/popups                        → Danh sách ảnh popup (ảnh + url điều hướng), dùng
|                                                cho cả app và website
|
| GET /api/v1/branches/{slug}/time-slots?days=15 → Lịch đặt sân đầy đủ 1 chi nhánh (mọi sân
|                                                    theo khung giờ x ngày, kèm giá/khuyến mãi/
|                                                    trạng thái đã đặt) — days tối đa 31.
| GET /api/v1/branches/{branch}/daily-rooms      → Danh sách sân THEO NGÀY (styles=2) thuộc 1
|                                                    chi nhánh — {branch} nhận id, slug, hoặc tên.
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('home', HomeController::class)->name('home')->middleware('throttle:public-api');

    Route::get('branches/{slug}/time-slots', [BranchController::class, 'timeSlots'])
        ->name('branches.time-slots')
        ->middleware('throttle:public-api');

    Route::get('branches/{branch}/daily-rooms', [BranchController::class, 'dailyRooms'])
        ->name('branches.daily-rooms')
        ->middleware('throttle:public-api');

    Route::prefix('provinces')->name('provinces.')->group(function () {
        Route::get('/',       [ProvinceController::class, 'index'])->name('index');
        Route::get('detect',  [ProvinceController::class, 'detect'])->name('detect');
        Route::get('select',  [ProvinceController::class, 'getSelected'])->name('select.get');
        Route::post('select', [ProvinceController::class, 'select'])->name('select');
        Route::get('{id}',    [ProvinceController::class, 'show'])->name('show')->whereNumber('id');
    });

    Route::get('guest/provinces/{id}', [ProvinceController::class, 'showInfo'])->name('guest.provinces.show')->whereNumber('id');

    Route::get('wards/{code}', [WardController::class, 'showByCode'])->name('wards.show')->whereNumber('code');

    Route::get('ask-user/{id}', [AskUserController::class, 'show'])->name('ask-user.show')->whereNumber('id');

    // GET /api/v1/popups → danh sách ảnh popup đang cấu hình (ảnh + url điều hướng), đúng thứ tự
    // sắp xếp trên admin — dùng cho cả app và website. Quản lý ở Admin > Quản lý API > Popup.
    Route::get('popups', [PopupController::class, 'index'])->name('popups.index')->middleware('throttle:public-api');

    Route::get('config',     ConfigController::class)->name('config')->middleware('throttle:config');
    Route::get('config/map', [ConfigController::class, 'map'])->name('config.map')->middleware('throttle:config');

    /*
    |--------------------------------------------------------------------------
    | Search
    | GET /api/v1/search/suggestions → Gợi ý điểm đến (màn nhập liệu)
    | GET /api/v1/search             → Kết quả tìm kiếm + marker bản đồ
    | GET /api/v1/search/locations   → Autocomplete địa điểm khi gõ
    |--------------------------------------------------------------------------
    */
    Route::prefix('search')->name('search.')->middleware('throttle:public-api')->group(function () {
        Route::get('suggestions', [SearchController::class, 'suggestions'])->name('suggestions');
        Route::get('locations',   [SearchController::class, 'locations'])->name('locations');
        Route::get('branches',    [SearchController::class, 'branches'])->name('branches');
        Route::get('/',           [SearchController::class, 'index'])->name('index');
    });

});

/*
|--------------------------------------------------------------------------
| GraphQL (pilot — chỉ phục vụ trang kết quả tìm kiếm)
| POST /api/graphql — xem App\Http\Controllers\Api\GraphQLController
|--------------------------------------------------------------------------
*/
Route::post('graphql', [GraphQLController::class, 'handle'])->name('graphql')->middleware('throttle:public-api');

// Dẫn đường bản đồ /ban-do (map-page.blade.php) — proxy qua backend để giữ API key
// OpenRouteService riêng (xem DirectionsController). Public, không cần đăng nhập, cùng mức throttle
// với các endpoint công khai khác.
Route::post('directions/route', [DirectionsController::class, 'route'])->name('directions.route')->middleware('throttle:public-api');

/*
|--------------------------------------------------------------------------
| V2 — Wards (phường / xã)
| GET /api/v2/ward                       → Danh sách tất cả phường/xã
| GET /api/v2/ward?province_code=92      → Lọc theo mã tỉnh
| GET /api/v2/ward?search=bình           → Tìm theo tên
| GET /api/v2/ward?division_type=phường  → Lọc loại đơn vị
| GET /api/v2/ward/{code}               → Chi tiết 1 phường/xã
|--------------------------------------------------------------------------
*/
Route::prefix('v2')->name('api.v2.')->group(function () {
    Route::get('ward',        [WardController::class, 'index'])->name('ward.index');
    Route::get('ward/{code}', [WardController::class, 'show'])->name('ward.show')->whereNumber('code');
});

/*
|--------------------------------------------------------------------------
| App Pages
| GET /api/pages/{slug} → Nội dung trang theo slug (home, payment, ...)
|--------------------------------------------------------------------------
*/
Route::get('pages/{slug}', [AppPageController::class, 'show'])->name('api.pages.show');

/*
|--------------------------------------------------------------------------
| Rooms
| GET /api/rooms/{slug} → Chi tiết sân (amenities, services, specials,
|                          prices, promotions, coupons)
|--------------------------------------------------------------------------
*/
Route::get('rooms/{id}', [RoomController::class, 'show'])->name('api.rooms.show');
Route::get('slots', [RoomController::class, 'slots'])->name('api.slots');
Route::get('rooms/{id}/guest-surcharge-preview', [RoomController::class, 'guestSurchargePreview'])->name('api.rooms.guest-surcharge-preview');

/*
|--------------------------------------------------------------------------
| Daily Rooms (sân theo ngày — styles=2)
| GET /api/rooms/{id}/dates?month=YYYY-MM
|     → Lịch tháng: giá, availability, promotion từng ngày (dùng cho calendar)
| GET /api/rooms/{id}/price-preview?checkin=YYYY-MM-DD&checkout=YYYY-MM-DD
|     → Tính giá tạm tính: per-night breakdown, availability, tổng, deposit
|--------------------------------------------------------------------------
*/
Route::get('rooms/{id}/dates',         [DailyRoomController::class, 'dates'])->name('api.rooms.daily.dates');
Route::get('rooms/{id}/price-preview', [DailyRoomController::class, 'pricePreview'])->name('api.rooms.daily.price-preview');
Route::post('rooms/{id}/hold',         [DailyRoomHoldController::class, 'hold'])->name('api.rooms.daily.hold');
Route::delete('rooms/{id}/hold',       [DailyRoomHoldController::class, 'release'])->name('api.rooms.daily.hold.release');

/*
|--------------------------------------------------------------------------
| Time-slot Hold (KHÁCH VÃNG LAI) — ĐÃ GỠ 2 route POST/DELETE public bên dưới.
|
| Trước đây cho phép bất kỳ ai (không cần đăng nhập) gọi thẳng 2 route này để "giữ chỗ" 1 ô
| khung giờ, phát real-time cho admin/khách khác thấy ngay. Rủi ro bảo mật thật sự: throttle
| 30 request/phút/IP KHÔNG đủ chặn kẻ tấn công cố ý spam giữ hết mọi ô khung giờ còn trống của
| 1 sân (đổi IP/dùng nhiều IP), khiến khách thật không đặt được sân dù còn trống — đây là
| lỗ hổng DoS/phá đơn, không phải lỗi hiệu năng thông thường. ĐÃ BỎ theo yêu cầu, chỉ còn giữ
| lại chiều NGƯỢC LẠI (admin giữ chỗ → khách thấy ngay, xem TimeslotHoldService +
| admin.rooms.timeslot-hold ở routes/web.php) — chiều đó an toàn vì chỉ admin đã đăng nhập mới
| gọi được.
|
| TimeSlotHoldController::hold()/release() vẫn còn trong code (không xoá file) vì
| App\Http\Controllers\Api\Admin\TimeSlotHoldController (route admin đã xác thực, KHÔNG bị gỡ)
| đang gọi thẳng TimeSlotHoldController::getActiveHolds() (static, cùng class) để đọc chung 1
| kho Cache — chỉ 2 route public hold()/release() bên dưới bị gỡ, phần đọc vẫn hoạt động bình
| thường.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Room Ratings — Đánh giá sao
| GET    /api/rooms/{id}/ratings → Danh sách đánh giá + summary (public)
| POST   /api/rooms/{id}/ratings → Tạo / cập nhật đánh giá (auth)
| DELETE /api/rooms/{id}/ratings → Xoá đánh giá của mình (auth)
|--------------------------------------------------------------------------
*/
Route::get('rooms/{id}/ratings', [RatingController::class, 'index'])->name('api.rooms.ratings.index');

Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
    Route::post('rooms/{id}/ratings',   [RatingController::class, 'store'])->name('api.rooms.ratings.store');
    Route::delete('rooms/{id}/ratings', [RatingController::class, 'destroy'])->name('api.rooms.ratings.destroy');
});

/*
|--------------------------------------------------------------------------
| Dev / Test bypass — chỉ hoạt động khi OTP_BYPASS_ENABLED=true
| GET /api/auth/dev-otp/{phone} → trả mã OTP hiện tại từ cache
|--------------------------------------------------------------------------
*/
if (config('app.otp_bypass_enabled', false)) {
    Route::get('auth/dev-otp/{phone}', function (string $phone) {
        $service = app(\App\Services\ZaloOtpService::class);
        $normalized = $service->normalizePhone($phone);
        $otp = \Illuminate\Support\Facades\Cache::get('zalo_otp:' . $normalized);
        if (! $otp) {
            return response()->json(['message' => 'Không có OTP nào đang chờ cho số này.'], 404);
        }
        return response()->json(['phone' => $normalized, 'otp' => $otp]);
    })->name('api.auth.dev-otp');
}

Route::prefix('auth')->name('api.auth.')->group(function () {
    // Đăng nhập
    Route::post('send-otp',   [ZaloOtpController::class, 'sendOtp'])->name('send-otp')->middleware('throttle:otp-send');
    Route::post('verify-otp', [ZaloOtpController::class, 'verifyOtp'])->name('verify-otp')->middleware('throttle:otp-verify');
    Route::post('login',      [ZaloOtpController::class, 'login'])->name('login')->middleware('throttle:6,1');
    // Đăng nhập bằng Email — phương thức THÊM cho tài khoản đã tồn tại, xem
    // ZaloOtpController::emailLogin().
    Route::post('email-login', [ZaloOtpController::class, 'emailLogin'])->name('email-login')->middleware('throttle:6,1');

    // Đổi mã trao đổi 1 lần từ luồng đăng nhập Google (routes/web.php: auth/google/callback) lấy
    // token thật — public, không cần đăng nhập trước, bảo vệ bằng mã ngẫu nhiên 40 ký tự + TTL 2
    // phút, xem ZaloOtpController::googleCallback()/googleExchange().
    Route::post('google/exchange', [ZaloOtpController::class, 'googleExchange'])->name('google.exchange')->middleware('throttle:10,1');

    // Đăng ký
    Route::post('register', [ZaloOtpController::class, 'register'])->name('register')->middleware('throttle:register');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout',     [ZaloOtpController::class, 'logout'])->name('logout');
        Route::get('me',          [ZaloOtpController::class, 'me'])->name('me');

        // Các route sau chặn nếu tài khoản bị vô hiệu hóa
        Route::middleware('customer.active')->group(function () {
            Route::post('me',              [ZaloOtpController::class, 'update'])->name('me.update');
            Route::post('change-password', [ZaloOtpController::class, 'changePassword'])->name('change-password');
            Route::post('deactivate',      [ZaloOtpController::class, 'deactivate'])->name('deactivate');
            Route::delete('account',       [ZaloOtpController::class, 'deleteAccount'])->name('delete-account');
            Route::delete('companions/{id}', [ZaloOtpController::class, 'deleteCompanion'])->name('companions.destroy')->whereNumber('id');

            // Gắn/xác thực email cho tài khoản đã đăng nhập bằng phone — xem
            // ZaloOtpController::sendEmailVerificationCode()/verifyEmail().
            Route::post('email/send-code', [ZaloOtpController::class, 'sendEmailVerificationCode'])->name('email.send-code')->middleware('throttle:6,1');
            Route::post('email/verify',    [ZaloOtpController::class, 'verifyEmail'])->name('email.verify')->middleware('throttle:6,1');

            // Mã giới thiệu bạn bè của khách + link chia sẻ + số bạn đã giới thiệu — xem
            // ZaloOtpController::referral().
            Route::get('referral', [ZaloOtpController::class, 'referral'])->name('referral');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Device Token (FCM)
    | POST   /api/auth/device-token → Lưu token thiết bị (sau đăng nhập / token mới)
    | DELETE /api/auth/device-token → Xóa token (đăng xuất / tắt thông báo)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
        Route::post('device-token',   [DeviceTokenController::class, 'store'])->name('device-token.store');
        Route::delete('device-token', [DeviceTokenController::class, 'destroy'])->name('device-token.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Orders / Booking
| POST /api/orders → Tạo đơn đặt sân (slot hoặc monthly)
|                    - Nếu có token Sanctum: lấy buyer từ customer
|                    - Nếu không có token:  bắt buộc buyer_name + buyer_phone
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders',             [OrderController::class,        'index'])->name('api.orders.index');
    Route::get('orders/{order_code}',[OrderController::class,        'show'])->name('api.orders.show');

    // Các route sau chặn nếu tài khoản bị vô hiệu hóa
    Route::middleware('customer.active')->group(function () {
        Route::post('orders',                                   [BookingController::class,     'store'])->name('api.orders.store');
        Route::post('orders/{order_code}',                   [OrderController::class,       'update'])->name('api.orders.update');
        Route::get('orders/{order_code}/payment-qr',         [OrderController::class,       'paymentQr'])->name('api.orders.payment-qr');
        // Khách tự phục vụ trên đơn của mình — xem App\Http\Controllers\Api\OrderSelfServiceController.
        Route::get('orders/{order_code}/cancel-preview',     [\App\Http\Controllers\Api\OrderSelfServiceController::class, 'cancelPreview'])->name('api.orders.cancel-preview');
        Route::post('orders/{order_code}/cancel',            [\App\Http\Controllers\Api\OrderSelfServiceController::class, 'cancel'])->name('api.orders.cancel');
        Route::post('orders/{order_code}/reschedule',        [\App\Http\Controllers\Api\OrderSelfServiceController::class, 'reschedule'])->name('api.orders.reschedule');
        Route::get('orders/{order_code}/check-in-code',      [\App\Http\Controllers\Api\OrderSelfServiceController::class, 'checkInCode'])->name('api.orders.check-in-code');
        Route::post('orders/{order_code}/retry-payment',    [OrderController::class, 'retryPayment'])->name('api.orders.retry-payment');
        Route::post('orders/{order_code}/remaining-payment',[OrderController::class, 'remainingPayment'])->name('api.orders.remaining-payment');
        // Đơn payment_method='bank_transfer' — khớp booking-detail.blade.php (web).
        Route::post('orders/{order_code}/payment-proof',    [OrderController::class, 'uploadPaymentProof'])->name('api.orders.payment-proof');
        Route::post('orders/{order}/services',              [OrderServiceController::class,'store'])->name('api.orders.services.store');
        Route::post('orders/{order_code}/extra',             [OrderController::class,       'addExtra'])->name('api.orders.extra');
        Route::post('orders/{order_code}/extra-charge-qr',   [OrderController::class,       'extraChargeQr'])->name('api.orders.extra-charge-qr');
        Route::get('coupons/mine',             [CouponController::class,     'mine'])->name('api.coupons.mine');
        Route::post('coupons/validate',        [CouponController::class,     'check'])->name('api.coupons.validate');
    });
});

/*
|--------------------------------------------------------------------------
| Guest Booking (không cần đăng nhập)
| POST   /api/guest/orders                                        → Đặt sân theo giờ / ngày (slot / daily)
|                                                                      payment_type=full   → 100% (mặc định)
|                                                                      payment_type=deposit → 50% (chỉ daily)
| POST   /api/guest/orders/{order_code}                           → Cập nhật đơn (xác thực bằng buyer_phone)
| GET    /api/guest/orders?phone=xxx                              → Tra cứu danh sách đơn theo SĐT
| GET    /api/guest/orders/{order_code}?phone=xx                  → Xem chi tiết đơn theo SĐT
| POST   /api/guest/orders/{order_code}/remaining-payment         → Thanh toán phần còn lại (đặt cọc)
| GET    /api/guest/orders/{order_code}/payment-status?phone=xx  → App polling trạng thái thanh toán
|--------------------------------------------------------------------------
*/
// Xác thực đơn khách vãng lai CHỈ dựa vào buyer_phone (KHÔNG xác minh OTP, ai gõ SĐT gì cũng qua)
// + order_code (chuỗi 6 số suy từ microtime() lúc tạo đơn, xem Order::booted() — KHÔNG phải chuỗi
// ngẫu nhiên bảo mật, không gian chỉ ~1 triệu giá trị). 2 yếu tố đều YẾU, nếu không giới hạn tần
// suất thì dò được: kẻ tấn công đoán order_code (thu hẹp phạm vi nếu biết khoảng thời gian khách
// đặt) để xem/tải ảnh CCCD mặt trước-sau + dữ liệu cá nhân của người khác qua GET
// orders/{order_code}, hoặc sửa được đơn người khác qua POST orders/{order_code} — throttle CHẶT
// hơn mức mặc định của toàn API (60/phút) là bắt buộc.
Route::prefix('guest')->name('api.guest.')->middleware('throttle:20,1')->group(function () {
    Route::post('orders',                                        [GuestBookingController::class, 'store'])->name('orders.store');
    Route::post('orders/{order_code}',                           [GuestBookingController::class, 'update'])->name('orders.update');
    Route::get('orders',                                         [GuestBookingController::class, 'lookup'])->name('orders.lookup');
    Route::get('orders/{order_code}',                            [GuestBookingController::class, 'show'])->name('orders.show');
    Route::post('orders/{order_code}/remaining-payment',         [GuestBookingController::class, 'remainingPayment'])->name('orders.remaining-payment');
    // Đơn payment_method='bank_transfer' — khớp booking-detail.blade.php (web).
    Route::post('orders/{order_code}/payment-proof',             [GuestBookingController::class, 'uploadPaymentProof'])->name('orders.payment-proof');
    Route::post('orders/{order_code}/extra',                     [GuestBookingController::class, 'addExtra'])->name('orders.extra');
    Route::post('orders/{order_code}/extra-charge-qr',           [GuestBookingController::class, 'extraChargeQr'])->name('orders.extra-charge-qr');
    Route::get('orders/{order_code}/payment-status',             [GuestBookingController::class, 'paymentStatus'])->name('orders.payment-status');

    /*
    |--------------------------------------------------------------------------
    | Guest Notifications (xác thực bằng device_token)
    | GET  /api/guest/notifications?device_token=xxx          → Danh sách thông báo
    | POST /api/guest/notifications/read-all                  → Đánh dấu tất cả đã xem
    | POST /api/guest/notifications/{id}/read                 → Đánh dấu 1 thông báo đã xem
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/',           [GuestNotificationController::class, 'index'])->name('index');
        Route::post('read-all',   [GuestNotificationController::class, 'markAllRead'])->name('read-all');
        Route::post('{id}/read',  [GuestNotificationController::class, 'markRead'])->name('read')->whereNumber('id');
    });
});

/*
|--------------------------------------------------------------------------
| Notifications
| GET  /api/notifications           → Danh sách thông báo đã nhận (có is_read)
| POST /api/notifications/{id}/read → Đánh dấu 1 thông báo đã xem
| POST /api/notifications/read-all  → Đánh dấu tất cả đã xem
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('notifications')->name('api.notifications.')->group(function () {
    Route::get('/',          [NotificationController::class, 'index'])->name('index');
    Route::post('read-all',  [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::post('{id}/read', [NotificationController::class, 'markRead'])->name('read')->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Trình độ người chơi (tự khai theo môn; nhân viên sân có thể xác nhận)
| GET    /api/player-skills                          → trình độ của tôi + thang trình độ + danh sách môn
| PUT    /api/player-skills/{sport}                  → tự khai trình độ một môn (sport = general hoặc sport_type của loại sân)
| DELETE /api/player-skills/{sport}                  → bỏ trình độ tự khai
| POST   /api/player-skills/{sport}/request-assessment → xin nhân viên sân đánh giá / xác nhận
| GET    /api/player-skills/history                  → lịch sử thay đổi
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->prefix('player-skills')->name('api.player-skills.')->group(function () {
    Route::get('/',                         [\App\Http\Controllers\Api\PlayerSkillController::class, 'index'])->name('index');
    Route::get('history',                   [\App\Http\Controllers\Api\PlayerSkillController::class, 'history'])->name('history');
    Route::put('{sport}',                   [\App\Http\Controllers\Api\PlayerSkillController::class, 'update'])->name('update');
    Route::delete('{sport}',                [\App\Http\Controllers\Api\PlayerSkillController::class, 'destroy'])->name('destroy');
    Route::post('{sport}/request-assessment', [\App\Http\Controllers\Api\PlayerSkillController::class, 'requestAssessment'])->name('request-assessment');
});

/*
|--------------------------------------------------------------------------
| Chat — Tin nhắn giữa khách và admin
| GET    /api/chat                        → Lấy / tạo conversation + 20 tin nhắn mới nhất
| GET    /api/chat/messages               → Load thêm tin cũ (?before_id=&limit=)
| POST   /api/chat/messages               → Khách gửi tin nhắn
| POST   /api/chat/read                   → Đánh dấu tất cả tin từ admin là đã đọc
|
| GET    /api/admin/chat                  → Danh sách conversation (admin)
| GET    /api/admin/chat/{id}             → Chi tiết + tin nhắn (admin)
| POST   /api/admin/chat/{id}/messages    → Admin gửi tin nhắn
| POST   /api/admin/chat/{id}/read        → Admin đánh dấu đã đọc
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->prefix('chat')->name('api.chat.')->group(function () {
    Route::get('/',                        [ChatController::class, 'show'])->name('show');
    Route::get('/order/{order_code}',      [ChatController::class, 'showByOrder'])->name('order');
    Route::get('/messages',                [ChatController::class, 'messages'])->name('messages');
    Route::post('/messages',               [ChatController::class, 'send'])->name('send');
    Route::post('/read',                   [ChatController::class, 'read'])->name('read');
});

/*
|--------------------------------------------------------------------------
| Membership — Hạng thành viên
| GET /api/membership/tiers       → Danh sách hạng (public)
| GET /api/membership             → Hạng + coupon cá nhân của customer (auth)
|--------------------------------------------------------------------------
*/
Route::get('membership/tiers', [MembershipController::class, 'tiers'])->name('api.membership.tiers');

Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
    Route::get('membership', [MembershipController::class, 'show'])->name('api.membership.show');
});

/*
|--------------------------------------------------------------------------
| Wishlist
| GET  /api/wishlist                    → Danh sách sân đã lưu
| POST /api/wishlist/{product}/toggle   → Thêm / bỏ khỏi wishlist
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->prefix('wishlist')->name('api.wishlist.')->group(function () {
    Route::get('/',            [WishlistController::class, 'index'])->name('index');
    Route::post('{id}/toggle', [WishlistController::class, 'toggle'])->name('toggle');
});

/*
|--------------------------------------------------------------------------
| Waitlist — "Báo khi có chỗ trống"
| GET    /api/waitlist       → Danh sách đăng ký chờ (chưa được báo)
| POST   /api/waitlist       → Đăng ký chờ 1 khung giờ/ngày đã hết chỗ
| DELETE /api/waitlist/{id}  → Huỷ đăng ký chờ
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->prefix('waitlist')->name('api.waitlist.')->group(function () {
    Route::get('/',      [WaitlistController::class, 'index'])->name('index');
    Route::post('/',     [WaitlistController::class, 'store'])->name('store');
    Route::delete('{id}', [WaitlistController::class, 'destroy'])->name('destroy')->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Booking Fund — Quỹ nhóm góp tiền đặt sân (App\Models\BookingFund)
| GET    /api/booking-funds                                 → Quỹ đang làm trưởng nhóm + đã tham gia
| POST   /api/booking-funds                                 → Tạo quỹ mới (người tạo = trưởng nhóm)
| GET    /api/booking-funds/{code}                           → Chi tiết quỹ (nội dung tuỳ vai trò)
| POST   /api/booking-funds/{code}/join                      → Xin tham gia
| POST   /api/booking-funds/{code}/members/{memberId}/decide → (Trưởng nhóm) duyệt/từ chối thành viên
| POST   /api/booking-funds/{code}/contributions              → (Thành viên đã duyệt) gửi biên lai góp quỹ
| POST   /api/booking-funds/{code}/contributions/{id}/decide  → (Trưởng nhóm) xác nhận/từ chối khoản góp
| POST   /api/booking-funds/{code}/close                      → (Trưởng nhóm) đóng quỹ
| GET    /api/booking-funds/{code}/messages                   → Lịch sử chat nhóm
| POST   /api/booking-funds/{code}/messages                   → Gửi tin nhắn (broadcast Socket.IO
|                                                                 kênh "fundchat:{id}", cùng kênh
|                                                                 web đang dùng)
| Tiền KHÔNG đi qua hệ thống — quỹ chỉ theo dõi/minh bạch hoá việc góp quỹ, không tạo Order/PayOS.
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Đội nhóm (App\Models\Team) — khách tạo nhóm; người khác vào nhóm bằng MÃ NHÓM (xin tham gia, trưởng nhóm duyệt)
| hoặc được trưởng nhóm MỜI theo SỐ ĐIỆN THOẠI (người được mời đồng ý). Quỹ nhóm có thể gắn vào nhóm (team_id).
| GET    /api/teams                                   → Tab "Đội của bạn": nhóm mình làm trưởng nhóm + đã tham gia
| GET    /api/teams/requests                          → Tab "Tham gia": yêu cầu xin vào nhóm mình đã gửi (chờ duyệt)
| GET    /api/teams/invitations                       → Tab "Lời mời": lời mời vào nhóm gửi cho mình
| POST   /api/teams                                   → Tạo nhóm {name, description?}
| GET    /api/teams/{code}                            → Chi tiết (nội dung tuỳ vai trò; người ngoài xem trước theo mã)
| PUT    /api/teams/{code}                            → (Trưởng nhóm) đổi tên/mô tả
| POST   /api/teams/{code}/join                       → Xin vào nhóm bằng mã (đang được mời thì vào luôn)
| DELETE /api/teams/{code}/join                       → Rút lại yêu cầu đã gửi
| POST   /api/teams/{code}/respond                    → Trả lời lời mời {decision: accept|decline}
| POST   /api/teams/{code}/invite                     → (Trưởng nhóm) mời theo SĐT {phone}
| POST   /api/teams/{code}/members/{id}/decide        → (Trưởng nhóm) duyệt/từ chối {decision: approved|rejected}
| DELETE /api/teams/{code}/members/{id}               → (Trưởng nhóm) xoá thành viên / thu hồi lời mời
| POST   /api/teams/{code}/leave                      → Thành viên tự rời nhóm
| POST   /api/teams/{code}/disband                    → (Trưởng nhóm) giải tán nhóm
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->prefix('teams')->name('api.teams.')->group(function () {
    // Route tĩnh (requests/invitations) khai báo TRƯỚC {code} để không bị {code} nuốt.
    Route::get('/',            [TeamController::class, 'index'])->name('index');
    Route::get('requests',     [TeamController::class, 'requests'])->name('requests');
    Route::get('invitations',  [TeamController::class, 'invitations'])->name('invitations');
    Route::post('/',           [TeamController::class, 'store'])->name('store')->middleware('throttle:20,1');

    Route::prefix('{code}')->where(['code' => '[A-Za-z0-9]{8}'])->group(function () {
        Route::get('/',                         [TeamController::class, 'show'])->name('show');
        Route::put('/',                         [TeamController::class, 'update'])->name('update');
        // Dò mã nhóm / SĐT bị giới hạn tốc độ để không thể dò hàng loạt.
        Route::post('join',                     [TeamController::class, 'join'])->name('join')->middleware('throttle:30,1');
        Route::delete('join',                   [TeamController::class, 'cancelRequest'])->name('join.cancel');
        Route::post('respond',                  [TeamController::class, 'respond'])->name('respond');
        Route::post('invite',                   [TeamController::class, 'invite'])->name('invite')->middleware('throttle:30,1');
        Route::post('members/{memberId}/decide', [TeamController::class, 'decideMember'])->name('members.decide')->whereNumber('memberId');
        Route::delete('members/{memberId}',     [TeamController::class, 'removeMember'])->name('members.remove')->whereNumber('memberId');
        Route::post('leave',                    [TeamController::class, 'leave'])->name('leave');
        Route::post('disband',                  [TeamController::class, 'disband'])->name('disband');
        // Chat nhóm + chia sẻ lời mời (link/QR deep link).
        Route::get('messages',                  [\App\Http\Controllers\Api\TeamChatController::class, 'messages'])->name('messages.index');
        Route::post('messages',                 [\App\Http\Controllers\Api\TeamChatController::class, 'send'])->name('messages.send')->middleware('throttle:60,1');
        Route::get('share',                     [\App\Http\Controllers\Api\TeamChatController::class, 'share'])->name('share');
    });
});
// Xem trước nhóm từ link mời (chưa đăng nhập): tên, trưởng nhóm, số thành viên — không lộ SĐT.
Route::get('public/teams/{code}', [\App\Http\Controllers\Api\TeamChatController::class, 'preview'])->where('code', '[A-Za-z0-9]{8}')->middleware('throttle:30,1')->name('api.public.teams.preview');

Route::middleware(['auth:sanctum', 'customer.active'])->prefix('booking-funds')->name('api.booking-funds.')->group(function () {
    Route::get('/',                              [BookingFundController::class, 'index'])->name('index');
    Route::post('/',                             [BookingFundController::class, 'store'])->name('store');
    Route::get('{code}',                         [BookingFundController::class, 'show'])->name('show');
    Route::post('{code}/join',                   [BookingFundController::class, 'join'])->name('join');
    Route::post('{code}/members/{memberId}/decide', [BookingFundController::class, 'decideMember'])->name('members.decide')->whereNumber('memberId');
    Route::post('{code}/contributions',          [BookingFundController::class, 'storeContribution'])->name('contributions.store');
    Route::post('{code}/contributions/{id}/decide', [BookingFundController::class, 'decideContribution'])->name('contributions.decide')->whereNumber('id');
    Route::post('{code}/close',                  [BookingFundController::class, 'close'])->name('close');
    Route::get('{code}/messages',                [BookingFundController::class, 'messages'])->name('messages.index');
    Route::post('{code}/messages',               [BookingFundController::class, 'sendMessage'])->name('messages.store');
});

/*
|--------------------------------------------------------------------------
| Open Match — "Tìm bạn chơi" (App\Models\OpenMatch)
| GET    /api/open-matches            → Duyệt kèo đang mở (?category_id=&?product_id=&?date=
|                                        &?skill_level=&?sport_type=), loại trừ kèo của chính mình
| POST   /api/open-matches            → Mở kèo từ 1 đơn đã đặt (order_id)
| GET    /api/open-matches/{id}       → Chi tiết kèo + danh sách người đã ghép
| POST   /api/open-matches/{id}/join  → Xin ghép vào kèo (tự động, không cần host duyệt)
| POST   /api/open-matches/{id}/leave → Rời kèo đã ghép
| DELETE /api/open-matches/{id}       → (Host) huỷ cả kèo
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->prefix('open-matches')->name('api.open-matches.')->group(function () {
    Route::get('/',            [OpenMatchController::class, 'index'])->name('index');
    Route::post('/',           [OpenMatchController::class, 'store'])->name('store');
    Route::get('{id}',         [OpenMatchController::class, 'show'])->name('show')->whereNumber('id');
    Route::post('{id}/join',   [OpenMatchController::class, 'join'])->name('join')->whereNumber('id');
    Route::post('{id}/leave',  [OpenMatchController::class, 'leave'])->name('leave')->whereNumber('id');
    Route::delete('{id}',      [OpenMatchController::class, 'destroy'])->name('destroy')->whereNumber('id');
});



/*
|--------------------------------------------------------------------------
| Camera media proxy — PUBLIC (không qua auth:sanctum vì player HLS/video tag không tự gắn được
| Bearer token) — bảo mật bằng CameraMediaToken (HMAC ký sẵn, ngắn hạn, xem CameraRecordingService).
| GET /api/camera-media/{token}/{filename}
|--------------------------------------------------------------------------
*/
Route::get('camera-media/{token}/{filename}', CameraMediaProxyController::class)->name('api.camera-media');

/*
|--------------------------------------------------------------------------
| Gói nhiều buổi (thẻ trả trước) cho khách
| GET  /api/package-plans                  → gói đang bán (?room_id= ?category_id= ?partner_id=)
| POST /api/package-plans/{id}/purchase    → tạo đơn mua gói (auth); thanh toán qua /api/orders/{code}/payment-qr | payment-proof
| GET  /api/my-packages(/{id})             → gói tôi đã mua + số buổi còn lại (auth)
|--------------------------------------------------------------------------
*/
Route::get('package-plans', [\App\Http\Controllers\Api\CustomerPackageApiController::class, 'plans'])->name('api.package-plans.index');
Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
    Route::post('package-plans/{id}/purchase', [\App\Http\Controllers\Api\CustomerPackageApiController::class, 'purchase'])->whereNumber('id')->name('api.package-plans.purchase');
    Route::get('my-packages',      [\App\Http\Controllers\Api\CustomerPackageApiController::class, 'mine'])->name('api.my-packages.index');
    Route::get('my-packages/{id}', [\App\Http\Controllers\Api\CustomerPackageApiController::class, 'showMine'])->whereNumber('id')->name('api.my-packages.show');
});

/*
|--------------------------------------------------------------------------
| Nội dung CMS cho client (công khai)
| GET  /api/posts, /api/posts/{slug}, /api/posts/{slug}/comments   POST /api/posts/{slug}/comments
| GET  /api/rooms/{id}/comments                                    POST /api/rooms/{id}/comments
| GET  /api/forms/{id}   POST /api/forms/{id}/submit               GET /api/menus/{location}
|--------------------------------------------------------------------------
*/
Route::get('posts',                  [\App\Http\Controllers\Api\CmsClientController::class, 'posts'])->name('api.posts.index');
Route::get('posts/{slug}',           [\App\Http\Controllers\Api\CmsClientController::class, 'post'])->name('api.posts.show');
Route::get('posts/{slug}/comments',  [\App\Http\Controllers\Api\CmsClientController::class, 'postComments'])->name('api.posts.comments');
Route::post('posts/{slug}/comments', [\App\Http\Controllers\Api\CmsClientController::class, 'commentOnPost'])->middleware('throttle:10,1')->name('api.posts.comments.store');
Route::get('rooms/{id}/comments',    [\App\Http\Controllers\Api\CmsClientController::class, 'roomComments'])->name('api.rooms.comments');
Route::post('rooms/{id}/comments',   [\App\Http\Controllers\Api\CmsClientController::class, 'commentOnRoom'])->middleware('throttle:10,1')->name('api.rooms.comments.store');
Route::get('forms/{id}',             [\App\Http\Controllers\Api\CmsClientController::class, 'form'])->whereNumber('id')->name('api.forms.show');
Route::post('forms/{id}/submit',     [\App\Http\Controllers\Api\CmsClientController::class, 'submitForm'])->whereNumber('id')->middleware('throttle:10,1')->name('api.forms.submit');
Route::get('menus/{location}',       [\App\Http\Controllers\Api\CmsClientController::class, 'menu'])->name('api.menus.show');

/*
|--------------------------------------------------------------------------
| Ví điểm thưởng (khách đăng nhập)
| GET /api/wallet, GET /api/wallet/transactions, POST /api/wallet/redeem {points} → mã giảm giá riêng
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->prefix('wallet')->name('api.wallet.')->group(function () {
    Route::get('/',             [\App\Http\Controllers\Api\WalletController::class, 'show'])->name('show');
    Route::get('transactions',  [\App\Http\Controllers\Api\WalletController::class, 'transactions'])->name('transactions');
    Route::post('redeem',       [\App\Http\Controllers\Api\WalletController::class, 'redeem'])->middleware('throttle:20,1')->name('redeem');
});

/*
|--------------------------------------------------------------------------
| Hoá đơn VAT (khách đăng nhập)
| GET /api/invoices; GET|POST /api/orders/{order_code}/invoice
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
    Route::get('invoices',                     [\App\Http\Controllers\Api\InvoiceRequestController::class, 'index'])->name('api.invoices.index');
    Route::get('orders/{order_code}/invoice',  [\App\Http\Controllers\Api\InvoiceRequestController::class, 'show'])->name('api.orders.invoice.show');
    Route::post('orders/{order_code}/invoice', [\App\Http\Controllers\Api\InvoiceRequestController::class, 'store'])->middleware('throttle:10,1')->name('api.orders.invoice.store');
});

/*
|--------------------------------------------------------------------------
| Giải đấu
| GET /api/tournaments, GET /api/tournaments/{id} (công khai: đội, lịch, bảng xếp hạng)
| POST /api/tournaments/{id}/register {team_code}, DELETE /api/tournaments/{id}/register/{team_code}, GET /api/my-tournaments (auth)
|--------------------------------------------------------------------------
*/
Route::get('tournaments',      [\App\Http\Controllers\Api\TournamentController::class, 'index'])->name('api.tournaments.index');
Route::get('tournaments/{id}', [\App\Http\Controllers\Api\TournamentController::class, 'show'])->whereNumber('id')->name('api.tournaments.show');
Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
    Route::post('tournaments/{id}/register',              [\App\Http\Controllers\Api\TournamentController::class, 'register'])->whereNumber('id')->middleware('throttle:20,1')->name('api.tournaments.register');
    Route::delete('tournaments/{id}/register/{teamCode}', [\App\Http\Controllers\Api\TournamentController::class, 'cancel'])->whereNumber('id')->name('api.tournaments.cancel');
    Route::get('my-tournaments',                          [\App\Http\Controllers\Api\TournamentController::class, 'mine'])->name('api.tournaments.mine');
});
