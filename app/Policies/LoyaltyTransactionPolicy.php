<?php

declare(strict_types=1);

namespace App\Policies;

class LoyaltyTransactionPolicy extends ShieldPolicy
{
    protected string $resource = 'loyalty::transaction';
}
