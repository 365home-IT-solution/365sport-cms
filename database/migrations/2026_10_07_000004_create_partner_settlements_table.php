<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Đối soát hoa hồng nền tảng thu của từng đối tác theo tháng (App\Models\PartnerSettlement). Mỗi tháng nền tảng lập một bảng đối soát cho
// mỗi đối tác: doanh thu thực thu (đơn đã thanh toán/cọc) trừ hoàn tiền cho khách, nhân tỉ lệ hoa hồng trong hợp đồng (partners.commission_rate).
// pending = đã lập, chờ đối tác thanh toán hoa hồng; paid = nền tảng đã nhận. Đối tác chỉ XEM bảng của chính mình.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_settlements', function (Blueprint $table) {
            $table->id();
            $table->char('partner_id', 36)->index();
            $table->char('month', 7)->comment('YYYY-MM');
            $table->unsignedInteger('order_count')->default(0);
            $table->bigInteger('gross_revenue')->default(0)->comment('Tổng thực thu của các đơn trong tháng');
            $table->bigInteger('refunds')->default(0)->comment('Hoàn tiền cho khách của các đơn đã huỷ trong tháng');
            $table->bigInteger('net_revenue')->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->bigInteger('commission_amount')->default(0);
            $table->bigInteger('adjustment_amount')->default(0)->comment('Điều chỉnh tay (+/-) trước khi thu');
            $table->string('adjustment_note', 500)->nullable();
            $table->bigInteger('amount_due')->default(0)->comment('commission_amount + adjustment_amount');
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->unique(['partner_id', 'month']);
        });

        // Đối tác chỉ được xem đối soát của chính mình: cấp quyền xem cho role 'partner' và các role nhân viên đã sao chép.
        foreach (['view_any_partner::settlement', 'view_partner::settlement'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            foreach (Role::where('guard_name', 'web')->where('name', 'partner')->get() as $role) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_settlements');
    }
};
