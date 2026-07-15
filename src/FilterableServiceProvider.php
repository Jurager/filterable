<?php

namespace Jurager\Filterable;

use Illuminate\Database\Eloquent\Model;
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

        if (config('filterable.included_relations.auto_load', true)) {
            Model::retrieved(function (Model $model): void {
                if (method_exists($model, 'loadIncludedRelations')) {
                    $model->loadIncludedRelations(request()->query('filter') ?? []);
                }
            });
        }
    }
}
