<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mở rộng phạm vi gói nhiều buổi thêm 1 lựa chọn thứ 3: "một số sân cụ thể trong 1 chi nhánh" —
// trước đây chỉ có ĐÚNG 1 TRONG 2 (product_id = 1 sân duy nhất, HOẶC category_id không kèm gì thêm
// = TOÀN BỘ sân trong chi nhánh), không có cách chọn 1 tập con (vd chi nhánh có 3 sân, chỉ bán gói
// cho sân 1 và 3, không bán cho sân 2).
//
// product_ids (JSON, mảng id sân) LUÔN đi CÙNG category_id (không dùng độc lập với product_id đơn):
//   - category_id có giá trị, product_ids NULL/rỗng → TOÀN BỘ chi nhánh (hành vi cũ, KHÔNG đổi —
//     dữ liệu cũ toàn NULL nên tự động giữ đúng nghĩa cũ, không cần backfill).
//   - category_id có giá trị, product_ids có danh sách → CHỈ những sân trong danh sách đó.
//   - product_id có giá trị (product_ids/category_id đều NULL) → 1 sân cụ thể (hành vi cũ,
//     KHÔNG đổi, giữ nguyên cột/luồng cũ để không phải sửa lại toàn bộ nơi đang dùng product_id).
//
// customer_packages CŨNG cần cột này — CustomerPackage::activateFromOrder() snapshot NGUYÊN VẸN
// tập sân được phép dùng tại THỜI ĐIỂM MUA (đề phòng sau này admin sửa lại PackagePlan, gói khách
// đã mua không bị đổi phạm vi theo sau lưng).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_plans', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('category_id');
        });

        Schema::table('customer_packages', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('package_plans', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });

        Schema::table('customer_packages', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });
    }
};
