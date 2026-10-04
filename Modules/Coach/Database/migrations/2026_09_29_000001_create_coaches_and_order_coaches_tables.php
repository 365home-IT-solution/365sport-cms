<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->uuid('partner_id')->nullable();
            $table->foreign('partner_id')->references('id')->on('partners')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('photo')->nullable();
            $table->string('specialties')->nullable();
            $table->text('certifications')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedTinyInteger('years_experience')->default(0);
            $table->unsignedBigInteger('hourly_rate');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['category_id', 'is_active']);
        });

        Schema::create('order_coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches')->restrictOnDelete();
            $table->string('coach_name');
            $table->unsignedBigInteger('hourly_rate');
            $table->unsignedInteger('minutes');
            $table->unsignedBigInteger('subtotal');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
            $table->index(['coach_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_coaches');
        Schema::dropIfExists('coaches');
    }
};
