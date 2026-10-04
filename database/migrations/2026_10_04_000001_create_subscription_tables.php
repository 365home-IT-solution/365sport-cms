<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Luồng MUA GÓI của đối tác (port từ minihouse): gói = PartnerPlan (giá/tháng, tính năng, số chi nhánh) bổ sung kỳ hạn/
// dùng thử/giảm giá theo kỳ + giá bán thêm trang landing subdomain; mỗi đối tác đúng 1 dòng partner_subscriptions;
// mỗi lần thanh toán phí là 1 dòng subscription_payments (mã giao dịch SBxxxxxx, link/QR PayOS).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('period_months')->default(1)->after('price')->comment('Số tháng của 1 kỳ giá (price = giá mỗi kỳ).');
            $table->unsignedSmallInteger('trial_months')->default(0)->after('period_months')->comment('Số tháng dùng thử riêng của gói; 0 = theo cấu hình chung.');
            $table->json('period_discounts')->nullable()->after('trial_months')->comment('% giảm theo số kỳ mua, vd {"3":5,"12":15}.');
            $table->unsignedInteger('landing_addon_price')->nullable()->after('period_discounts')->comment('Giá/kỳ bán thêm trang landing subdomain; null = chưa bán.');
            $table->boolean('is_default')->default(false)->after('is_highlighted');
        });

        Schema::create('partner_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('partner_id')->unique()->constrained('partners')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('partner_plans')->restrictOnDelete();
            $table->string('status', 20)->default('active'); // trial|active|cancelled — hết hạn tính động theo expires_at
            $table->boolean('is_trial')->default(false);
            $table->boolean('landing_addon')->default(false);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->json('reminders_sent')->nullable();
            $table->dateTime('renewal_link_sent_at')->nullable();
            $table->dateTime('expired_notified_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('partner_plans')->restrictOnDelete();
            $table->string('transaction_code', 20)->unique();
            $table->unsignedBigInteger('amount_vnd')->default(0);
            $table->unsignedSmallInteger('months');
            $table->boolean('landing_addon')->default(false);
            $table->string('status', 20)->default('pending')->index();
            $table->string('source', 20)->default('payos'); // payos|request|auto_renew
            $table->boolean('is_renewal')->default(false);
            $table->unsignedBigInteger('payos_order_code')->nullable()->unique();
            $table->string('payos_payment_link_id')->nullable();
            $table->text('payos_checkout_url')->nullable();
            $table->text('payos_qr_code')->nullable();
            $table->string('payos_bank_bin', 20)->nullable();
            $table->string('payos_account_number', 50)->nullable();
            $table->string('payos_account_name')->nullable();
            $table->dateTime('payos_expired_at')->nullable();
            $table->string('bank_reference', 120)->nullable()->index();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('extends_to')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->string('onboarding_token', 64)->nullable()->index()->comment('SHA-256 của mã đơn mua gói gửi cho khách.');
            $table->foreignId('signup_plan_id')->nullable()->constrained('partner_plans')->nullOnDelete();
            $table->unsignedSmallInteger('signup_periods')->nullable();
            $table->boolean('signup_landing_addon')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signup_plan_id');
            $table->dropColumn(['onboarding_token', 'signup_periods', 'signup_landing_addon']);
        });
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('partner_subscriptions');
        Schema::table('partner_plans', function (Blueprint $table) {
            $table->dropColumn(['period_months', 'trial_months', 'period_discounts', 'landing_addon_price', 'is_default']);
        });
    }
};
