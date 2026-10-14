<?php

// Cờ bật/tắt các tính năng thừa kế từ dự án homestay / bán hàng. Đặt sân thể thao KHÔNG cần các tính năng này nên mặc định TẮT;
// mã nguồn và dữ liệu cũ vẫn giữ nguyên, bật lại bằng .env nếu sau này cần.
return [
    // Nghiệp vụ lưu trú: khai báo CCCD / khai báo lưu trú, yêu cầu CCCD người đi cùng khi qua đêm, phụ thu theo số khách.
    'lodging' => (bool) env('FEATURE_LODGING', false),

    // Cho phép đối tác CHƯA tự thiết lập thanh toán (không có ngân hàng, không có khoá PayOS riêng) dùng khoá PayOS CHUNG của nền tảng.
    // Mặc định TẮT: mỗi đối tác phải tự thiết lập thanh toán (trang "Thiết lập thanh toán") trước khi mở bán sân; sân của đối tác chưa thiết lập
    // không nhận đặt online. Chỉ bật khi nền tảng thật sự thu hộ bằng khoá PayOS chung hợp lệ.
    'platform_payos_for_partners' => (bool) env('PLATFORM_PAYOS_FOR_PARTNERS', false),

    // Di sản dự án bán hàng: giao hàng GHN/GHTK, giỏ hàng, túi mù.
    'legacy_shop' => (bool) env('FEATURE_LEGACY_SHOP', false),

    // Các nghiệp vụ tạm ẩn khỏi bản sân thể thao (Filament + API đều bị chặn khi tắt; mã nguồn và dữ liệu giữ nguyên, bật lại bằng .env):
    // đối soát hoa hồng đối tác, giải đấu, kèo (ghép trận), chấm công, tính lương.
    'settlement' => (bool) env('FEATURE_SETTLEMENT', false),
    'tournament' => (bool) env('FEATURE_TOURNAMENT', false),
    'open_match' => (bool) env('FEATURE_OPEN_MATCH', false),
    'attendance' => (bool) env('FEATURE_ATTENDANCE', false),
    'payroll'    => (bool) env('FEATURE_PAYROLL', false),
];
