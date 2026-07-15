<?php

namespace Jurager\Filterable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Cache\FilterableCacheObserver;
use Jurager\Filterable\Contracts\FieldResolverInterface;
use Jurager\Filterable\Contracts\RelationResolverInterface;
use Jurager\Filterable\Contracts\SortResolverInterface;
use Jurager\Filterable\Filterable;
use Jurager\Filterable\Query\FilterableBuilder;
use Jurager\Filterable\Scopes\PendingFilterScope;
use Jurager\Filterable\Scopes\PendingSortScope;

trait HasFilterable
{
    public static function bootHasFilterable(): void
    {
        static $registered = [];

        $modelClass = static::class;

        static::whenBooted(function () use (&$registered, $modelClass): void {
            if (isset($registered[$modelClass])) {
                return;
            }
            $registered[$modelClass] = true;
            $modelClass::observe(new FilterableCacheObserver());
        });
    }

    /**
     * @param mixed $query
     * @return FilterableBuilder
     */
    public function newEloquentBuilder($query): FilterableBuilder
    {
        return new FilterableBuilder($query);
    }

    public function filterableCacheConfig(): array
    {
        return property_exists($this, 'cache') && is_array($this->cache) ? $this->cache : [];
    }

    /**
     * @return Filterable
     */
    protected function newFilterable(): Filterable
    {
        if ($this->filterableInstance !== null) {
            return $this->filterableInstance;
        }

        $filterable = new Filterable(
            property_exists($this, 'filterable') ? $this->filterable : [],
            property_exists($this, 'sortable') && is_array($this->sortable) ? $this->sortable : [],
            $this->filterableCacheConfig(),
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

        return $this->filterableInstance = $filterable;
    }

    private ?Filterable $filterableInstance = null;

    /**
     * Apply filter conditions to the query. Takes the parsed array directly.
     *
     * @param Builder $query
     * @param array $filter
     * @return Builder
     */
    public function scopeFilter(Builder $query, array $filter): Builder
    {
        if (empty($filter)) {
            return $query;
        }

        $query->withGlobalScope('_filterable_filter', new PendingFilterScope($this->newFilterable(), $filter));

        if (config('filterable.cache.enabled', false) && $query instanceof FilterableBuilder) {
            $query->enableCache();
        }

        return $query;
    }

    /**
     * Apply a sort spec to the query.
     *
     * @param Builder $query
     * @param string|null $sort
     * @return Builder
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        $query->withGlobalScope('_filterable_sort', new PendingSortScope($this->newFilterable(), $sort));

        return $query;
    }

    /**
     * Narrow a list of primary keys down to those matching filter conditions.
     *
     * @param array $ids
     * @param array $filter
     * @return array
     */
    public function narrow(array $ids, array $filter): array
    {
        $keyName = $this->getKeyName();

        return static::query()->whereIn($keyName, $ids)->filter($filter)->pluck($keyName)->all();
    }

    /**
     * Enable cache for the current filter query.
     *
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
     *
     * @param Builder $query
     * @param bool|callable $condition
     * @param int|null $ttl
     * @return Builder
     */
    public function scopeCacheWhen(Builder $query, bool|callable $condition, ?int $ttl = null): Builder
    {
        if ((is_callable($condition) ? $condition() : $condition) && $query instanceof FilterableBuilder) {
            $query->enableCache($ttl);
        }

        return $query;
    }

    /**
     * Resolve route model binding with EAV attribute support and relation loading.
     *
     * @param mixed $value
     * @param string|null $field
     * @return static|null
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        $field ??= $this->getRouteKeyName();

        /** @var static|null $model */
        if ($field !== $this->getKeyName() && method_exists($this, 'scopeWhereAttribute')) {
            $model = $this->whereAttribute($field, $value)->first();
        } else {
            $model = parent::resolveRouteBinding($value, $field);
        }

        $model?->loadIncludedRelations(request()->query('filter') ?? []);

        return $model;
    }

    /**
     * Eager-load relations scoped by included conditions.
     *
     * @param array $filter
     * @return static
     */
    public function loadIncludedRelations(array $filter): static
    {
        if (empty($filter)) {
            return $this;
        }

        $included = [];

        foreach ($filter as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'included.')) {
                $included[substr($key, 9)] = $value;
            }
        }

        if (empty($included)) {
            return $this;
        }

        foreach ($this->newFilterable()->filterableRelations($included, $this) as $relation => $callback) {

            $query = $this->{$relation}();
            $result = $callback($query);

            $this->setRelation(
                $relation,
                $result instanceof Builder ? $result->get() : $query->get(),
            );
        }

        return $this;
    }
}
