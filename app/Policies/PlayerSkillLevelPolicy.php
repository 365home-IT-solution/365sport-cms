<?php

declare(strict_types=1);

namespace App\Policies;

class PlayerSkillLevelPolicy extends ShieldPolicy
{
    protected string $resource = 'player::skill::level';
}
