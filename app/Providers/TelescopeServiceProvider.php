<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TelescopeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Telescope hanya aktif di environment local dan jika package terinstall
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }
    }

    public function boot(): void
    {
        //
    }
}
