<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Cho phép ADMIN quản lý "khách đã mua gói" theo đúng đối tác (App\Filament\Resources\
// CustomerPackageResource, dùng chung App\Models\Concerns\BelongsToPartner như các resource
// nghiệp vụ khác — super_admin xem toàn bộ, đối tác chỉ xem đúng khách đã mua gói của mình).
// customer_packages TRƯỚC ĐÂY không có partner_id (chỉ suy gián tiếp qua package_plan_id ->
// package_plans.partner_id, hoặc qua product_id/category_id -> Product/Category.partner_id) —
// thêm cột trực tiếp để BelongsToPartner lọc được bằng 1 câu WHERE đơn giản, KHÔNG phải join.
// Backfill từ package_plans.partner_id cho các gói đã bán trước đây (partner_id trên plan luôn có
// giá trị — CreatePackagePlan::mutateFormDataBeforeCreate() suy từ product ngay lúc tạo plan).
return new class extends Migration
{
    public function up(): void
    {
        // hasColumn guard — DB này dùng table PREFIX (cms_), lần chạy đầu Schema::table() (tự nhận
        // biết prefix) đã thêm cột thành công nhưng bước UPDATE raw SQL bên dưới lại fail (raw SQL
        // gõ tay KHÔNG tự thêm prefix như query builder), khiến migration không được ghi nhận
        // "Ran" — chạy lại lần 2 sẽ ADD COLUMN trùng nếu không có guard này.
        if (! Schema::hasColumn('customer_packages', 'partner_id')) {
            Schema::table('customer_packages', function (Blueprint $table) {
                $table->char('partner_id', 36)->nullable()->after('package_plan_id');
                $table->index('partner_id');
            });
        }

        // Dùng query builder (DB::table()) thay vì raw SQL literal tên bảng — query builder tự
        // thêm đúng table prefix của kết nối hiện tại cho TÊN BẢNG THẬT, raw SQL thì không (kể cả
        // alias "as" cũng bị viết đè thành "cms_xxx" bởi query builder, chỉ riêng chuỗi bên trong
        // DB::raw() là không — phải tự nối prefix thủ công cho phần đó).
        $prefix = DB::getTablePrefix();

        DB::table('customer_packages')
            ->join('package_plans', 'package_plans.id', '=', 'customer_packages.package_plan_id')
            ->whereNull('customer_packages.partner_id')
            ->update(['customer_packages.partner_id' => DB::raw("`{$prefix}package_plans`.`partner_id`")]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_packages', 'partner_id')) {
            Schema::table('customer_packages', function (Blueprint $table) {
                $table->dropIndex(['partner_id']);
                $table->dropColumn('partner_id');
            });
        }
    }
};
