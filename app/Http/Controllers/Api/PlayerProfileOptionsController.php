<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OpenMatch;
use Illuminate\Http\JsonResponse;
use Modules\Product\App\Models\RoomType;

// GET /api/v1/player-profile-options — các lựa chọn hợp lệ cho hồ sơ người chơi (POST /api/auth/me): môn thể
// thao (theo loại sân đang hoạt động), trình độ, mục tiêu, tần suất chơi. Công khai, app gọi để dựng form thay
// vì tự cứng mã/nhãn — đổi tập giá trị chỉ cần sửa ở Customer/OpenMatch, app không phải cập nhật.
class PlayerProfileOptionsController extends Controller
{
    private const SKILL_LABELS = [
        'beginner'     => 'Mới chơi',
        'intermediate' => 'Trung bình',
        'advanced'     => 'Khá',
        'pro'          => 'Chuyên nghiệp',
    ];

    public function __invoke(): JsonResponse
    {
        $sports = RoomType::query()
            ->where('is_active', true)
            ->whereNotNull('sport_type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['name', 'sport_type'])
            ->unique('sport_type')
            ->map(fn ($rt) => ['sport_type' => $rt->sport_type, 'name' => $rt->name])
            ->values();

        $options = fn (array $map) => collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values();

        return response()->json([
            'sports'            => $sports,
            'skill_levels'      => $options(array_intersect_key(self::SKILL_LABELS, array_flip(OpenMatch::SKILL_LEVELS))),
            'goals'             => $options(Customer::PLAY_GOALS),
            'play_frequencies'  => $options(Customer::PLAY_FREQUENCIES),
        ]);
    }
}
