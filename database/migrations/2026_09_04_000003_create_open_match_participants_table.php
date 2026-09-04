<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Danh sách khách đã xin ghép vào 1 Open Match (App\Models\OpenMatch). MVP: tự động join ngay khi
// gọi API (KHÔNG có hàng đợi chờ host duyệt — luồng duyệt thủ công để dành cho bản sau nếu cần,
// tránh tăng độ phức tạp ngay từ đầu). status='left' giữ lại lịch sử rời kèo thay vì xoá hẳn dòng,
// cùng triết lý "không xoá cứng, chỉ đổi trạng thái" như OrderItem/Order trong hệ thống.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_match_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('open_match_id');
            $table->char('customer_id', 36);
            $table->enum('status', ['joined', 'left'])->default('joined');
            $table->timestamps();

            $table->foreign('open_match_id')->references('id')->on('open_matches')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            // Chặn 1 khách join trùng 1 match nhiều lần ở tầng DB — kết hợp thêm kiểm tra tầng
            // ứng dụng (OpenMatchController::join()) để trả message rõ ràng thay vì lỗi SQL thô khi
            // khách join-leave-join lại (unique tính trên toàn bộ status, không chỉ 'joined', nên
            // rời kèo rồi join lại phải INSERT dòng mới — xem ghi chú tại OpenMatchController::join()).
            $table->unique(['open_match_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_match_participants');
    }
};
