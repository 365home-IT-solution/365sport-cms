<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Camera an ninh — giám sát trộm/khu vực sân bãi, KHÁC bản chất 365home-cms (camera phòng khách
// thuê dài hạn). Gắn theo branch_id (chi nhánh — Category gốc category_type='product'), không gắn
// theo từng sân riêng lẻ, vì camera an ninh giám sát 1 ĐỊA ĐIỂM VẬT LÝ (khu sân/bãi xe/kho dụng cụ
// của 1 cơ sở), không phải theo từng sân. go2rtc/Frigate là phần mềm NGOÀI dự án (server/NVR do
// người dùng tự cài, xem CameraSetting) — bảng này chỉ lưu cấu hình client kết nối tới đó.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cameras', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable()->comment('Chi nhánh — categories.id (category_type=product, parent_id null).');
            $table->string('name');
            $table->string('stream_key')->comment('Tên nguồn go2rtc dùng để build URL phát.');
            $table->string('frigate_camera_name')->nullable()->comment('Tên camera thật trong Frigate (khác stream_key nếu lệch tên) — dùng riêng cho API lịch sử ghi hình.');
            $table->text('rtsp_url')->nullable()->comment('Chỉ điền khi camera CHƯA có sẵn trong Frigate — hệ thống tự đẩy sang go2rtc qua API khi lưu.');
            $table->text('note')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('alert_enabled')->default(false)->comment('Bật cảnh báo phát hiện người ngoài khung giờ mở cửa.');
            $table->time('alert_start_time')->nullable()->comment('Bắt đầu khung giờ coi là "ngoài giờ mở cửa" để bật cảnh báo.');
            $table->time('alert_end_time')->nullable();
            $table->timestamp('alert_last_checked_at')->nullable()->comment('Mốc thời gian đã quét Frigate events lần cuối — tránh xử lý trùng.');
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('categories')->nullOnDelete();
            $table->unique(['branch_id', 'stream_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cameras');
    }
};
