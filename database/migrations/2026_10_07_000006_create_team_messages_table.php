<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Chat nhóm: thành viên đã duyệt (và trưởng nhóm) nhắn tin trong nhóm. Chỉ lưu văn bản, đọc theo con trỏ id (before_id/after_id).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->char('customer_id', 36);
            $table->text('body');
            $table->timestamps();

            $table->index(['team_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_messages');
    }
};
