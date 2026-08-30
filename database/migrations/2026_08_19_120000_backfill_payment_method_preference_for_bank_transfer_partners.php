<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Partner::effectivePaymentMethod() vừa đổi từ "tự suy luận theo đã cấu hình gì" sang "CHỈ theo
// đúng lựa chọn admin ở payment_method_preference" (3 giá trị rõ ràng: payos /
// bank_transfer_sepay / bank_transfer_manual — xem migration
// 2026_08_18_190000_add_payment_method_preference_to_partners_table.php).
//
// RỦI RO nếu KHÔNG backfill: đối tác nào đã cấu hình đủ STK ngân hàng nhưng CHƯA cấu hình PayOS
// riêng và CHƯA từng đụng tới field này (NULL) — theo logic CŨ tự động dùng 'bank_transfer', theo
// logic MỚI sẽ rơi về 'payos' (vì NULL không nằm trong danh sách "muốn chuyển khoản" nữa) — ÂM
// THẦM đổi phương thức thu tiền của đối tác đang chạy thật (vd Mamble Sport dùng trong quá trình
// test SePay), có thể khiến tiền chuyển vào tài khoản PayOS CHUNG của nền tảng thay vì tài khoản
// ngân hàng riêng của đối tác. Migration này giữ nguyên hành vi CŨ cho các đối tác thuộc diện này
// bằng cách gán rõ 'bank_transfer_manual' (an toàn — không tự bật SePay tự động cho ai chưa từng
// được cấp tính năng 'sepay_auto_confirm' qua gói, chỉ giữ đúng "vẫn dùng chuyển khoản").
return new class extends Migration
{
    public function up(): void
    {
        DB::table('partners')
            ->whereNull('payment_method_preference')
            ->whereNotNull('bank_bin')
            ->whereNotNull('bank_account_number')
            ->whereNotNull('bank_account_holder')
            ->where(function ($q) {
                $q->whereNull('payos_client_id')->orWhere('payos_client_id', '');
            })
            ->update(['payment_method_preference' => 'bank_transfer_manual']);
    }

    public function down(): void
    {
        // Không hoàn tác — không phân biệt được dòng nào do migration này set với dòng admin tự
        // chọn sau đó (idempotent theo 1 chiều, an toàn khi rollback vì cột vẫn còn nguyên giá trị
        // hợp lệ, không có schema nào cần revert).
    }
};
