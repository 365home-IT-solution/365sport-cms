<?php

namespace Modules\Coach\App\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class CoachPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string { return 'Coach'; }
    public function getId(): string { return 'coach'; }
    public function boot(Panel $panel): void {}
}
