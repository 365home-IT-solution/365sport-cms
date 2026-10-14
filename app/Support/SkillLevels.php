<?php

declare(strict_types=1);

namespace App\Support;

// Thang trình độ người chơi dùng chung toàn hệ thống (khớp OpenMatch::SKILL_LEVELS). Thứ tự = từ thấp đến cao.
final class SkillLevels
{
    public const GENERAL = 'general';

    public const MAP = [
        'beginner'     => ['label' => 'Mới chơi',       'rank' => 1, 'description' => 'Mới làm quen, chơi để giải trí, chưa nắm vững luật và kỹ thuật cơ bản.'],
        'intermediate' => ['label' => 'Trung bình',     'rank' => 2, 'description' => 'Nắm luật và kỹ thuật cơ bản, chơi đều tay, đã tham gia các buổi giao lưu.'],
        'advanced'     => ['label' => 'Khá',            'rank' => 3, 'description' => 'Kỹ thuật tốt, chiến thuật rõ ràng, thường xuyên thi đấu giao hữu / phong trào.'],
        'pro'          => ['label' => 'Chuyên nghiệp',  'rank' => 4, 'description' => 'Thi đấu giải, có huấn luyện bài bản hoặc đã từng thi đấu chuyên nghiệp.'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::MAP);
    }

    public static function label(?string $level): ?string
    {
        return $level !== null ? (self::MAP[$level]['label'] ?? $level) : null;
    }

    public static function rank(?string $level): int
    {
        return $level !== null ? (self::MAP[$level]['rank'] ?? 0) : 0;
    }

    /** @return list<array{value:string,label:string,rank:int,description:string}> */
    public static function options(): array
    {
        $out = [];
        foreach (self::MAP as $value => $m) {
            $out[] = ['value' => $value, 'label' => $m['label'], 'rank' => $m['rank'], 'description' => $m['description']];
        }

        return $out;
    }
}
