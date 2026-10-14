<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeAttendancePolicy extends ShieldPolicy
{
    protected string $resource = 'employee::attendance';
}
