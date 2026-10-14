<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Khách KHÔNG còn tự huỷ đơn ngay — chỉ GỬI YÊU CẦU HUỶ, admin duyệt/từ chối (xem
// App\Services\OrderCancelRequestService). cancelled_at vẫn là nguồn sự thật duy nhất cho "đơn đã
// huỷ" — chỉ được set khi admin DUYỆT. Các cột dưới đây chỉ lưu dấu vết yêu cầu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancel_request_status', 20)->nullable()->after('cancel_reason')
                ->comment("'pending' | 'approved' | 'rejected'");
            $table->timestamp('cancel_requested_at')->nullable()->after('cancel_request_status');
            $table->text('cancel_request_reason')->nullable()->after('cancel_requested_at');
            $table->timestamp('cancel_request_reviewed_at')->nullable()->after('cancel_request_reason');
            $table->string('cancel_request_reviewed_by', 64)->nullable()->after('cancel_request_reviewed_at');
            $table->text('cancel_request_note')->nullable()->after('cancel_request_reviewed_by')
                ->comment('Ghi chú/lý do của admin khi duyệt hoặc từ chối');

            $table->index('cancel_request_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['cancel_request_status']);
            $table->dropColumn([
                'cancel_request_status',
                'cancel_requested_at',
                'cancel_request_reason',
                'cancel_request_reviewed_at',
                'cancel_request_reviewed_by',
                'cancel_request_note',
            ]);
        });
    }
};
