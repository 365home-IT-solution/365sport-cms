<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OPEN_HOUR  = 5;
    private const CLOSE_HOUR = 22;

    // "Bảng giá mẫu" — bộ khung giờ + giá có sẵn để đối tác chọn khi tạo sân / tạo bảng giá.
    // partner_id NULL = mẫu hệ thống dùng chung; đối tác sửa mẫu hệ thống thì tự tách thành bản riêng
    // (source_template_id trỏ về mẫu gốc). KHÁC price_boards (bảng giá THEO LỊCH, ghi đè giá sân trong
    // 1 khoảng ngày rồi khôi phục).
    public function up(): void
    {
        if (! Schema::hasTable('price_templates')) {
            Schema::create('price_templates', function (Blueprint $table) {
                $table->id();
                $table->uuid('partner_id')->nullable();
                $table->foreignId('source_template_id')->nullable()->constrained('price_templates')->nullOnDelete();
                $table->string('name');
                $table->text('note')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('partner_id')->references('id')->on('partners')->cascadeOnDelete();
                $table->index(['partner_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('price_template_slots')) {
            Schema::create('price_template_slots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('price_template_id')->constrained('price_templates')->cascadeOnDelete();
                $table->foreignId('timeslot_id')->constrained('time_slots')->restrictOnDelete();
                $table->unsignedBigInteger('price')->nullable();
                $table->string('checkin', 5)->nullable();
                $table->string('checkout', 5)->nullable();
                $table->boolean('over_night')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['price_template_id', 'timeslot_id']);
            });
        }

        $this->seedSystemTemplates();
    }

    /**
     * 2 bảng giá mẫu hệ thống: mỗi khung đúng 1 tiếng, từ 05:00 đến 22:00 (cùng bộ khung giờ với
     * SportVenueDemoSeeder). Khung giờ hệ thống chưa có thì tạo luôn (partner_id NULL).
     */
    private function seedSystemTemplates(): void
    {
        if (DB::table('price_templates')->whereNull('partner_id')->exists()) {
            return;
        }

        $now   = now();
        $slots = [];

        for ($h = self::OPEN_HOUR; $h < self::CLOSE_HOUR; $h++) {
            $start = sprintf('%02d:00:00', $h);
            $end   = sprintf('%02d:00:00', $h + 1);

            $id = DB::table('time_slots')
                ->whereNull('partner_id')
                ->where('start_time', $start)
                ->where('end_time', $end)
                ->where('over_night', false)
                ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'date'))
                ->value('id');

            $id ??= DB::table('time_slots')->insertGetId([
                'partner_id' => null, 'start_time' => $start, 'end_time' => $end, 'over_night' => false,
                'label' => sprintf('%02d:00 - %02d:00', $h, $h + 1), 'type' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $slots[$h] = $id;
        }

        // Giờ cao điểm 17:00–21:00 giá cao hơn giờ thường.
        $weekday = fn (int $hour) => ($hour >= 17 && $hour < 21) ? 200000 : 120000;

        $templates = [
            ['Giá ngày thường', 'Mỗi khung 1 tiếng, 05:00–22:00', 1, $weekday],
            ['Giá cuối tuần', 'Cao hơn ngày thường 20%', 2, fn (int $hour) => (int) round($weekday($hour) * 1.2, -4)],
        ];

        foreach ($templates as [$name, $note, $order, $price]) {
            $templateId = DB::table('price_templates')->insertGetId([
                'partner_id' => null, 'name' => $name, 'note' => $note, 'is_active' => true,
                'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($slots as $hour => $timeslotId) {
                DB::table('price_template_slots')->insert([
                    'price_template_id' => $templateId, 'timeslot_id' => $timeslotId, 'price' => $price($hour),
                    'over_night' => false, 'sort_order' => $hour - self::OPEN_HOUR,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_template_slots');
        Schema::dropIfExists('price_templates');
    }
};
