<?php

declare(strict_types=1);

// Danh sách ngân hàng Việt Nam phổ biến kèm mã BIN chuẩn Napas — dùng để sinh mã QR VietQR tự
// động (https://img.vietqr.io/image/{bin}-{so_tk}-compact2.png) cho luồng "chuyển khoản thủ công"
// (xem PartnerForm's financialTab() ColorPicker... à Select ngân hàng, và
// BladeThemeV1Controller::vietQrImageUrl()). Danh sách rút gọn ~25 ngân hàng phổ biến nhất — đủ
// dùng thực tế, không cần đầy đủ ~60 ngân hàng của Napas.
return [
    '970436' => 'Vietcombank',
    '970415' => 'VietinBank',
    '970418' => 'BIDV',
    '970405' => 'Agribank',
    '970407' => 'Techcombank',
    '970432' => 'VPBank',
    '970416' => 'ACB',
    '970423' => 'TPBank',
    '970422' => 'MB Bank (Quân đội)',
    '970437' => 'HDBank',
    '970441' => 'VIB',
    '970403' => 'Sacombank',
    '970431' => 'Eximbank',
    '970425' => 'An Bình Bank (ABBANK)',
    '970448' => 'OCB',
    '970438' => 'Bảo Việt Bank (BAOVIET Bank)',
    '970400' => 'SHB',
    '970426' => 'MSB (Hàng Hải)',
    '970427' => 'VietABank',
    '970449' => 'Bản Việt Bank (BVBank)',
    '970433' => 'VietBank',
    '970452' => 'Kienlongbank',
    '970430' => 'PG Bank',
    '970439' => 'Public Bank (PBVN)',
    '970457' => 'Woori Bank',
    '546034' => 'Standard Chartered',
];
