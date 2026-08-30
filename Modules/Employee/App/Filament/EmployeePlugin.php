<?php

declare(strict_types=1);

namespace Modules\Employee\App\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class EmployeePlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Employee';
    }

    public function getId(): string
    {
        return 'employee';
    }

    public function boot(Panel $panel): void
    {
        // TODO: Implement boot() method.
    }
}
