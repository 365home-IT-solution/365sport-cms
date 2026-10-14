<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chấm công + chốt bảng lương tháng của nhân viên (Modules\Employee).
//  - employee_attendances: mỗi nhân viên một dòng cho mỗi ngày làm việc (giờ vào/ra, trạng thái). Nhân viên tự chấm công qua API hoặc
//    quản lý nhập tay.
//  - payroll_periods: một kỳ lương theo tháng của một đối tác. 'closed' = đã chốt, các phiếu lương bên dưới là ẢNH CHỤP số liệu lúc
//    chốt nên không đổi khi sau này thay mẫu lương/phụ cấp.
//  - payroll_slips: phiếu lương từng nhân viên trong kỳ, kèm chi tiết (breakdown) để đối chiếu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attendances', function (Blueprint $table) {
            $table->id();
            $table->char('partner_id', 36)->nullable()->index();
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->dateTime('check_in_at')->nullable();
            $table->dateTime('check_out_at')->nullable();
            // present = đi làm đủ; late = đi muộn (vẫn tính công); absent = nghỉ không phép (trừ lương); leave = nghỉ phép có lương; day_off = ngày nghỉ theo lịch
            $table->enum('status', ['present', 'late', 'absent', 'leave', 'day_off'])->default('present');
            $table->unsignedSmallInteger('worked_minutes')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('source', 20)->default('manual')->comment('manual = quản lý nhập; self = nhân viên tự chấm công');
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['partner_id', 'work_date']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->char('partner_id', 36)->nullable()->index();
            $table->char('month', 7)->comment('YYYY-MM');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedInteger('employee_count')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->char('closed_by', 36)->nullable();
            $table->timestamps();

            $table->unique(['partner_id', 'month']);
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->string('employee_name');
            $table->string('employee_code', 50)->nullable();
            $table->bigInteger('base_amount')->default(0);
            $table->bigInteger('piece_rate_amount')->default(0);
            $table->bigInteger('allowances_total')->default(0);
            $table->bigInteger('deductions_total')->default(0);
            $table->bigInteger('absence_deduction')->default(0);
            $table->unsignedSmallInteger('days_present')->default(0);
            $table->unsignedSmallInteger('days_absent')->default(0);
            $table->unsignedSmallInteger('days_leave')->default(0);
            $table->bigInteger('final_amount')->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_attendances');
    }
};
