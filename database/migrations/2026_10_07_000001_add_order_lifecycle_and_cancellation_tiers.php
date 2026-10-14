<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Vòng đời đơn đầy đủ + chính sách huỷ theo mốc giờ:
//  - categories.cancellation_tiers: các mốc hoàn tiền khi KHÁCH tự huỷ, mỗi mốc {hours_before, refund_percent}.
//    Huỷ sớm hơn hoặc bằng hours_before giờ trước giờ chơi thì được hoàn refund_percent%. Rỗng = dùng mức mặc định 70%
//    (Order::CUSTOMER_CANCEL_REFUND_RATE) như trước đây, nên chi nhánh chưa cấu hình không đổi hành vi.
//  - orders.checked_in_by / checked_out_by: ai thao tác nhận/trả sân (id người dùng hoặc 'customer' / 'system').
//  - orders.rescheduled_count / rescheduled_at: số lần đổi giờ và lần gần nhất (giới hạn số lần khách tự đổi).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('cancellation_tiers')->nullable()->after('default_policy')
                ->comment('Mốc hoàn tiền khi khách tự huỷ: [{hours_before, refund_percent}]. Rỗng = mặc định 70%.');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('checked_in_by', 64)->nullable()->after('checked_in_at');
            $table->string('checked_out_by', 64)->nullable()->after('checked_out_at');
            $table->unsignedTinyInteger('rescheduled_count')->default(0)->after('no_show_marked_by');
            $table->timestamp('rescheduled_at')->nullable()->after('rescheduled_count');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['checked_in_by', 'checked_out_by', 'rescheduled_count', 'rescheduled_at']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('cancellation_tiers');
        });
    }
};
