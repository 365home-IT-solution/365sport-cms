<?php

declare(strict_types=1);

namespace App\Policies;

class RoomRatingPolicy extends ShieldPolicy
{
    protected string $resource = 'room::rating';
}
