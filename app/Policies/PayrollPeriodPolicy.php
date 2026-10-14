<?php

declare(strict_types=1);

namespace App\Policies;

class PayrollPeriodPolicy extends ShieldPolicy
{
    protected string $resource = 'payroll::period';
}
