<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lịch sử cảnh báo phát hiện người ngoài giờ mở cửa — do CameraAlertPollingService quét Frigate
// events định kỳ tạo ra (xem cameras.alert_enabled/alert_start_time/alert_end_time).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camera_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->constrained('cameras')->cascadeOnDelete();
            $table->string('frigate_event_id')->nullable();
            $table->timestamp('detected_at');
            $table->string('label')->default('person');
            $table->unsignedBigInteger('acknowledged_by')->nullable()->comment('Employee.id đã xác nhận kiểm tra.');
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('acknowledged_by')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['camera_id', 'frigate_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camera_alerts');
    }
};
