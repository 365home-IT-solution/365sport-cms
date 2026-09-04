<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đếm dồn số lần khách bị đánh dấu "không đến" (Order::markNoShow() — xem migration cùng ngày
// 2026_09_03_000004_add_no_show_to_orders_table bên module Payment) — TĂNG đúng 1 lần cho mỗi đơn
// bị đánh dấu, KHÔNG tự giảm lại nếu admin gỡ nhãn (chưa có luồng "bỏ đánh dấu no-show" ở bản MVP
// này). Chỉ là bộ đếm hiển thị cho admin tham khảo khi xem xét hạn chế/theo dõi khách — CHƯA gắn bất
// kỳ hành vi tự động nào (không tự khoá tài khoản/chặn đặt sân dù đếm cao), để làm sau nếu cần.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedInteger('no_show_count')->default(0)->after('total_spending');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('no_show_count');
        });
    }
};
