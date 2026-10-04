<?php

namespace Modules\Coach\App\Providers;

use Illuminate\Support\ServiceProvider;

class CoachServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path('Coach', 'Database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
