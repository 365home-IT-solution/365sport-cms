<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentTeam extends Model
{
    protected $fillable = ['tournament_id', 'team_id', 'registered_by', 'status', 'fee_paid', 'note'];

    protected $casts = ['fee_paid' => 'boolean'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
