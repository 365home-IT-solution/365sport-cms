<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_plans', function (Blueprint $table) {
            // Danh sách dòng chữ TỰ DO admin gõ thêm (vd "Tư vấn 1-1", "Xây dựng website riêng
            // theo yêu cầu"...) — CHỈ hiển thị ở trang quảng cáo /lam-doi-tac, KHÔNG khớp với bất
            // kỳ module/permission thật nào (khác hẳn cột "features" — features là khoá tính năng
            // CÓ THẬT trong code, enforce được; extra_features chỉ là text quảng cáo thuần tuý).
            $table->json('extra_features')->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('partner_plans', function (Blueprint $table) {
            $table->dropColumn('extra_features');
        });
    }
};
