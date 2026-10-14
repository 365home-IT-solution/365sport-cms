<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerSkillLevelLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'sport_type', 'from_level', 'to_level', 'source', 'changed_by', 'note'];
}
