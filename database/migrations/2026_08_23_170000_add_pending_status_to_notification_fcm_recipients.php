<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Gửi FCM giờ chạy qua queue (App\Jobs\SendFcmPushJob) thay vì đồng bộ — bản ghi
// NotificationFcmRecipient được tạo TRƯỚC khi job thật sự gửi xong, nên cần trạng thái trung gian
// 'pending' (trước đây chỉ có 'sent'/'failed', ENUM không đủ giá trị cho luồng bất đồng bộ).
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notification_fcm_recipients', 'status')) {
            $table = DB::getTablePrefix() . 'notification_fcm_recipients';
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM('pending', 'sent', 'failed') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('notification_fcm_recipients', 'status')) {
            $table = DB::getTablePrefix() . 'notification_fcm_recipients';
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM('sent', 'failed') DEFAULT 'sent'");
        }
    }
};
