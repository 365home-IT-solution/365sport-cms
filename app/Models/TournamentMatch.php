<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    protected $fillable = ['tournament_id', 'round', 'team_a_id', 'team_b_id', 'scheduled_at', 'venue', 'score_a', 'score_b', 'status'];

    protected $casts = ['scheduled_at' => 'datetime', 'score_a' => 'integer', 'score_b' => 'integer'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teamA()
    {
        return $this->belongsTo(Team::class, 'team_a_id');
    }

    public function teamB()
    {
        return $this->belongsTo(Team::class, 'team_b_id');
    }

    // Đội thắng (null nếu chưa đá xong hoặc hoà).
    public function winnerId(): ?int
    {
        if ($this->status !== 'finished' || $this->score_a === null || $this->score_b === null || $this->score_a === $this->score_b) {
            return null;
        }

        return $this->score_a > $this->score_b ? $this->team_a_id : $this->team_b_id;
    }
}
