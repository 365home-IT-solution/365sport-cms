<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->unique()->after('email_verified_at');
            $table->string('referred_by_customer_id')->nullable()->after('referral_code');
            $table->timestamp('referrer_rewarded_at')->nullable()->after('referred_by_customer_id');

            $table->foreign('referred_by_customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['referred_by_customer_id']);
            $table->dropColumn(['referral_code', 'referred_by_customer_id', 'referrer_rewarded_at']);
        });
    }
};
