<?php

declare(strict_types=1);

namespace App\Services;

use Modules\Product\App\Models\TimeSlot;

/**
 * CÁCH RANDOM để tạo khung giờ + giá gợi ý cho bảng giá, đối tác không phải thêm và đặt giá từng khung
 * (cách còn lại là chọn trong bảng giá mẫu — PriceTemplateService). Dựng bộ khung giờ ngẫu nhiên gắn vào
 * time_slots của đối tác (dùng lại khung trùng giờ, chưa có thì tạo khung riêng).
 *
 * Quy tắc random (sân thể thao thuê theo giờ):
 *  - mặc định NHIỀU khung (DEFAULT_COUNT), mỗi khung ĐÚNG 1 TIẾNG (SLOT_MINUTES), tròn giờ;
 *  - các khung nối tiếp nhau, không hở: 07:00–08:00, 08:00–09:00, …;
 *  - giờ bắt đầu khung đầu tiên ngẫu nhiên sao cho cả bộ nằm gọn trong giờ hoạt động
 *    (OPEN_HOUR–CLOSE_HOUR);
 *  - không có khung qua đêm.
 */
class RoomSlotRandomizer
{
    public const MIN_COUNT     = 1;
    public const MAX_COUNT     = 17; // kín giờ hoạt động 05:00–22:00
    public const DEFAULT_COUNT = 8;

    public const SLOT_MINUTES = 60;
    public const OPEN_HOUR    = 5;
    public const CLOSE_HOUR   = 22;

    /**
     * Dựng lịch $count khung giờ 1 tiếng (chưa ghi DB).
     *
     * @return list<array{start:string,end:string,over_night:bool}> theo thứ tự thời gian, "H:i"
     */
    public function schedule(int $count): array
    {
        $count = max(self::MIN_COUNT, min(self::MAX_COUNT, $count));

        $first = random_int(self::OPEN_HOUR, self::CLOSE_HOUR - $count);
        $slots = [];

        for ($hour = $first; $hour < $first + $count; $hour++) {
            $slots[] = [
                'start'      => $this->clock($hour * 60),
                'end'        => $this->clock(($hour + 1) * 60),
                'over_night' => false,
            ];
        }

        return $slots;
    }

    /**
     * RANDOM: lịch $count khung giờ 1 tiếng + giá ngẫu nhiên, đã gắn với time_slots của đối tác. Giá
     * theo giờ cao điểm (17:00–21:00) 200.000–300.000đ, giờ thường 100.000–180.000đ, làm tròn 10.000đ.
     *
     * @return array{slots: list<array{timeslot_id:int,price:int,over_night:bool,is_new:bool}>, created: int}
     */
    public function pick(?string $partnerId, int $count = self::DEFAULT_COUNT): array
    {
        $rows = array_map(function (array $slot) {
            $startHour = intdiv($this->toMinutes($slot['start']), 60);
            $peak      = $startHour >= 17 && $startHour < 21;

            return $slot + ['price' => $peak
                ? intdiv(random_int(200000, 300000), 10000) * 10000
                : intdiv(random_int(100000, 180000), 10000) * 10000,
            ];
        }, $this->schedule($count));

        return $this->resolve($partnerId, $rows);
    }

    /**
     * Gắn từng khung giờ vào time_slots của đối tác: trùng giờ với khung đã dùng được (hệ thống hoặc
     * riêng) thì dùng lại, chưa có thì tạo khung riêng của đối tác.
     *
     * @param list<array{start:string,end:string,over_night:bool,price:int}> $rows
     * @return array{slots: list<array{timeslot_id:int,price:int,over_night:bool,is_new:bool}>, created: int}
     */
    private function resolve(?string $partnerId, array $rows): array
    {
        $out     = [];
        $created = 0;

        foreach ($rows as $slot) {
            $existing = $this->findOrCreateSlot($partnerId, $slot['start'], $slot['end'], (bool) $slot['over_night']);
            $isNew    = $existing->wasRecentlyCreated;

            if ($isNew) {
                $created++;
            }

            $out[] = [
                'timeslot_id' => (int) $existing->id,
                'price'       => (int) $slot['price'],
                'over_night'  => (bool) $slot['over_night'],
                'is_new'      => $isNew,
            ];
        }

        return ['slots' => $out, 'created' => $created];
    }

    /**
     * Khung giờ trùng giờ bắt đầu/kết thúc/qua đêm mà đối tác dùng được (hệ thống hoặc riêng); null nếu
     * chưa có. Khung hệ thống được ưu tiên. Không tạo gì.
     */
    public function findSlot(?string $partnerId, string $start, string $end, bool $overNight): ?TimeSlot
    {
        return TimeSlot::usableBy($partnerId)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'date'))
            ->where('over_night', $overNight)
            ->where('start_time', substr($start, 0, 5) . ':00')
            ->where('end_time', substr($end, 0, 5) . ':00')
            ->orderByRaw('partner_id IS NULL DESC') // ưu tiên khung hệ thống
            ->first();
    }

    /** Như findSlot() nhưng chưa có thì tạo khung riêng của đối tác (không bao giờ tạo trùng giờ). */
    public function findOrCreateSlot(?string $partnerId, string $start, string $end, bool $overNight): TimeSlot
    {
        $start = substr($start, 0, 5);
        $end   = substr($end, 0, 5);

        return $this->findSlot($partnerId, $start, $end, $overNight)
            ?? TimeSlot::create([
                'partner_id' => $partnerId,
                'start_time' => $start,
                'end_time'   => $end,
                'over_night' => $overNight,
                'label'      => "{$start} - {$end}" . ($overNight ? ' (Qua đêm)' : ''),
                'type'       => 'time',
            ]);
    }

    private function clock(int $minutes): string
    {
        $minutes %= 1440;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function toMinutes(string $clock): int
    {
        [$h, $m] = array_map('intval', explode(':', $clock));

        return $h * 60 + $m;
    }
}
