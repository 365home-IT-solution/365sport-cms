<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Đơn đặt sân được thanh toán qua hạn mức công ty (App\Models\CorporateAccount) thay vì khách tự
// trả tiền — xem App\Http\Controllers\Api\Admin\BookingController::store() (chỉ luồng admin/lễ
// tân đặt hộ mới hỗ trợ, KHÔNG có ở luồng khách tự đặt). Nullable — tuyệt đại đa số đơn KHÔNG liên
// quan gì tới B2B, giữ mặc định NULL để không ảnh hưởng dữ liệu/hành vi hiện có. order_type VẪN là
// 'booking' bình thường (đơn thật, có sân/khung giờ) — corporate_account_id chỉ là THÔNG TIN AI
// TRẢ TIỀN, không phải loại đơn mới (khác package_purchase, vốn là loại đơn không có sân thật).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('corporate_account_id')->nullable()->after('package_plan_id')
                ->comment('Đơn được trừ vào hạn mức giờ của công ty này (NULL = đơn thanh toán bình thường) — App\\Models\\CorporateAccount.id.');
            $table->index('corporate_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['corporate_account_id']);
            $table->dropColumn('corporate_account_id');
        });
    }
};
