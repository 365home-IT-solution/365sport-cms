<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlayerSkillLevel;
use App\Models\PlayerSkillLevelLog;
use App\Services\PlayerSkillService;
use App\Support\SkillLevels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\App\Models\RoomType;

// Trình độ người chơi của chính khách đăng nhập: tự khai theo từng môn, xin nhân viên sân đánh giá lại, xem lịch sử.
class PlayerSkillController extends Controller
{
    public function __construct(private readonly PlayerSkillService $service) {}

    // GET /api/player-skills — trình độ của tôi + thang trình độ + danh sách môn hợp lệ
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'skills' => $this->service->presentFor($request->user()),
            'levels' => SkillLevels::options(),
            'sports' => $this->sports(),
        ]]);
    }

    // PUT /api/player-skills/{sport} — body: level (beginner|intermediate|advanced|pro), note?
    public function update(Request $request, string $sport): JsonResponse
    {
        if ($this->validSport($sport) === null) {
            return response()->json(['message' => 'Unknown sport type.'], 404);
        }

        $data = $request->validate([
            'level' => ['required', 'string', 'in:' . implode(',', SkillLevels::keys())],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $row = $this->service->set($request->user(), $sport, $data['level'], PlayerSkillLevel::SOURCE_SELF, null, $data['note'] ?? null);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => 'This level was confirmed by venue staff. Request a re-assessment instead of changing it yourself.',
                'code'    => 'skill_level_locked',
            ], 422);
        }

        return response()->json(['message' => 'Skill level saved.', 'data' => PlayerSkillService::present($row)]);
    }

    // DELETE /api/player-skills/{sport} — bỏ trình độ tự khai của một môn
    public function destroy(Request $request, string $sport): JsonResponse
    {
        $row = PlayerSkillLevel::where('customer_id', $request->user()->id)->where('sport_type', $sport)->first();
        if (! $row) {
            return response()->json(['message' => 'Skill level not found.'], 404);
        }

        try {
            $this->service->set($request->user(), $sport, null, PlayerSkillLevel::SOURCE_SELF);
        } catch (\DomainException $e) {
            return response()->json(['message' => 'This level was confirmed by venue staff and cannot be removed.', 'code' => 'skill_level_locked'], 422);
        }

        return response()->json(['message' => 'Skill level removed.']);
    }

    // POST /api/player-skills/{sport}/request-assessment — xin nhân viên sân đánh giá / xác nhận trình độ
    public function requestAssessment(Request $request, string $sport): JsonResponse
    {
        $row = $this->service->requestAssessment($request->user(), $sport);
        if (! $row) {
            return response()->json(['message' => 'Declare your skill level for this sport first.'], 404);
        }

        return response()->json(['message' => 'Assessment requested.', 'data' => PlayerSkillService::present($row)]);
    }

    // GET /api/player-skills/history — lịch sử thay đổi trình độ của tôi (mới nhất trước)
    public function history(Request $request): JsonResponse
    {
        $rows = PlayerSkillLevelLog::where('customer_id', $request->user()->id)->orderByDesc('id')->limit(100)->get()
            ->map(fn ($l) => [
                'sport_type' => $l->sport_type, 'from_level' => $l->from_level, 'to_level' => $l->to_level,
                'to_level_label' => SkillLevels::label($l->to_level), 'source' => $l->source, 'note' => $l->note,
                'created_at' => $l->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** @return list<array{sport_type:string,name:string}> */
    private function sports(): array
    {
        $rows = RoomType::query()->where('is_active', true)->whereNotNull('sport_type')->orderBy('sort_order')->orderBy('name')
            ->get(['name', 'sport_type'])->unique('sport_type');

        return array_merge(
            [['sport_type' => SkillLevels::GENERAL, 'name' => 'Trình độ chung']],
            $rows->map(fn ($rt) => ['sport_type' => $rt->sport_type, 'name' => $rt->name])->values()->all(),
        );
    }

    private function validSport(string $sport): ?string
    {
        foreach ($this->sports() as $s) {
            if ($s['sport_type'] === $sport) {
                return $sport;
            }
        }

        return null;
    }
}
