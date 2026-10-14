<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\PlayerSkillLevel;
use App\Models\PlayerSkillLevelLog;
use App\Support\SkillLevels;
use Illuminate\Support\Facades\DB;

// Nơi DUY NHẤT ghi trình độ người chơi: bảng player_skill_levels (+ log lịch sử) và đồng bộ ngược sang 2 cột cũ trên customers
// (skill_level = trình độ chung, sport_levels = {môn: trình độ}) để các màn / API cũ (kèo, hồ sơ) vẫn đọc đúng.
class PlayerSkillService
{
    /**
     * Đặt / xoá trình độ một môn của khách.
     *
     * @param  string|null  $level  null = xoá
     * @throws \DomainException  'skill_level_locked' khi khách tự khai nhưng trình độ đã được nhân viên xác nhận
     */
    public function set(Customer $customer, string $sport, ?string $level, string $source, ?string $byUserId = null, ?string $note = null): ?PlayerSkillLevel
    {
        $sport = trim($sport) !== '' ? trim($sport) : SkillLevels::GENERAL;

        return DB::transaction(function () use ($customer, $sport, $level, $source, $byUserId, $note) {
            $row = PlayerSkillLevel::where('customer_id', $customer->id)->where('sport_type', $sport)->lockForUpdate()->first();

            if ($source === PlayerSkillLevel::SOURCE_SELF && $row && $row->source === PlayerSkillLevel::SOURCE_STAFF && $row->level !== $level) {
                throw new \DomainException('skill_level_locked');
            }

            $from = $row?->level;
            if ($level === null) {
                if ($row) {
                    $row->delete();
                    $this->log($customer, $sport, $from, null, $source, $byUserId, $note);
                }
                $this->syncCustomer($customer);

                return null;
            }

            // Khách khai lại đúng trình độ nhân viên đã xác nhận: giữ nguyên (không hạ về "tự khai").
            if ($source === PlayerSkillLevel::SOURCE_SELF && $row && $row->source === PlayerSkillLevel::SOURCE_STAFF && $row->level === $level) {
                return $row;
            }

            if ($row && $row->level === $level && $row->source === $source) {
                // Không đổi trình độ — chỉ cập nhật ghi chú / dấu thời gian đánh giá nếu nhân viên xác nhận lại.
                $row->fill(['note' => $note ?? $row->note]);
                if ($source === PlayerSkillLevel::SOURCE_STAFF) {
                    $row->fill(['assessed_by' => $byUserId, 'assessed_at' => now(), 'assessment_requested_at' => null]);
                }
                $row->save();

                return $row;
            }

            $attrs = [
                'level'  => $level,
                'source' => $source,
                'note'   => $note,
                'assessed_by' => $source === PlayerSkillLevel::SOURCE_STAFF ? $byUserId : null,
                'assessed_at' => $source === PlayerSkillLevel::SOURCE_STAFF ? now() : null,
            ];
            if ($source === PlayerSkillLevel::SOURCE_STAFF) {
                $attrs['assessment_requested_at'] = null;
            }

            $row = $row ?? new PlayerSkillLevel(['customer_id' => $customer->id, 'sport_type' => $sport]);
            $row->fill($attrs)->save();

            $this->log($customer, $sport, $from, $level, $source, $byUserId, $note);
            $this->syncCustomer($customer);

            return $row;
        });
    }

    /** Khách xin nhân viên đánh giá / xác nhận lại trình độ một môn (phải đã có trình độ tự khai). */
    public function requestAssessment(Customer $customer, string $sport): ?PlayerSkillLevel
    {
        $row = PlayerSkillLevel::where('customer_id', $customer->id)->where('sport_type', $sport)->first();
        if (! $row) {
            return null;
        }
        $row->forceFill(['assessment_requested_at' => now()])->save();

        return $row;
    }

    /**
     * Áp dụng phần khai trình độ trong cập nhật hồ sơ (skill_level chung + sport_levels): môn nhân viên đã xác nhận được giữ nguyên.
     *
     * @param  array<string,string>|null  $sportLevels  null = không đụng tới; [] = xoá hết phần tự khai
     */
    public function applyProfileDeclaration(Customer $customer, bool $generalPresent, ?string $general, ?array $sportLevels): void
    {
        if ($generalPresent) {
            try {
                $this->set($customer, SkillLevels::GENERAL, $general ?: null, PlayerSkillLevel::SOURCE_SELF);
            } catch (\DomainException) {
                // trình độ chung đã được nhân viên xác nhận → giữ nguyên
            }
        }

        if ($sportLevels !== null) {
            $existing = PlayerSkillLevel::where('customer_id', $customer->id)->where('sport_type', '!=', SkillLevels::GENERAL)->get()->keyBy('sport_type');
            foreach ($sportLevels as $sport => $level) {
                try {
                    $this->set($customer, (string) $sport, $level ?: null, PlayerSkillLevel::SOURCE_SELF);
                } catch (\DomainException) {
                    // môn đã được nhân viên xác nhận → giữ nguyên
                }
            }
            foreach ($existing as $sport => $row) {
                if (! array_key_exists($sport, $sportLevels) && $row->source === PlayerSkillLevel::SOURCE_SELF) {
                    $this->set($customer, (string) $sport, null, PlayerSkillLevel::SOURCE_SELF);
                }
            }
        }
    }

    /** Ghi lại 2 cột cũ trên customers theo bảng player_skill_levels. */
    public function syncCustomer(Customer $customer): void
    {
        $rows = PlayerSkillLevel::where('customer_id', $customer->id)->get();
        $general = $rows->firstWhere('sport_type', SkillLevels::GENERAL)?->level;
        $sports = $rows->where('sport_type', '!=', SkillLevels::GENERAL)->mapWithKeys(fn ($r) => [$r->sport_type => $r->level])->all();

        DB::table('customers')->where('id', $customer->id)->update([
            'skill_level'  => $general,
            'sport_levels' => $sports ? json_encode($sports, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $customer->setAttribute('skill_level', $general);
        $customer->setAttribute('sport_levels', $sports ?: null);
        $customer->syncOriginal();
    }

    /** Dữ liệu trả về cho app/admin: danh sách trình độ của một khách. */
    public function presentFor(Customer $customer): array
    {
        return PlayerSkillLevel::where('customer_id', $customer->id)->orderByRaw("sport_type = 'general' desc")->orderBy('sport_type')->get()
            ->map(fn (PlayerSkillLevel $r) => self::present($r))->all();
    }

    public static function present(PlayerSkillLevel $r): array
    {
        return [
            'id'                       => $r->id,
            'sport_type'               => $r->sport_type,
            'level'                    => $r->level,
            'level_label'              => SkillLevels::label($r->level),
            'source'                   => $r->source,
            'verified'                 => $r->source === PlayerSkillLevel::SOURCE_STAFF,
            'note'                     => $r->note,
            'assessed_at'              => $r->assessed_at?->toIso8601String(),
            'assessment_requested_at'  => $r->assessment_requested_at?->toIso8601String(),
            'updated_at'               => $r->updated_at?->toIso8601String(),
        ];
    }

    private function log(Customer $customer, string $sport, ?string $from, ?string $to, string $source, ?string $by, ?string $note): void
    {
        PlayerSkillLevelLog::create([
            'customer_id' => $customer->id, 'sport_type' => $sport, 'from_level' => $from, 'to_level' => $to,
            'source' => $source, 'changed_by' => $by, 'note' => $note,
        ]);
    }
}
