<?php

namespace Jurager\Filterable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Jurager\Filterable\Cache\FilterableCacheObserver;
use Jurager\Filterable\Contracts\FieldResolverInterface;
use Jurager\Filterable\Contracts\RelationResolverInterface;
use Jurager\Filterable\Contracts\SortResolverInterface;
use Jurager\Filterable\Filterable;
use Jurager\Filterable\Query\FilterableBuilder;

/**
 * Attaches Filterable to an Eloquent model and enhances route model binding.
 * Declare $filterable and $sortable on the model, or override newFilterable() to return a custom subclass.
 *
 * Third-party resolvers are registered via a single container tag:
 *   $this->app->tag([MyResolver::class], 'filterable.resolvers');
 *
 * @property array $filterable
 * @property array $sortable
 */
trait HasFilterable
{
    /**
     * Register FilterableCacheObserver when caching is enabled.
     * @return void
     */
    public static function bootHasFilterable(): void
    {
        // Static guard prevents duplicate observer registration under Octane.
        // Octane resets Model::$booted each request but not Model::$observers.
        static $registered = [];

        if (isset($registered[static::class])) {
            return;
        }

        $model      = new static();
        $filterable = $model->newFilterable();
        $enabled    = $filterable->isCacheEnabled() || (bool) config('filterable.cache.enabled', false);

        if (!$enabled) {
            return;
        }

        $tags = $filterable->getCacheTags() ?: [$model->getTable()];

        static::observe(new FilterableCacheObserver($tags));

        $registered[static::class] = true;
    }

    /**
     * @param mixed $query
     * @return FilterableBuilder
     */
    public function newEloquentBuilder($query): FilterableBuilder
    {
        return new FilterableBuilder($query);
    }

    /**
     * @return Filterable
     */
    protected function newFilterable(): Filterable
    {
        $filterable = new Filterable(
            property_exists($this, 'filterable') ? $this->filterable : [],
            property_exists($this, 'sortable') && is_array($this->sortable) ? $this->sortable : [],
        );

        foreach (app()->tagged('filterable.resolvers') as $resolver) {
            if ($resolver instanceof FieldResolverInterface) {
                $filterable->addFieldResolver($resolver);
            }
            if ($resolver instanceof RelationResolverInterface) {
                $filterable->addRelationResolver($resolver);
            }
            if ($resolver instanceof SortResolverInterface) {
                $filterable->addSortResolver($resolver);
            }
        }

        return $filterable;
    }

    /**
     * Apply filter[] query params to the query.
     * @param Builder $query
     * @param Request|array|null $request
     * @return Builder
     */
    public function scopeFilter(Builder $query, Request|array|null $request = null): Builder
    {
        $raw = is_array($request) ? $request : (($request ?? request())->query('filter') ?? []);

        abort_if(is_string($raw), 400, 'The filter parameter must be an array.');

        if (!is_array($raw) || empty($raw)) {
            return $query;
        }

        if ($query instanceof FilterableBuilder) {
            $query->setPendingFilter($this->newFilterable(), $raw);

            return $query;
        }

        return $this->newFilterable()->apply($query, $raw);
    }

    /**
     * Apply sort query param to the query.
     * @param Builder $query
     * @param Request|null $request
     * @return Builder
     */
    public function scopeSort(Builder $query, Request|null $request = null): Builder
    {
        $sort = ($request ?? request())->query('sort');

        return $this->newFilterable()->sort($query, is_string($sort) ? $sort : null);
    }

    /**
     * Enable cache for the current filter query.
     * @param Builder $query
     * @param int|null $ttl
     * @return Builder
     */
    public function scopeCache(Builder $query, ?int $ttl = null): Builder
    {
        if ($query instanceof FilterableBuilder) {
            $query->enableCache($ttl);
        }

        return $query;
    }

    /**
     * Conditionally enable cache for the current filter query.
     * @param Builder $query
     * @param bool|callable $condition
     * @param int|null $ttl
     * @return Builder
     */
    public function scopeCacheWhen(Builder $query, bool|callable $condition, ?int $ttl = null): Builder
    {
        $should = is_callable($condition) ? $condition() : $condition;

        if ($should && $query instanceof FilterableBuilder) {
            $query->enableCache($ttl);
        }

        return $query;
    }

    /**
     * Resolve route model binding with EAV attribute support and relation loading.
     * @param mixed $value
     * @param string|null $field
     * @return static|null
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        $field ??= $this->resolveRouteBindingField($value);

        /** @var static|null $model */
        if ($field !== $this->getKeyName() && method_exists($this, 'scopeWhereAttribute')) {
            $model = $this->whereAttribute($field, $value)->first();
        } else {
            $model = parent::resolveRouteBinding($value, $field);
        }

        $model?->loadFilteredRelations();

        return $model;
    }

    /**
     * Determine the binding column for route model binding.
     * @param mixed $value
     * @return string
     */
    protected function resolveRouteBindingField(mixed $value): string
    {
        return 'id';
    }

    /**
     * Eager-load filterable relations scoped to filter[included.*] params.
     * @param Request|array|null $request
     * @return static
     */
    public function loadFilteredRelations(Request|array|null $request = null): static
    {
        $raw = is_array($request) ? $request : (($request ?? request())->query('filter') ?? []);

        if (!is_array($raw) || empty($raw)) {
            return $this;
        }

        $included = [];

        foreach ($raw as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'included.')) {
                $included[substr($key, 9)] = $value;
            }
        }

        if (empty($included)) {
            return $this;
        }

        foreach ($this->newFilterable()->filterableRelations($included, $this) as $relation => $callback) {
            $relationQuery = $this->{$relation}();
            $callback($relationQuery);
            $this->setRelation($relation, $relationQuery->get());
        }

        return $this;
    }
}
