<?php

declare(strict_types=1);

// Nhóm nhỏ hiển thị BÊN TRONG dropdown mega menu của top navigation (admin panel) — thuần
// trình bày, KHÔNG liên quan gì đến Filament ->navigationGroup() thật (những cái đó vẫn là
// "Quản lý" / "Cấu hình web" / "Phân quyền" / "Quản lý API" như cũ). Dùng bởi
// resources/views/vendor/filament-panels/components/topbar/index.blade.php để chia các mục
// trong 1 dropdown thành nhiều cột có tiêu đề (giống "Resources" / "Company" của các mega menu
// SaaS phổ biến), thay vì 1 danh sách dài duy nhất.
//
// Khớp theo navigationLabel (chuỗi hiển thị THẬT SỰ của resource — nhiều resource dùng
// getNavigationLabel() override hoặc __() qua lang file, không phải lúc nào cũng đúng như tên
// class, đã tự kiểm tra lại từng cái qua tinker/đọc code trước khi đưa vào đây) — không khớp được
// thì mục đó rơi vào cột "khác" ở cuối, không bị mất khỏi menu.
//
// Cấu trúc cột dựa theo mega menu mẫu (365home-cms) + các điều chỉnh trực tiếp theo yêu cầu sau
// khi xem thử trên trình duyệt: "Quản lý khách hàng" chuyển hẳn sang top-level "Phân quyền" (khác
// với mega menu mẫu gốc, đây là chỉnh tay riêng cho sport); "Thông báo" (Push Notification) chuyển
// sang "Cấu hình web"; nhóm Lương (Phòng ban/Chức danh/Loại lương/Loại phụ cấp/Loại giảm trừ) ẨN
// HẲN khỏi menu (shouldRegisterNavigation() => false ở từng Resource, xem
// Modules/Employee/App/Filament/Resources/*.php) — chỉ giữ lại "Mẫu lương" (SalaryTemplateResource,
// không nằm trong danh sách bị ẩn). "Khách đã mua gói"/"Quỹ nhóm góp tiền" trước đây rơi vào cột
// "Khác" tự động do quên liệt kê — gắn hẳn vào "Nội dung & Marketing" cạnh "Gói nhiều buổi".
//
// LƯU Ý: mega-menu.php chỉ NHÓM HIỂN THỊ các mục ĐANG THUỘC ĐÚNG top-level navigationGroup đó —
// đổi 1 mục sang cột ở top-level KHÁC (vd Khách hàng: Quản lý -> Phân quyền) bắt buộc phải đổi
// CẢ navigationGroup THẬT của Resource đó (xem CustomerResource/MembershipTierResource/
// GuestCustomerResource/CustomerChat page/NotificationFcmResource), không chỉ sửa file này.
return [
    'Quản lý' => [
        'Quản lý vận hành' => [
            'Dịch vụ Sân', 'Thiết lập Sân', 'Tiện ích Sân', 'Gán Tiện Ích Sân',
            'Ảnh Sân', 'Điểm Đặc Biệt', 'Danh mục Sân', 'BOOKING',
            'Chi nhánh và khu vực', 'Tỉnh/Thành phố',
        ],
        'Quản lý giá & khuyến mãi' => ['Hệ thống giá', 'Bảng giá', 'Ưu đãi', 'Mã giảm giá', 'Pass Cổng'],
        'Nội dung & Marketing' => ['Bài viết', 'Dịch vụ Bổ sung', 'Gói nhiều buổi', 'Tiện ích', 'Đơn mua gói', 'Khách đã mua gói', 'Quỹ nhóm góp tiền'],
        'Quản lý kho' => ['Nhóm vật tư', 'Đơn vị tính', 'Danh mục vật tư', 'Phiếu nhập kho', 'Phiếu xuất kho', 'Phiếu kiểm kê'],
        'Đối tác & Báo cáo' => [
            'Đối tác', 'Gói đối tác', 'Tính năng theo gói', 'Yêu cầu đăng ký',
            'Thống kê lương', 'Mẫu lương', 'Cấu hình biểu mẫu', 'Biểu mẫu đến',
        ],
        'Khác' => ['Kiểm tra dọn sân', 'Khai báo lưu trú', 'Tư vấn khách hàng'],
    ],

    'Cấu hình web' => [
        'Cấu hình chung' => ['Chung', 'Chi nhánh', 'Thông tin công ty'],
        'Giao diện website' => ['Thành phần', 'Menu', 'Trang', 'Theme', 'Trang App', 'Banner'],
        'Thanh toán & Tích hợp bên thứ ba' => ['Thanh toán online', 'Email'],
        'Thông báo' => ['Push Notification'],
        // Không nằm trong mega menu mẫu (365home không có/không hiện các module này) — vẫn cần có
        // chỗ chứa vì resource thật sự tồn tại ở sport, gom chung 1 cột "Khác" thay vì bịa ra cột
        // riêng không có căn cứ.
        'Khác' => [
            'Quản lí quy trình', 'QA', 'Thông báo ZNS',
            'Phân tích bình luận', 'Bình luận bài viết', 'Bình luận sân',
            'Thông báo và chú ý', 'Biểu mẫu',
        ],
    ],

    'Phân quyền' => [
        'Vai trò & Phân quyền' => ['Vai trò', 'Phân quyền Chi nhánh'],
        'Thành viên nội bộ' => ['Thành viên', 'Lịch sử thao tác'],
        'Quản lý khách hàng' => ['Khách hàng', 'Hạng thành viên', 'Tin nhắn khách hàng', 'Khách vãng lai'],
        'Khác' => ['Phân quyền dữ liệu'],
    ],

    // Home có thêm cột "Nội dung app" (APP/POPUP) — sport không có resource tương đương (Trang App
    // đã nằm ở "Cấu hình web", sport không có PopupImage), nên bỏ qua cột đó, chỉ còn 1 mục thật.
    'Quản lý API' => [
        'Khác' => ['Thông báo vào app'],
    ],
];
