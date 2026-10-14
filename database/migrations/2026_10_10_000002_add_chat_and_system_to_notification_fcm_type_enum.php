<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Mã nguồn đã gửi thông báo với type 'chat' (trả lời chat của khách), 'order_extra_charge' (khoản phát sinh của đơn) và 'system' (sự kiện hệ thống như đánh giá trình độ),
// nhưng cột enum chưa có các giá trị này → insert lỗi "Data truncated" và khách không nhận được thông báo.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_fcm')) {
            return;
        }

        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE {$prefix}notification_fcm MODIFY COLUMN type ENUM('manual','booking','checkin_reminder','checkout_warning','chat','system','order_extra_charge') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_fcm')) {
            return;
        }

        $prefix = DB::getTablePrefix();
        DB::statement("UPDATE {$prefix}notification_fcm SET type = 'manual' WHERE type IN ('chat','system','order_extra_charge')");
        DB::statement("ALTER TABLE {$prefix}notification_fcm MODIFY COLUMN type ENUM('manual','booking','checkin_reminder','checkout_warning') NOT NULL DEFAULT 'manual'");
    }
};
