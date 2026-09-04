<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đặt sân ĐỊNH KỲ hàng tuần (khách quen đặt cùng sân + cùng khung giờ, lặp lại N tuần) — mỗi tuần
// vẫn là 1 Order RIÊNG (giữ nguyên mọi bất biến sẵn có: 1 order = 1 lần nhận/trả sân, 1 lần thanh
// toán, 1 order_code), chỉ NỐI các Order cùng 1 loạt lại bằng recurring_group_id để hiển thị/quản lý
// theo nhóm — xem App\Http\Controllers\Api\Admin\RecurringBookingController. Cố tình KHÔNG dùng
// bảng riêng (vd recurring_bookings) để khỏi phải đồng bộ trạng thái 2 nơi — Order vẫn là nguồn sự
// thật duy nhất, recurring_group_id chỉ là khoá nhóm thuần tuý (không có ràng buộc khoá ngoại vì
// không trỏ tới bảng nào, tự sinh UUID ở tầng ứng dụng).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('recurring_group_id')->nullable()->after('order_type')
                ->comment('Nhóm các đơn đặt sân định kỳ (đặt 1 lần, lặp N tuần) — null nếu đơn đặt lẻ bình thường.');
            $table->unsignedInteger('recurring_occurrence_number')->nullable()->after('recurring_group_id')
                ->comment('Thứ tự tuần trong loạt đặt định kỳ (1, 2, 3...) — null nếu không thuộc loạt nào.');
            $table->index('recurring_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['recurring_group_id']);
            $table->dropColumn(['recurring_group_id', 'recurring_occurrence_number']);
        });
    }
};
