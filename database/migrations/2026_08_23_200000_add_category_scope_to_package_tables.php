<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mở rộng gói nhiều buổi để áp dụng được cho CẢ CHI NHÁNH (mọi sân thuộc 1 Category
// category_type=product), không chỉ riêng 1 sân như thiết kế ban đầu — theo yêu cầu thực tế: khách
// mua gói ở 1 đối tác thì dùng được cho bất kỳ sân nào của đối tác đó, không phải mua riêng từng
// sân. product_id chuyển thành NULLABLE (trước đây bắt buộc) — 1 gói giờ đây thuộc ĐÚNG 1 TRONG 2
// phạm vi: product_id (gói riêng 1 sân, hành vi cũ không đổi) HOẶC category_id (gói cả chi nhánh),
// ràng buộc "chỉ 1 trong 2" xử lý ở tầng ứng dụng (App\Models\PackagePlan), không đặt CHECK
// constraint DB vì MySQL cần 8.0.16+ mới hỗ trợ đầy đủ, không chắc khớp mọi môi trường deploy.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_plans', function (Blueprint $table) {
            $table->char('product_id', 36)->nullable()->change();
            $table->unsignedBigInteger('category_id')->nullable()->after('product_id');
            $table->index('category_id');
        });

        Schema::table('customer_packages', function (Blueprint $table) {
            $table->char('product_id', 36)->nullable()->change();
            $table->unsignedBigInteger('category_id')->nullable()->after('product_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('package_plans', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
            $table->char('product_id', 36)->nullable(false)->change();
        });

        Schema::table('customer_packages', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
            $table->char('product_id', 36)->nullable(false)->change();
        });
    }
};
