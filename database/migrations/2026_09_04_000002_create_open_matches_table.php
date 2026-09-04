<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Tìm bạn chơi" (Open Match) — khách đã đặt sân (Order) nhưng thiếu người có thể mở đơn của mình
// cho khách khác xin ghép cùng chơi, chia tiền sân. product_id/category_id CỐ Ý denormalize từ
// order_items/orders (giống cách booking_funds denormalize slot_date/slot_start_time/slot_end_time
// ở migration add_slot_info_to_booking_funds_table) để lọc theo sân/chi nhánh KHÔNG cần join ngược
// qua orders->items mỗi lần liệt kê. price_per_player CHỈ mang tính hiển thị/tham khảo — bản MVP
// này KHÔNG ép buộc thanh toán, xem ghi chú tại App\Models\OpenMatch::maybeCreateBookingFund()
// (tự tạo App\Models\BookingFund khi đủ người VÀ host đã khai bank_bin/bank_account_number khi tạo
// match, để việc thu tiền đi qua đúng cơ chế BookingFund sẵn có, không dựng thêm hệ thống chia tiền
// riêng).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('Đơn đặt sân gốc — Modules\Payment\Entities\Order.');
            $table->char('host_customer_id', 36)->comment('Khách mở kèo — App\Models\Customer.id, phải là chủ đơn.');
            $table->char('product_id', 36)->nullable()->comment('Denormalize từ order_items — Modules\Product\App\Models\Product.');
            $table->unsignedBigInteger('category_id')->nullable()->comment('Chi nhánh — denormalize từ orders.category_id.');
            $table->date('play_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedTinyInteger('players_needed')->comment('Số người CẦN THÊM (không tính host).');
            $table->unsignedTinyInteger('players_joined')->default(0)->comment('Đếm dồn số người đã ghép — denormalize, xem OpenMatchParticipant.');
            $table->string('min_skill_level')->nullable();
            $table->string('max_skill_level')->nullable();
            $table->unsignedInteger('price_per_player')->nullable()->comment('Giá dự kiến/người — CHỈ hiển thị, không ràng buộc thanh toán.');
            $table->text('note')->nullable();
            $table->enum('status', ['open', 'full', 'cancelled', 'expired'])->default('open');
            // Bank cá nhân của host — TUỲ CHỌN, chỉ cần khi muốn tự động tạo BookingFund thu tiền
            // lúc đủ người (xem OpenMatch::maybeCreateBookingFund()). Để trống thì price_per_player
            // chỉ là thông tin tham khảo, khách tự thu xếp ngoài app.
            $table->string('bank_bin', 10)->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->unsignedBigInteger('booking_fund_id')->nullable()->comment('Quỹ tự tạo khi đủ người — App\Models\BookingFund, null nếu host không khai bank.');
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('host_customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unique('order_id', 'open_matches_order_id_unique');
            $table->index(['status', 'category_id', 'product_id', 'play_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_matches');
    }
};
