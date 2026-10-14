<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMessage extends Model
{
    protected $fillable = ['team_id', 'customer_id', 'body'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
