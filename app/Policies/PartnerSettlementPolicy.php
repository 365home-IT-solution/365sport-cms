<?php

declare(strict_types=1);

namespace App\Policies;

class PartnerSettlementPolicy extends ShieldPolicy
{
    protected string $resource = 'partner::settlement';
}
