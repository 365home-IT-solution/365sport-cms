<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Category\Entities\Category;
use Modules\Coach\App\Models\Coach;
use Modules\Product\App\Models\Product;
use Modules\Product\App\Models\RoomType;
use RuntimeException;

/**
 * Dữ liệu demo cho huấn luyện viên theo chi nhánh (hiện ở bước xác nhận đặt sân) và vài tài khoản
 * khách để thử các luồng khách hàng (nhóm, quỹ, tìm bạn chơi...).
 *
 * Chạy lại an toàn: tài khoản/HLV được update theo phone/slug.
 *
 * Chạy: php artisan db:seed --class=CoachDemoSeeder
 */
class CoachDemoSeeder extends Seeder
{
    public const PASSWORD = 'Test@12345';

    private const CUSTOMERS = [
        ['phone' => '84900000099', 'phone_ui' => '0900000099', 'name' => 'Khách Test', 'email' => 'khach.test@365sport.test'],
        ['phone' => '84900000101', 'phone_ui' => '0900000101', 'name' => 'An Demo', 'email' => 'an.demo@365sport.test'],
        ['phone' => '84900000102', 'phone_ui' => '0900000102', 'name' => 'Bình Demo', 'email' => 'binh.demo@365sport.test'],
        ['phone' => '84900000103', 'phone_ui' => '0900000103', 'name' => 'Chi Demo', 'email' => 'chi.demo@365sport.test'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $partner = Partner::query()->orderBy('created_at')->first();
            if (! $partner) {
                throw new RuntimeException('Chưa có đối tác. Hãy chạy PartnerSeeder trước CoachDemoSeeder.');
            }

            // Ưu tiên đúng chi nhánh đang dùng để test luồng đặt sân trên client; nếu dự án khác
            // không có slug này thì chọn một chi nhánh thật đang có sân.
            $category = Category::query()
                ->where('slug', 'mamble-sport')
                ->where('category_type', 'product')
                ->where('status', true)
                ->first()
                ?? Category::query()
                    ->where('category_type', 'product')
                    ->where('status', true)
                    ->whereHas('products')
                    ->orderBy('id')
                    ->first();

            if (! $category) {
                $category = Category::create([
                    'name' => '[DEMO] Chi nhánh thể thao',
                    'slug' => 'ranking-coach-demo-branch',
                    'description' => 'Chi nhánh dùng để kiểm thử thuê huấn luyện viên.',
                    'category_type' => 'product',
                    'status' => true,
                    'partner_id' => $partner->id,
                    'checkin_time' => '06:00:00',
                    'checkout_time' => '22:00:00',
                ]);
            }

            // Dùng loại sân Cầu lông có sẵn (PartnerSeeder); chỉ tạo loại riêng khi chưa có.
            $badminton = RoomType::where('sport_type', 'badminton')->first() ?? RoomType::updateOrCreate(
                ['slug' => 'demo-cau-long'],
                ['name' => 'Cầu lông (Demo)', 'sport_type' => 'badminton', 'surface_type' => 'synthetic', 'is_outdoor' => false, 'is_active' => true, 'sort_order' => 90]
            );
            $customers = collect(self::CUSTOMERS)->mapWithKeys(function (array $demo): array {
                $customer = Customer::updateOrCreate(
                    ['phone' => $demo['phone']],
                    [
                        'fullname' => $demo['name'],
                        'email' => $demo['email'],
                        'email_verified_at' => now(),
                        'date_of_birth' => '2000-01-01',
                        'phone_verified_at' => now(),
                        'status' => Customer::STATUS_ACTIVE,
                        'password' => self::PASSWORD,
                    ]
                );

                return [$demo['phone'] => $customer];
            });

            // Sân demo cũ (từng dùng để thử báo kết quả/ELO — tính năng đã bỏ): dọn đi nếu còn sót.
            $legacyCourt = Product::query()->where('slug', 'ranking-coach-demo-court')->first();
            if ($legacyCourt) {
                $legacyCourt->categories()->detach();
                $legacyCourt->delete();
            }

            $this->createCoach(
                $partner->id,
                $category->id,
                'demo-hlv-minh-cau-long',
                '[DEMO] HLV Minh Cầu Lông',
                300000,
                'Kỹ thuật cầu lông, sửa động tác, chiến thuật đánh đôi',
                'Chứng chỉ HLV cầu lông cấp quốc gia',
                8
            );
            $this->createCoach(
                $partner->id,
                $category->id,
                'demo-hlv-lan-pickleball',
                '[DEMO] HLV Lan Pickleball',
                250000,
                'Pickleball nhập môn, dink, volley và chiến thuật thi đấu',
                'PPR Certified Coach',
                5
            );
        });

        $this->command?->newLine();
        $this->command?->info('CoachDemoSeeder đã sẵn sàng. Mật khẩu chung: ' . self::PASSWORD);
        foreach (self::CUSTOMERS as $demo) {
            $this->command?->line("- {$demo['name']}: {$demo['phone_ui']}");
        }
        $this->command?->line('- HLV chỉ hiển thị tại bước xác nhận đặt sân của đúng chi nhánh.');
    }

    private function createCoach(
        int|string $partnerId,
        int|string $categoryId,
        string $slug,
        string $name,
        int $price,
        string $specialties,
        string $certifications,
        int $experience
    ): Coach {
        // Dọn dữ liệu demo theo thiết kế cũ (HLV từng bị lưu nhầm như một Product/sân).
        $legacy = Product::query()->where('slug', $slug)->where('type', 'coach')->first();
        if ($legacy) {
            $legacy->categories()->detach();
            $legacy->delete();
        }

        return Coach::updateOrCreate(
            ['slug' => $slug],
            [
                'partner_id' => $partnerId,
                'category_id' => $categoryId,
                'name' => $name,
                'hourly_rate' => $price,
                'specialties' => $specialties,
                'certifications' => $certifications,
                'bio' => "{$name} nhận dạy người mới và người chơi muốn nâng trình.",
                'years_experience' => $experience,
                'is_active' => true,
            ]
        );
    }
}
