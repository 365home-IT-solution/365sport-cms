<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Yêu cầu xuất hoá đơn VAT: khách gửi thông tin doanh nghiệp cho đơn đã thanh toán, đối tác xuất hoá đơn ở phần mềm hoá đơn của họ rồi
// nhập số hoá đơn + tải file lên đây để khách nhận. Hệ thống không tự phát hành hoá đơn điện tử.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_requests', function (Blueprint $table) {
            $table->id();
            $table->char('order_id', 36)->unique()->comment('Mỗi đơn chỉ có 1 yêu cầu hoá đơn.');
            $table->char('customer_id', 36)->nullable()->index();
            $table->char('partner_id', 36)->nullable()->index();
            $table->string('company_name', 255);
            $table->string('tax_code', 20);
            $table->string('address', 500);
            $table->string('email', 255);
            $table->string('note', 500)->nullable();
            $table->enum('status', ['pending', 'issued', 'rejected'])->default('pending')->index();
            $table->string('invoice_number', 50)->nullable();
            $table->string('invoice_file', 500)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->char('handled_by', 36)->nullable();
            $table->timestamps();
        });

        foreach (['view_any', 'view', 'update'] as $action) {
            $permission = Permission::firstOrCreate(['name' => $action . '_invoice::request', 'guard_name' => 'web']);
            foreach (Role::where('guard_name', 'web')->get() as $role) {
                if (($role->name === 'partner' || $role->name === 'employee' || str_starts_with($role->name, 'Nhân viên - ')) && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_requests');
    }
};
