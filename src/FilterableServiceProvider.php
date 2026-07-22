<?php

declare(strict_types=1);

namespace Jurager\Filterable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class FilterableServiceProvider extends ServiceProvider
{
    /** Container tag used to auto-wire custom resolvers. */
    public const string RESOLVER_TAG = 'filterable.resolvers';

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