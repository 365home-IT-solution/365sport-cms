<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // NULL = đối tác chưa được gán gói nào — Partner::hasFeature()/branchLimit() coi NULL
            // là "không giới hạn" để không đột ngột khoá tính năng của các đối tác đã tồn tại từ
            // trước khi có hệ thống gói này.
            $table->foreignId('partner_plan_id')->nullable()->after('is_platform_partner')
                ->constrained('partner_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_plan_id');
        });
    }
};
