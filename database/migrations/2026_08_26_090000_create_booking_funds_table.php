<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Quỹ nhóm góp tiền đặt sân (App\Models\BookingFund) — trưởng nhóm tạo 1 quỹ với mục tiêu số
// tiền + STK ngân hàng CÁ NHÂN của mình, sinh mã QR (xem BookingFund::vietQrImageUrl(), copy đúng
// logic App\Models\Partner::vietQrImageUrl()) để thành viên chuyển khoản trực tiếp. TIỀN KHÔNG ĐI
// QUA HỆ THỐNG — bảng này chỉ theo dõi/minh bạch hoá việc góp quỹ, KHÔNG liên kết Order/PayOS. Khi
// quỹ đủ, trưởng nhóm tự đặt sân bình thường, ngoài phạm vi module này.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_funds', function (Blueprint $table) {
            $table->id();
            $table->char('leader_customer_id', 36)->comment('Trưởng nhóm — App\Models\Customer.id.');
            $table->string('invite_code', 8)->unique()->comment('Mã mời riêng tư — trưởng nhóm tự gửi cho bạn bè qua kênh ngoài app, KHÔNG có danh sách quỹ công khai.');
            $table->string('title');
            $table->text('note')->nullable();
            $table->unsignedInteger('target_amount');
            $table->string('bank_bin', 10)->comment('Mã BIN ngân hàng — xem config/vietnam_banks.php.');
            $table->string('bank_account_number');
            $table->string('bank_account_holder');
            $table->enum('status', ['collecting', 'closed'])->default('collecting');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('leader_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_funds');
    }
};
