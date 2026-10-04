<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Nhóm (đội) của khách hàng (App\Models\Team) — khách tạo nhóm, người khác vào nhóm bằng MÃ NHÓM (xin tham gia,
// trưởng nhóm duyệt) hoặc được trưởng nhóm MỜI theo SỐ ĐIỆN THOẠI (người được mời đồng ý). Cùng khung với quỹ
// nhóm (booking_funds): trưởng nhóm ngầm định qua teams.leader_customer_id, KHÔNG có dòng trong team_members.
// Quỹ nhóm có thể gắn vào 1 nhóm (booking_funds.team_id) để lấy sẵn danh sách thành viên đã duyệt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->char('leader_customer_id', 36)->comment('Trưởng nhóm — App\Models\Customer.id.');
            $table->string('code', 8)->unique()->comment('Mã nhóm — chia sẻ để người khác xin tham gia.');
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->enum('status', ['active', 'disbanded'])->default('active');
            $table->timestamp('disbanded_at')->nullable();
            $table->timestamps();

            $table->index('leader_customer_id');
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->char('customer_id', 36);
            // pending  = khách xin tham gia bằng mã, chờ trưởng nhóm duyệt
            // invited  = trưởng nhóm mời theo SĐT, chờ người được mời đồng ý
            // approved = đã là thành viên
            // rejected = trưởng nhóm từ chối yêu cầu; declined = người được mời từ chối lời mời
            $table->enum('status', ['pending', 'invited', 'approved', 'rejected', 'declined'])->default('pending');
            $table->char('invited_by_customer_id', 36)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'customer_id']);
            $table->index(['customer_id', 'status']);
        });

        Schema::table('booking_funds', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('leader_customer_id')->constrained('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_funds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
