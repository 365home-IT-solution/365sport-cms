<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Thêm lớp phân theo CHI NHÁNH bên trên lớp đối tác sẵn có (partner_id) — 1 đối tác nhiều chi
// nhánh trước đây gộp chung tồn kho, giờ tách riêng theo branch_id (trỏ tới category gốc,
// category_type='product', parent_id NULL — cùng nguồn "chi nhánh" dùng chung toàn hệ thống qua
// User::rootProductCategoryIds()). CHỈ thêm vào 4 bảng nghiệp vụ có phát sinh tồn kho thật
// (items, stock_ins, stock_outs, stock_checks) — KHÔNG thêm vào warehouse_categories/
// warehouse_units vì đó là danh mục (nhóm vật tư/đơn vị tính) dùng chung cho MỌI chi nhánh của
// 1 đối tác, tách theo chi nhánh sẽ buộc phải tạo trùng lặp danh mục ở mỗi chi nhánh.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['warehouse_items', 'warehouse_stock_ins', 'warehouse_stock_outs', 'warehouse_stock_checks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('partner_id')->constrained('categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['warehouse_items', 'warehouse_stock_ins', 'warehouse_stock_outs', 'warehouse_stock_checks'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
