<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use Illuminate\Support\Facades\DB;

// Nghiệp vụ giải đấu dùng chung cho API khách, API quản trị và Filament.
class TournamentService
{
    // Trưởng nhóm đăng ký đội cho giải. Khoá dòng giải để 2 đội cuối đăng ký cùng lúc không vượt số đội tối đa.
    public function register(Tournament $tournament, Team $team, Customer $by): array
    {
        if (! $team->isLeader($by->id)) {
            return ['ok' => false, 'code' => 403, 'message' => 'Only the team leader can register the team.'];
        }
        if ($team->status !== Team::STATUS_ACTIVE) {
            return ['ok' => false, 'code' => 422, 'message' => 'This team is no longer active.'];
        }

        return DB::transaction(function () use ($tournament, $team, $by) {
            $locked = Tournament::query()->withoutGlobalScopes()->lockForUpdate()->find($tournament->id);
            if (! $locked || ! $locked->acceptsRegistration()) {
                return ['ok' => false, 'code' => 422, 'message' => 'Registration for this tournament is closed or full.'];
            }

            $existing = TournamentTeam::query()->where('tournament_id', $locked->id)->where('team_id', $team->id)->first();
            if ($existing && $existing->status !== 'rejected') {
                return ['ok' => false, 'code' => 409, 'message' => 'This team has already registered.'];
            }
            if ($existing) {
                return ['ok' => false, 'code' => 422, 'message' => 'The organizer declined this team.'];
            }

            return ['ok' => true, 'registration' => TournamentTeam::create(['tournament_id' => $locked->id, 'team_id' => $team->id, 'registered_by' => $by->id, 'status' => 'pending'])];
        });
    }

    // Quyết định đăng ký: duyệt / từ chối. Duyệt phải còn chỗ trong số đội tối đa.
    public function decide(TournamentTeam $reg, string $decision, ?bool $feePaid = null): array
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            return ['ok' => false, 'message' => 'Decision must be approved or rejected.'];
        }

        return DB::transaction(function () use ($reg, $decision, $feePaid) {
            $tournament = Tournament::query()->withoutGlobalScopes()->lockForUpdate()->find($reg->tournament_id);
            if ($decision === 'approved' && $reg->status !== 'approved'
                && TournamentTeam::query()->where('tournament_id', $tournament->id)->where('status', 'approved')->count() >= $tournament->max_teams) {
                return ['ok' => false, 'message' => 'The tournament already has the maximum number of teams.'];
            }

            $reg->update(['status' => $decision] + ($feePaid !== null ? ['fee_paid' => $feePaid] : []));

            return ['ok' => true];
        });
    }

    // Lưu trận đấu: hai đội phải khác nhau và đều là đội đã được duyệt của giải; có tỉ số đủ cả hai bên thì trận coi là đã kết thúc.
    public function saveMatch(Tournament $tournament, array $data, ?TournamentMatch $match = null): array
    {
        $a = (int) ($data['team_a_id'] ?? $match?->team_a_id);
        $b = (int) ($data['team_b_id'] ?? $match?->team_b_id);
        if ($a === $b) {
            return ['ok' => false, 'message' => 'A match needs two different teams.'];
        }

        $approved = TournamentTeam::query()->where('tournament_id', $tournament->id)->where('status', 'approved')->pluck('team_id')->all();
        if (! in_array($a, $approved, true) || ! in_array($b, $approved, true)) {
            return ['ok' => false, 'message' => 'Both teams must be approved participants of this tournament.'];
        }

        $scoreA = array_key_exists('score_a', $data) ? $data['score_a'] : $match?->score_a;
        $scoreB = array_key_exists('score_b', $data) ? $data['score_b'] : $match?->score_b;
        if (($scoreA === null) !== ($scoreB === null)) {
            return ['ok' => false, 'message' => 'Enter the score for both teams.'];
        }

        $payload = array_intersect_key($data, array_flip(['round', 'team_a_id', 'team_b_id', 'scheduled_at', 'venue', 'score_a', 'score_b']));
        $payload['status'] = $scoreA !== null ? 'finished' : 'scheduled';

        $saved = $match ? tap($match)->update($payload) : TournamentMatch::create($payload + ['tournament_id' => $tournament->id]);

        return ['ok' => true, 'match' => $saved];
    }

    // Bảng xếp hạng: thắng 3 điểm, hoà 1, thua 0; xếp theo điểm, hiệu số, bàn thắng.
    public function standings(Tournament $tournament): array
    {
        $rows = [];
        foreach (TournamentTeam::query()->with('team:id,name,code')->where('tournament_id', $tournament->id)->where('status', 'approved')->get() as $reg) {
            $rows[$reg->team_id] = ['team_id' => $reg->team_id, 'team_name' => $reg->team?->name, 'played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goals_for' => 0, 'goals_against' => 0, 'goal_diff' => 0, 'points' => 0];
        }

        foreach (TournamentMatch::query()->where('tournament_id', $tournament->id)->where('status', 'finished')->get() as $m) {
            foreach ([[$m->team_a_id, $m->score_a, $m->score_b], [$m->team_b_id, $m->score_b, $m->score_a]] as [$id, $for, $against]) {
                if (! isset($rows[$id])) {
                    continue;
                }
                $rows[$id]['played']++;
                $rows[$id]['goals_for'] += $for;
                $rows[$id]['goals_against'] += $against;
                if ($for > $against) {
                    $rows[$id]['won']++; $rows[$id]['points'] += 3;
                } elseif ($for === $against) {
                    $rows[$id]['drawn']++; $rows[$id]['points'] += 1;
                } else {
                    $rows[$id]['lost']++;
                }
            }
        }

        foreach ($rows as &$r) {
            $r['goal_diff'] = $r['goals_for'] - $r['goals_against'];
        }
        unset($r);

        usort($rows, fn ($x, $y) => [$y['points'], $y['goal_diff'], $y['goals_for']] <=> [$x['points'], $x['goal_diff'], $x['goals_for']]);

        return array_values($rows);
    }
}
