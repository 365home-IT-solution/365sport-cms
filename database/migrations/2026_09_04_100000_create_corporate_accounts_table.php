<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tài khoản khách hàng doanh nghiệp (B2B) — App\Models\CorporateAccount. 1 công ty đăng ký với 1
// đối tác (chi nhánh/sân) CỤ THỂ, được cấp 1 hạn mức GIỜ SÂN/tháng dùng chung cho nhiều nhân viên
// (xem corporate_account_employees) — nhân viên đặt sân KHÔNG trả tiền từng lần, công ty nhận 1
// hoá đơn tổng hợp cuối kỳ (xem CorporateAccount::hasQuotaRemaining()/consumeHours() +
// Filament action "Xuất hoá đơn"/"Chốt kỳ"). partner_id KHÔNG dùng $table->foreign() (đồng bộ
// với customer_packages/package_plans — dự án này không đặt FK cứng cho các cột kiểu này, chỉ
// index) — partners.id là UUID (xem create_partners_table).
// softDeletes(): đây là thực thể có ý nghĩa billing/audit (giống Order/Customer) — xoá phải giữ
// lại lịch sử, không hard-delete.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_accounts', function (Blueprint $table) {
            $table->id();
            $table->char('partner_id', 36)->comment('Đối tác (chủ sân) mà công ty này đăng ký hợp tác — App\\Models\\Partner.id.');
            $table->string('company_name');
            $table->string('tax_code')->nullable()->comment('Mã số thuế doanh nghiệp — dùng để xuất hoá đơn.');
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->string('contact_email')->nullable();
            $table->text('billing_address')->nullable();
            $table->decimal('monthly_hour_quota', 8, 2)->comment('Số giờ sân được cấp mỗi kỳ (tháng).');
            $table->decimal('hours_used_current_cycle', 8, 2)->default(0)->comment('Số giờ đã dùng trong kỳ hiện tại — reset về 0 khi "Chốt kỳ" (xem CorporateAccount::closeCycle()).');
            $table->date('cycle_start_date')->comment('Ngày bắt đầu kỳ hiện tại — hoá đơn tính từ ngày này tới lúc xuất.');
            $table->string('status')->default('active')->comment('active|suspended — suspended thì chặn đặt sân qua tài khoản này (xem CorporateAccount::isActive()).');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('partner_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_accounts');
    }
};
