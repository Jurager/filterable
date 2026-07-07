<?php

namespace Jurager\Filterable;

use Illuminate\Support\ServiceProvider;

class FilterableServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filterable.php', 'filterable');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/filterable.php' => config_path('filterable.php'),
        ], 'filterable-config');
    }
}
