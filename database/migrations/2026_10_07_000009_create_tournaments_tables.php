<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Giải đấu do đối tác tổ chức: các đội (App\Models\Team) đăng ký, đối tác duyệt + ghi nhận đã thu lệ phí, rồi xếp trận và nhập tỉ số.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->char('partner_id', 36)->nullable()->index();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('banner', 500)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('registration_deadline');
            $table->unsignedSmallInteger('max_teams')->default(16);
            $table->unsignedInteger('entry_fee')->default(0);
            $table->string('prize_info', 500)->nullable();
            $table->enum('status', ['draft', 'open', 'ongoing', 'finished', 'cancelled'])->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('tournament_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->char('registered_by', 36)->nullable()->comment('Customer đăng ký (trưởng nhóm).');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('fee_paid')->default(false);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'team_id']);
        });

        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->string('round', 50)->nullable();
            $table->foreignId('team_a_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('team_b_id')->constrained('teams')->cascadeOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('venue', 255)->nullable();
            $table->unsignedSmallInteger('score_a')->nullable();
            $table->unsignedSmallInteger('score_b')->nullable();
            $table->enum('status', ['scheduled', 'finished'])->default('scheduled');
            $table->timestamps();

            $table->index(['tournament_id', 'scheduled_at']);
        });

        foreach (['view_any', 'view', 'create', 'update', 'delete', 'delete_any'] as $action) {
            $permission = Permission::firstOrCreate(['name' => $action . '_tournament', 'guard_name' => 'web']);
            foreach (Role::where('guard_name', 'web')->get() as $role) {
                $isPartner = $role->name === 'partner';
                $isStaff = $role->name === 'employee' || str_starts_with($role->name, 'Nhân viên - ');
                // Nhân viên chỉ xem + sửa (nhập tỉ số, duyệt đội); tạo/xoá giải là việc của chủ đối tác.
                $allowed = $isPartner || ($isStaff && in_array($action, ['view_any', 'view', 'update'], true));
                if ($allowed && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_teams');
        Schema::dropIfExists('tournaments');
    }
};
