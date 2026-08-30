<?php

declare(strict_types=1);

// Nhóm nhỏ hiển thị BÊN TRONG dropdown mega menu của top navigation (admin panel) — thuần
// trình bày, KHÔNG liên quan gì đến Filament ->navigationGroup() thật (những cái đó vẫn là
// "Quản lý" / "Cấu hình web" / "Phân quyền" / "Quản lý API" như cũ). Dùng bởi
// resources/views/vendor/filament-panels/components/topbar/index.blade.php để chia các mục
// trong 1 dropdown thành nhiều cột có tiêu đề (giống "Resources" / "Company" của các mega menu
// SaaS phổ biến), thay vì 1 danh sách dài duy nhất.
//
// Khớp theo navigationLabel (chuỗi hiển thị) của từng mục — không khớp được thì mục đó rơi vào
// cột "khác" ở cuối, không bị mất khỏi menu.
return [
    'Quản lý' => [
        'Quản lý vận hành / Booking' => ['BOOKING', 'Chi nhánh và khu vực', 'Thiết lập Sân', 'Mật khẩu khóa thủ công', 'Khai báo lưu trú'],
        'Giá & Khuyến mãi' => ['Hệ thống giá', 'Ưu đãi', 'Mã giảm giá', 'Pass Cổng'],
        // 'Gói nhiều buổi' (PackagePlanResource) và 'Tiện ích' (TagResource — tên nhãn gây nhầm,
        // thực chất là quản lý Thẻ/Tag) trước đây không khớp mục nào ở đây nên rơi vào cột "Khác"
        // tự động — chuyển hẳn vào đây theo đúng yêu cầu, không còn cột "Khác" rời rạc nữa.
        'Nội dung & Marketing' => ['Bài viết', 'Dịch vụ Bổ sung', 'Gói nhiều buổi', 'Tiện ích'],
        'Chăm sóc khách hàng' => ['Tư vấn khách hàng', 'Tin nhắn khách hàng'],
        // Toàn bộ mục liên quan đối tác (hồ sơ, gói dịch vụ, ma trận tính năng, lead đăng ký) gộp
        // chung 1 cột — KHÔNG tách thành nhóm điều hướng Filament riêng (navigationGroup vẫn là
        // 'Quản lý' như các mục khác), chỉ khác nhau ở cách NHÓM HIỂN THỊ trong mega menu này.
        // 'Cấu hình biểu mẫu'/'Biểu mẫu đến' (Modules\Form) trước đây là 1 nhóm menu riêng "Biểu
        // mẫu" — gộp vào đây, đúng chỗ nhất vì hiện chỉ dùng cho form "Đăng ký làm đối tác".
        'Đối tác & Báo cáo' => ['Đối tác', 'Gói đối tác', 'Tính năng theo gói', 'Yêu cầu đăng ký', 'Thống kê lương', 'Cấu hình biểu mẫu', 'Biểu mẫu đến'],
        // 6 resource của module Kho (trước đây là navigationGroup Filament riêng "Quản lý kho",
        // tách hẳn top-level) — gộp vào đây cùng cách đã làm với "Biểu mẫu": đổi getNavigationGroup()
        // của cả 6 resource về 'Quản lý', chỉ khác nhau ở cột hiển thị trong mega menu này.
        'Quản lý kho' => ['Nhóm vật tư', 'Đơn vị tính', 'Danh mục vật tư', 'Phiếu nhập kho', 'Phiếu xuất kho', 'Phiếu kiểm kê'],
    ],

    'Cấu hình web' => [
        'Cấu hình chung' => ['Chung', 'Chi nhánh', 'Thông tin công ty'],
        'Giao diện & Nội dung Website' => ['Thành phần', 'Menu', 'Trang', 'Theme', 'Thư viện'],
        'Thanh toán & Tích hợp bên thứ ba' => ['Thanh toán online', 'Tài khoản TTLock', 'Email'],
    ],

    'Phân quyền' => [
        'Vai trò & Phân quyền' => ['Vai trò', 'Phân quyền dữ liệu', 'Phân quyền Chi nhánh'],
        'Thành viên nội bộ' => ['Thành viên', 'Lịch sử thao tác'],
        'Khách hàng bên ngoài' => ['Khách hàng', 'Hạng thành viên'],
    ],

    'Quản lý API' => [
        'Thông tin & Cấu hình Sân' => ['Danh mục Sân', 'Ảnh Sân', 'Điểm Đặc Biệt', 'Tiện ích Sân', 'Gán Tiện Ích Sân', 'Dịch vụ Sân'],
        'Vận hành Sân' => ['Kiểm tra dọn sân', 'Khách vãng lai'],
        'Nội dung & Giao diện App' => ['Trang App', 'Banner'],
        'Thông báo & Dữ liệu hệ thống' => ['Push Notification', 'Thông báo vào app', 'Tỉnh/Thành phố'],
    ],
];
