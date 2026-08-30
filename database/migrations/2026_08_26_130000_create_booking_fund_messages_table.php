<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chat nhóm giữa trưởng nhóm + thành viên đã duyệt của 1 quỹ (App\Models\BookingFundMessage) —
// realtime qua websocket/server.js kênh "fundchat:{id}" (xem App\Services\
// BookingFundChatRealtimeService), CÙNG hạ tầng Socket.IO đã dùng cho chat hỗ trợ khách hàng
// (ChatMessage) nhưng TÁCH BẢNG riêng vì đây là chat NHIỀU người (N thành viên), khác chat 1-1
// khách↔admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_fund_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_fund_id')->constrained('booking_funds')->cascadeOnDelete();
            $table->char('customer_id', 36);
            $table->text('body');
            $table->timestamps();

            $table->index('booking_fund_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_fund_messages');
    }
};
