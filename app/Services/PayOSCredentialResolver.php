<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Partner;
use Illuminate\Support\Facades\Config;
use PayOS\PayOS;

// Điểm DUY NHẤT quyết định "dùng bộ khoá PayOS nào" cho MỌI nơi trong hệ thống có tạo PayOS
// client (checkout khách hàng, webhook, thanh toán phần còn lại, QR tạo tay ở admin) — trước đây
// mỗi nơi tự đọc thẳng Config::get('payos.*') (1 tài khoản merchant DUY NHẤT dùng chung toàn nền
// tảng, xem PaymentServiceProvider). Giờ ưu tiên: (1) đối tác tự cấu hình PayOS riêng
// (Partner::hasPayosConfigured()) → dùng bộ khoá của ĐÚNG đối tác đó; (2) không có → dùng bộ khoá
// CHUNG của nền tảng (config('payos.*'), giữ nguyên hành vi cũ, KHÔNG phá đối tác nào đang chạy
// bình thường). Đối tác được admin CHỌN dùng chuyển khoản (SePay/thủ công, qua
// Partner::payment_method_preference — xem Partner::effectivePaymentMethod()) thì KHÔNG được gọi
// tới đây nữa, lớp này chỉ lo phần "khoá nào", không quyết định "có nên dùng PayOS hay không".
class PayOSCredentialResolver
{
    /**
     * @return array{client_id: string, api_key: string, checksum_key: string}
     */
    public static function resolve(?Partner $partner): array
    {
        if ($partner && $partner->hasPayosConfigured()) {
            return [
                'client_id'    => (string) $partner->payos_client_id,
                'api_key'      => (string) $partner->payos_api_key,
                'checksum_key' => (string) $partner->payos_checksum_key,
            ];
        }

        return [
            'client_id'    => (string) Config::get('payos.client_id'),
            'api_key'      => (string) Config::get('payos.api_key'),
            'checksum_key' => (string) Config::get('payos.checksum_key'),
        ];
    }

    public static function client(?Partner $partner): PayOS
    {
        $creds = self::resolve($partner);

        return new PayOS($creds['client_id'], $creds['api_key'], $creds['checksum_key']);
    }
}
