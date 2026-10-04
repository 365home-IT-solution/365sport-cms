<?php

// Cấu hình cho `php artisan audit:project` (App\Console\Commands\AuditProject) — công cụ TỰ KIỂM TRA toàn dự án:
// mỗi tài nguyên Filament có API tương ứng chưa, mỗi route API có chạy được không, các trang client có mở được không.
//
// Có 3 phần "khai báo" (con người quyết định, lệnh kiểm tra chỉ đối chiếu với thực tế):
//  1. modules          — nhóm tài nguyên Filament / trang / route vào module nghiệp vụ nào.
//  2. admin_api        — tài nguyên Filament nào ứng với nhóm route API admin nào (chuẩn hoá {param} thành {}).
//  3. client / admin_extra — danh sách endpoint mà NGHIỆP VỤ đặt sân cần có. Endpoint nào chưa tồn tại sẽ tự hiện "thiếu".
//
// Thêm tài nguyên mới: lệnh tự phát hiện và đưa vào module "Chưa phân loại" cho tới khi bạn xếp nó vào module.

return [

    'groups' => ['Vận hành đặt sân', 'Khách hàng', 'Đối tác & nội bộ', 'Nội dung & hệ thống'],

    // resources / pages = class_basename (bỏ hậu tố không cần, so khớp theo tên đầy đủ của class).
    'modules' => [
        'auth' => ['name' => 'Tài khoản & đăng nhập khách', 'group' => 'Khách hàng', 'code' => ['Customer', 'Auth API'],
            'resources' => ['CustomerResource', 'GuestCustomerResource', 'CccdDeclarationResource'], 'pages' => ['GoogleLoginSettings']],
        'venues' => ['name' => 'Chi nhánh, sân & loại sân', 'group' => 'Vận hành đặt sân', 'code' => ['Category', 'Product', 'SettingCompany', 'Province'],
            'resources' => ['CategoryResource', 'ProductResource', 'RoomTypeResource', 'RoomImageResource', 'RoomAmenityResource', 'RoomAmenityAssignResource',
                'RoomServiceResource', 'RoomSpecialResource', 'AdditionServiceResource', 'BranchResource', 'ProvinceResource'], 'pages' => []],
        'orders' => ['name' => 'Đặt sân & đơn hàng', 'group' => 'Vận hành đặt sân', 'code' => ['Payment (Order)', 'Book', 'SlotWaitlist', 'TimeslotHold'],
            'resources' => ['OrderResource', 'RoomHousekeepingResource'], 'pages' => ['PendingRefunds']],
        'pricing' => ['name' => 'Giá, khung giờ & khuyến mãi', 'group' => 'Vận hành đặt sân', 'code' => ['Book', 'Promotion', 'Coupon', 'PriceBoard'],
            'resources' => ['BookResource', 'PriceBoardResource', 'PromotionResource', 'CouponResource'], 'pages' => []],
        'packages' => ['name' => 'Gói nhiều buổi', 'group' => 'Vận hành đặt sân', 'code' => ['PackagePlan', 'CustomerPackage', 'PackageOrder'],
            'resources' => ['PackagePlanResource', 'PackageOrderResource', 'CustomerPackageResource'], 'pages' => []],
        'payments' => ['name' => 'Thanh toán & hoàn tiền', 'group' => 'Vận hành đặt sân', 'code' => ['Payment', 'PayOS', 'SePay'],
            'resources' => ['PaymentConfigurationResource'], 'pages' => ['GHNConfigPage']],
        'reports' => ['name' => 'Báo cáo & bảng điều khiển', 'group' => 'Vận hành đặt sân', 'code' => ['Dashboard'],
            'resources' => [], 'pages' => ['Dashboard', 'SalaryReport']],
        'community' => ['name' => 'Tìm bạn chơi, đội nhóm & quỹ nhóm', 'group' => 'Khách hàng', 'code' => ['OpenMatch', 'Team', 'BookingFund', 'Coach'],
            'resources' => ['OpenMatchResource', 'TeamResource', 'BookingFundResource', 'CoachResource'], 'pages' => []],
        'membership' => ['name' => 'Hạng thành viên & giới thiệu', 'group' => 'Khách hàng', 'code' => ['MembershipTier', 'Referral'],
            'resources' => ['MembershipTierResource'], 'pages' => []],
        'reviews' => ['name' => 'Đánh giá & bình luận', 'group' => 'Khách hàng', 'code' => ['Comment', 'RoomRating'],
            'resources' => ['CommentResource', 'CommentPostResource', 'CommentProductResource'], 'pages' => []],
        'chat' => ['name' => 'Chat chăm sóc khách', 'group' => 'Khách hàng', 'code' => ['Chat'],
            'resources' => [], 'pages' => ['CustomerChat']],
        'maps' => ['name' => 'Bản đồ, tìm kiếm & chỉ đường', 'group' => 'Khách hàng', 'code' => ['Province', 'Ward', 'Directions'],
            'resources' => [], 'pages' => ['DirectionsSettings']],
        'notify' => ['name' => 'Nội dung app & thông báo', 'group' => 'Khách hàng', 'code' => ['AppPage', 'Banner', 'Zns', 'NotificationFcm'],
            'resources' => ['AppPageResource', 'BannerResource', 'AskUserResource', 'NotificationFcmResource', 'ZnsNotificationResource'], 'pages' => ['ManagePopups']],
        'partner' => ['name' => 'Đối tác & gói dịch vụ', 'group' => 'Đối tác & nội bộ', 'code' => ['Partner', 'PartnerPlan', 'Subscription'],
            'resources' => ['PartnerResource', 'PartnerLeadResource', 'PartnerPlanResource', 'SubscriptionPaymentResource'], 'pages' => ['MySubscription', 'PartnerFeatureMatrix']],
        'access' => ['name' => 'Người dùng, vai trò & phân quyền', 'group' => 'Đối tác & nội bộ', 'code' => ['User', 'DataPermission', 'AuditLog'],
            'resources' => ['UserResource', 'RoleResource', 'DataScopeResource', 'UserBranchPermissionResource', 'AuditLogResource'], 'pages' => []],
        'hr' => ['name' => 'Nhân sự & lương', 'group' => 'Đối tác & nội bộ', 'code' => ['Employee'],
            'resources' => ['DepartmentResource', 'PositionResource', 'SalaryTypeResource', 'AllowanceTypeResource', 'DeductionTypeResource', 'SalaryTemplateResource', 'ConsultationLogResource'], 'pages' => []],
        'warehouse' => ['name' => 'Kho & dụng cụ', 'group' => 'Đối tác & nội bộ', 'code' => ['Warehouse'],
            'resources' => ['WarehouseItemResource', 'WarehouseCategoryResource', 'WarehouseUnitResource', 'WarehouseStockInResource', 'WarehouseStockOutResource',
                'WarehouseStockCheckResource', 'WarehouseItemRentalResource'], 'pages' => []],
        'camera' => ['name' => 'Camera an ninh', 'group' => 'Đối tác & nội bộ', 'code' => ['Camera', 'CameraAlert'],
            'resources' => ['CameraResource', 'CameraAlertResource'], 'pages' => ['CameraMonitor', 'ManageCamera']],
        'corporate' => ['name' => 'Khách doanh nghiệp', 'group' => 'Đối tác & nội bộ', 'code' => ['CorporateAccount'],
            'resources' => ['CorporateAccountResource'], 'pages' => []],
        'cms' => ['name' => 'Nội dung website (CMS)', 'group' => 'Nội dung & hệ thống', 'code' => ['Post', 'Page', 'Menu', 'Component', 'Tag', 'QA', 'Process', 'Form', 'ThemeSetting', 'ThemeStudio'],
            'resources' => ['PostResource', 'PostCategoryResource', 'TagResource', 'QAResource', 'PageResource', 'MenuResource', 'ComponentResource', 'ThemeResource',
                'ThemeStudioResource', 'FormResource', 'FormSubmissionResource', 'FormNotificationResource', 'EmailSettingResource', 'MediaResource', 'FolderResource'], 'pages' => []],
        'system' => ['name' => 'Cấu hình hệ thống & tích hợp', 'group' => 'Nội dung & hệ thống', 'code' => ['Settings', 'Log Viewer'],
            'resources' => ['SettingCompanyResource'], 'pages' => ['ManageGeneral', 'ManageMail', 'MyProfilePage']],
    ],

    // Tài nguyên Filament => nhóm route API admin tương ứng (không có = chưa có API).
    // Danh sách để hỗ trợ nhiều nhóm route cho một tài nguyên (vd Sân = products + rooms).
    'admin_api' => [
        'CustomerResource' => ['api/admin/customers'],
        'CategoryResource' => ['api/admin/categories'],
        'ProductResource' => ['api/admin/products'],
        'RoomTypeResource' => ['api/admin/room-types'],
        'RoomImageResource' => ['api/admin/products/{}/images'],
        'RoomAmenityResource' => ['api/admin/room-amenities'],
        'RoomAmenityAssignResource' => ['api/admin/products/{}/amenities'],
        'RoomServiceResource' => ['api/admin/products/{}/services'],
        'RoomSpecialResource' => ['api/admin/products/{}/specials'],
        'AdditionServiceResource' => ['api/admin/services'],
        'BranchResource' => ['api/admin/branches'],
        'OrderResource' => ['api/admin/orders'],
        'RoomHousekeepingResource' => ['api/admin/room-housekeeping'],
        'PriceBoardResource' => ['api/admin/price-boards'],
        'PromotionResource' => ['api/admin/promotions'],
        'CouponResource' => ['api/admin/coupons'],
        'PackagePlanResource' => ['api/admin/package-plans'],
        'PackageOrderResource' => ['api/admin/package-orders'],
        'CustomerPackageResource' => ['api/admin/customer-packages'],
        'CoachResource' => ['api/admin/coaches'],
        'PartnerResource' => ['api/admin/partner-signups'],
        'SubscriptionPaymentResource' => ['api/admin/subscription-payments'],
        'DepartmentResource' => ['api/admin/departments'],
        'PositionResource' => ['api/admin/positions'],
        'SalaryTypeResource' => ['api/admin/salary-types'],
        'AllowanceTypeResource' => ['api/admin/allowance-types'],
        'DeductionTypeResource' => ['api/admin/deduction-types'],
        'SalaryTemplateResource' => ['api/admin/salary-templates'],
        'WarehouseItemResource' => ['api/admin/warehouse/items'],
        'WarehouseCategoryResource' => ['api/admin/warehouse/categories'],
        'WarehouseUnitResource' => ['api/admin/warehouse/units'],
        'WarehouseStockInResource' => ['api/admin/warehouse/stock-ins'],
        'WarehouseStockOutResource' => ['api/admin/warehouse/stock-outs'],
        'WarehouseStockCheckResource' => ['api/admin/warehouse/stock-checks'],
        'WarehouseItemRentalResource' => ['api/admin/warehouse/item-rentals'],
        'CameraResource' => ['api/admin/cameras'],
        'CameraAlertResource' => ['api/admin/camera-alerts'],
        'CorporateAccountResource' => ['api/admin/corporate-accounts'],
        'TagResource' => ['api/admin/tags'],
    ],

    // Các màn Filament dạng "trang" => nhóm route API admin đọc cùng dữ liệu.
    'admin_api_pages' => [
        'Dashboard' => ['api/admin/dashboard/overview'],
        'CustomerChat' => ['api/admin/chat'],
        'CameraMonitor' => ['api/admin/cameras'],
        'MySubscription' => ['api/admin/subscription'],
    ],

    // Tài nguyên / trang mà API admin KHÔNG bắt buộc (nội dung website, cấu hình, quảng bá…): thiếu API vẫn không bị tính là thiếu.
    'api_optional' => [
        'PostResource', 'PostCategoryResource', 'TagResource', 'QAResource', 'PageResource', 'MenuResource', 'ComponentResource', 'ThemeResource', 'ThemeStudioResource',
        'FormResource', 'FormSubmissionResource', 'FormNotificationResource', 'EmailSettingResource', 'MediaResource', 'FolderResource',
        'SettingCompanyResource', 'ManageGeneral', 'ManageMail', 'GoogleLoginSettings', 'DirectionsSettings', 'MyProfilePage', 'ManageCamera', 'ManagePopups',
        'AppPageResource', 'BannerResource', 'AskUserResource', 'NotificationFcmResource', 'ZnsNotificationResource', 'GHNConfigPage', 'PaymentConfigurationResource',
        'PartnerFeatureMatrix', 'ProvinceResource',
    ],

    // Endpoint admin mà nghiệp vụ cần ngoài việc "mỗi màn Filament có CRUD": [mô-đun => [[nhãn, METHOD, uri chuẩn hoá]]]
    'admin_extra' => [
        'orders' => [
            ['Check-in sân', 'POST', 'api/admin/orders/{}/check-in'],
            ['Check-out sân', 'POST', 'api/admin/orders/{}/check-out'],
            ['Danh sách hoàn tiền chờ xử lý', 'GET', 'api/admin/refunds'],
            ['Xác nhận đã hoàn tiền', 'POST', 'api/admin/refunds/{}/complete'],
            ['Đổi giờ / đổi sân đơn đã đặt', 'POST', 'api/admin/orders/{}/reschedule'],
        ],
        'payments' => [
            ['Sổ giao dịch / đối soát', 'GET', 'api/admin/transactions'],
        ],
        'reports' => [
            ['Báo cáo hoa hồng đối tác', 'GET', 'api/admin/reports/commission'],
            ['Báo cáo lương', 'GET', 'api/admin/reports/payroll'],
        ],
        'hr' => [
            ['Chấm công', 'GET', 'api/admin/attendance'],
            ['Chốt bảng lương tháng', 'POST', 'api/admin/payroll/close'],
        ],
        'partner' => [
            ['Đối soát / chi trả hoa hồng', 'GET', 'api/admin/partner-settlements'],
            ['Hợp đồng và thông tin đối tác', 'GET', 'api/admin/partners'],
        ],
        'reviews' => [
            ['Duyệt / ẩn đánh giá', 'POST', 'api/admin/ratings/{}/moderate'],
            ['Trả lời đánh giá', 'POST', 'api/admin/ratings/{}/reply'],
        ],
        'payments_invoice' => [],
    ],

    // Endpoint client (khách / app / công khai) mà nghiệp vụ cần: [nhãn, METHOD, uri chuẩn hoá, 'auth' => customer|guest|public]
    'client' => [
        'auth' => [
            ['Gửi OTP', 'POST', 'api/auth/send-otp', 'public'], ['Xác thực OTP', 'POST', 'api/auth/verify-otp', 'public'],
            ['Đăng ký', 'POST', 'api/auth/register', 'public'], ['Đăng nhập', 'POST', 'api/auth/login', 'public'],
            ['Đăng nhập email', 'POST', 'api/auth/email-login', 'public'], ['Đăng nhập Google', 'POST', 'api/auth/google/exchange', 'public'],
            ['Xem hồ sơ', 'GET', 'api/auth/me', 'customer'], ['Cập nhật hồ sơ', 'POST', 'api/auth/me', 'customer'],
            ['Đổi mật khẩu', 'POST', 'api/auth/change-password', 'customer'], ['Vô hiệu hoá tài khoản', 'POST', 'api/auth/deactivate', 'customer'],
            ['Xoá tài khoản', 'DELETE', 'api/auth/account', 'customer'], ['Gửi mã xác thực email', 'POST', 'api/auth/email/send-code', 'customer'],
            ['Xác thực email', 'POST', 'api/auth/email/verify', 'customer'], ['Lưu token thiết bị', 'POST', 'api/auth/device-token', 'customer'],
            ['Xoá token thiết bị', 'DELETE', 'api/auth/device-token', 'customer'], ['Mã giới thiệu', 'GET', 'api/auth/referral', 'customer'],
            ['Xoá người đi cùng', 'DELETE', 'api/auth/companions/{}', 'customer'], ['Lựa chọn hồ sơ người chơi', 'GET', 'api/v1/player-profile-options', 'public'],
            ['Đăng xuất', 'POST', 'api/auth/logout', 'customer'],
        ],
        'venues' => [
            ['Danh sách chi nhánh', 'GET', 'api/v1/branches', 'public'], ['Chi tiết chi nhánh', 'GET', 'api/v1/branches/{}', 'public'],
            ['Khung giờ chi nhánh', 'GET', 'api/v1/branches/{}/time-slots', 'public'], ['Sân theo ngày', 'GET', 'api/v1/branches/{}/daily-rooms', 'public'],
            ['Chi tiết sân', 'GET', 'api/rooms/{}', 'public'], ['Lịch trống của sân', 'GET', 'api/rooms/{}/dates', 'public'],
            ['Xem trước giá', 'GET', 'api/rooms/{}/price-preview', 'public'], ['Phụ thu khách', 'GET', 'api/rooms/{}/guest-surcharge-preview', 'public'],
            ['Loại sân', 'GET', 'api/room-types', 'public'], ['Tìm kiếm', 'GET', 'api/v1/search', 'public'], ['Tìm chi nhánh', 'GET', 'api/v1/search/branches', 'public'],
            ['Gợi ý tìm kiếm', 'GET', 'api/v1/search/suggestions', 'public'], ['Danh sách tỉnh có sân', 'GET', 'api/v1/provinces', 'public'],
            ['Trang chủ app', 'GET', 'api/v1/home', 'public'], ['Khung giờ trống', 'GET', 'api/slots', 'public'], ['GraphQL', 'POST', 'api/graphql', 'public'],
        ],
        'orders' => [
            ['Danh sách đơn của tôi', 'GET', 'api/orders', 'customer'], ['Tạo đơn', 'POST', 'api/orders', 'customer'], ['Xem đơn', 'GET', 'api/orders/{}', 'customer'],
            ['Sửa đơn', 'POST', 'api/orders/{}', 'customer'], ['Thêm dịch vụ', 'POST', 'api/orders/{}/services', 'customer'],
            ['Giữ chỗ', 'POST', 'api/rooms/{}/hold', 'customer'], ['Bỏ giữ chỗ', 'DELETE', 'api/rooms/{}/hold', 'customer'],
            ['Danh sách chờ', 'GET', 'api/waitlist', 'customer'], ['Vào danh sách chờ', 'POST', 'api/waitlist', 'customer'], ['Rời danh sách chờ', 'DELETE', 'api/waitlist/{}', 'customer'],
            ['Đơn khách vãng lai', 'GET', 'api/guest/orders', 'guest'], ['Tạo đơn khách vãng lai', 'POST', 'api/guest/orders', 'guest'],
            ['Khách huỷ đơn đã thanh toán', 'POST', 'api/orders/{}/cancel', 'customer', 'Web đã có (account-page, AccountPage::cancelOrder, hoàn 70%); app chưa có API'], ['Khách yêu cầu hoàn tiền', 'POST', 'api/orders/{}/refund-request', 'customer'],
            ['Khách đổi giờ / đổi sân', 'POST', 'api/orders/{}/reschedule', 'customer'], ['Mã check-in (QR) của đơn', 'GET', 'api/orders/{}/check-in-code', 'customer'],
        ],
        'pricing' => [
            ['Kiểm tra mã giảm giá', 'POST', 'api/coupons/validate', 'customer'], ['Mã giảm giá của tôi', 'GET', 'api/coupons/mine', 'customer'],
        ],
        'packages' => [
            ['Gói đang bán', 'GET', 'api/package-plans', 'public', 'Web đã có cửa sổ mua gói (Livewire)'], ['Mua gói', 'POST', 'api/package-plans/{}/purchase', 'customer', 'Web đã có; app chưa có API'],
            ['Gói của tôi', 'GET', 'api/customer-packages', 'customer'], ['Chi tiết gói đã mua', 'GET', 'api/customer-packages/{}', 'customer'],
        ],
        'payments' => [
            ['Mã QR thanh toán', 'GET', 'api/orders/{}/payment-qr', 'customer'], ['Thanh toán lại', 'POST', 'api/orders/{}/retry-payment', 'customer'],
            ['Thanh toán phần còn lại', 'POST', 'api/orders/{}/remaining-payment', 'customer'], ['Gửi biên lai chuyển khoản', 'POST', 'api/orders/{}/payment-proof', 'customer'],
            ['Trạng thái thanh toán (khách vãng lai)', 'GET', 'api/guest/orders/{}/payment-status', 'guest'],
        ],
        'community' => [
            ['Kèo: danh sách', 'GET', 'api/open-matches', 'customer'], ['Kèo: tạo', 'POST', 'api/open-matches', 'customer'], ['Kèo: chi tiết', 'GET', 'api/open-matches/{}', 'customer'],
            ['Kèo: tham gia', 'POST', 'api/open-matches/{}/join', 'customer'], ['Kèo: rời', 'POST', 'api/open-matches/{}/leave', 'customer'],
            ['Nhóm: của bạn', 'GET', 'api/teams', 'customer'], ['Nhóm: yêu cầu đã gửi', 'GET', 'api/teams/requests', 'customer'], ['Nhóm: lời mời', 'GET', 'api/teams/invitations', 'customer'],
            ['Nhóm: tạo', 'POST', 'api/teams', 'customer'], ['Nhóm: chi tiết', 'GET', 'api/teams/{}', 'customer'], ['Nhóm: xin vào', 'POST', 'api/teams/{}/join', 'customer'],
            ['Nhóm: trả lời lời mời', 'POST', 'api/teams/{}/respond', 'customer'], ['Nhóm: mời theo SĐT', 'POST', 'api/teams/{}/invite', 'customer'],
            ['Quỹ: danh sách', 'GET', 'api/booking-funds', 'customer'], ['Quỹ: tạo', 'POST', 'api/booking-funds', 'customer'], ['Quỹ: chi tiết', 'GET', 'api/booking-funds/{}', 'customer'],
            ['Quỹ: chat', 'GET', 'api/booking-funds/{}/messages', 'customer'], ['HLV của chi nhánh', 'GET', 'api/v1/branches/{}/coaches', 'public'],
            ['Nhóm: chat', 'GET', 'api/teams/{}/messages', 'customer', 'Quỹ nhóm đã có chat, đội nhóm chưa'],
        ],
        'membership' => [
            ['Hạng của tôi', 'GET', 'api/membership', 'customer'], ['Các hạng thành viên', 'GET', 'api/membership/tiers', 'customer'],
            ['Điểm thưởng / ví', 'GET', 'api/wallet', 'customer'],
        ],
        'reviews' => [
            ['Xem đánh giá sân', 'GET', 'api/rooms/{}/ratings', 'public'], ['Gửi đánh giá', 'POST', 'api/rooms/{}/ratings', 'customer'], ['Xoá đánh giá', 'DELETE', 'api/rooms/{}/ratings', 'customer'],
            ['Bình luận bài viết', 'GET', 'api/comment', 'public'], ['Gửi bình luận', 'POST', 'api/comment', 'customer'],
        ],
        'chat' => [
            ['Hội thoại', 'GET', 'api/chat', 'customer'], ['Tin nhắn', 'GET', 'api/chat/messages', 'customer'], ['Gửi tin', 'POST', 'api/chat/messages', 'customer'],
            ['Đã đọc', 'POST', 'api/chat/read', 'customer'], ['Chat theo đơn', 'GET', 'api/chat/order/{}', 'customer'],
        ],
        'maps' => [
            ['Tính đường đi', 'POST', 'api/directions/route', 'public'], ['Gợi ý địa điểm', 'GET', 'api/v1/search/locations', 'public'],
            ['Cấu hình bản đồ', 'GET', 'api/v1/config/map', 'public'], ['Phường/Xã', 'GET', 'api/v2/ward', 'public'], ['Nhận diện tỉnh theo toạ độ', 'GET', 'api/v1/provinces/detect', 'public'],
        ],
        'notify' => [
            ['Popup', 'GET', 'api/v1/popups', 'public'], ['Thông báo khi vào app', 'GET', 'api/v1/ask-user/{}', 'public'],
            ['Thông báo của tôi', 'GET', 'api/notifications', 'customer'], ['Đọc tất cả', 'POST', 'api/notifications/read-all', 'customer'], ['Đọc một thông báo', 'POST', 'api/notifications/{}/read', 'customer'],
            ['Thông báo khách vãng lai', 'GET', 'api/guest/notifications', 'guest'],
        ],
        'partner' => [
            ['Gói đối tác', 'GET', 'api/public/partner-plans', 'public'], ['Địa chỉ đăng ký', 'GET', 'api/public/partner-address', 'public'],
            ['Đăng ký làm đối tác', 'POST', 'api/public/partner-purchase', 'public'], ['Tiến trình đăng ký', 'GET', 'api/public/partner-purchase/{}', 'public'],
            ['Kiểm tra tên miền', 'GET', 'api/check-domain', 'public'],
        ],
        'cms' => [
            ['Trang tĩnh', 'GET', 'api/pages/{}', 'public'], ['Danh sách bài viết', 'GET', 'api/posts', 'public'], ['Chi tiết bài viết', 'GET', 'api/posts/{}', 'public'],
            ['Gửi biểu mẫu liên hệ', 'POST', 'api/forms/{}/submit', 'public'], ['Menu cho app', 'GET', 'api/menus/{}', 'public'],
        ],
        'system' => [
            ['Cấu hình chung', 'GET', 'api/v1/config', 'public'],
        ],
    ],

    // Route không thuộc tài nguyên Filament nào nhưng vẫn cần gán module để thống kê khi gọi thử (khớp tiền tố DÀI NHẤT).
    'route_modules' => [
        'api/admin/login' => 'access', 'api/admin/logout' => 'access', 'api/admin/me' => 'access',
        'api/admin/rooms' => 'venues', 'api/admin/time-slots' => 'pricing', 'api/admin/recurring-bookings' => 'orders',
        'api/admin/dashboard' => 'reports', 'api/admin/reports' => 'reports', 'api/admin/chat' => 'chat',
        'api/admin/employees' => 'hr', 'api/admin/subscription' => 'partner', 'api/admin/camera-settings' => 'camera',
        'admin/api/orders' => 'orders', 'admin/api/rooms' => 'orders', 'admin/api/room-cards' => 'orders', 'admin/api' => 'reports',
        'api/auth' => 'auth', 'api/v1/player-profile-options' => 'auth',
        'api/v1/branches' => 'venues', 'api/v1/search' => 'venues', 'api/v1/provinces' => 'venues', 'api/v1/wards' => 'venues', 'api/v2' => 'venues',
        'api/rooms' => 'venues', 'api/room-types' => 'venues', 'api/v1/room-types' => 'venues', 'api/slots' => 'venues', 'api/v1/home' => 'venues', 'api/graphql' => 'venues',
        'api/orders' => 'orders', 'api/guest' => 'orders', 'api/waitlist' => 'orders', 'api/wishlist' => 'venues', 'api/v1/guest' => 'orders',
        'api/coupons' => 'pricing', 'api/open-matches' => 'community', 'api/teams' => 'community', 'api/booking-funds' => 'community',
        'api/membership' => 'membership', 'api/chat' => 'chat', 'api/directions' => 'maps', 'api/v1/config' => 'system',
        'api/notifications' => 'notify', 'api/v1/popups' => 'notify', 'api/v1/ask-user' => 'notify',
        'api/public' => 'partner', 'api/check-domain' => 'partner', 'api/comment' => 'reviews', 'api/camera-media' => 'camera', 'api/pages' => 'cms',
    ],

    // Route KHÔNG gọi thử vì có tác dụng phá huỷ tài khoản đang dùng để kiểm tra (xoá/vô hiệu hoá tài khoản, đăng xuất…).
    'skip_live' => [
        'POST api/auth/deactivate', 'DELETE api/auth/account', 'POST api/auth/logout', 'POST api/admin/logout',
    ],
];
