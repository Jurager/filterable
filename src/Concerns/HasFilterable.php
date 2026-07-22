<?php

declare(strict_types=1);

namespace Jurager\Filterable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Jurager\Filterable\Cache\FilterableCacheObserver;
use Jurager\Filterable\Filterable;
use Jurager\Filterable\FilterableFactory;
use Jurager\Filterable\Query\FilterableBuilder;
use Jurager\Filterable\Scopes\PendingFilterScope;
use Jurager\Filterable\Scopes\PendingSortScope;
use Jurager\Filterable\Support\ParsedFilters;

/** Provide filtering, sorting, and caching capabilities to Eloquent models. */
trait HasFilterable
{
    /**
     * Track observed models to prevent duplicate listeners.
     *
     * @var array<class-string, true>
     */
    private static array $filterableObserved = [];

    /** Cached Filterable definition instance. */
    private ?Filterable $filterableInstance = null;

    /** Boot the trait to attach the cache observer. */
    protected static function bootHasFilterable(): void
    {
        $class = static::class;

        static::whenBooted(static function () use ($class): void {
            if (isset(self::$filterableObserved[$class])) {
                return;
            }

            self::$filterableObserved[$class] = true;

            $class::observe(new FilterableCacheObserver());
        });
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param QueryBuilder $query
     */
    public function newEloquentBuilder($query): FilterableBuilder
    {
        return new FilterableBuilder($query);
    }

    /** Get the cache configuration for the model. */
    public function filterableCacheConfig(): array
    {
        return $this->filterablePropertyArray('cache');
    }

    /** Read an array property from the model if defined. */
    private function filterablePropertyArray(string $name): array
    {
        return property_exists($this, $name) && is_array($this->{$name}) ? $this->{$name} : [];
    }

    /** Build or retrieve the cached Filterable definition. */
    protected function newFilterable(): Filterable
    {
        return $this->filterableInstance ??= (new FilterableFactory())->make(
            $this->filterablePropertyArray('filterable'),
            $this->filterablePropertyArray('sortable'),
            $this->filterableCacheConfig(),
            $this->filterablePropertyArray('sanitizers'),
        );
    }

    /** Apply filter conditions to the query. */
    public function scopeFilter(Builder $query, array $filter): Builder
    {
        if (empty($filter)) {
            return $query;
        }

        $filterable = $this->newFilterable();

        $query->withGlobalScope(FilterableBuilder::FILTER_SCOPE, new PendingFilterScope($filterable, $filter));

        if ($query instanceof FilterableBuilder && (config('filterable.cache.enabled', false) === true || $filterable->isCacheEnabled())) {
            $query->enableCache();
        }

        return $query;
    }

    /** Apply a sort specification to the query. */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        $query->withGlobalScope(FilterableBuilder::SORT_SCOPE, new PendingSortScope($this->newFilterable(), $sort));

        return $query;
    }

    /** Narrow a list of primary keys down to those matching filter conditions. */
    public function narrow(array $ids, array $filter): array
    {
        $keyName = $this->getKeyName();

        return $this->newQuery()
            ->whereIn($this->qualifyColumn($keyName), $ids)
            ->filter($filter)
            ->pluck($keyName)
            ->all();
    }

    /** Enable cache for the current filter query. */
    public function scopeCache(Builder $query, ?int $ttl = null): Builder
    {
        if ($query instanceof FilterableBuilder) {
            $query->enableCache($ttl);
        }

        return $query;
    }

    /** Conditionally enable cache for the current filter query. */
    public function scopeCacheWhen(Builder $query, bool|callable $condition, ?int $ttl = null): Builder
    {
        $shouldCache = is_callable($condition) ? $condition() : $condition;

        if ($shouldCache) {
            return $this->scopeCache($query, $ttl);
        }

        return $query;
    }

    /**
     * Retrieve the model for a bound value, supporting EAV attributes.
     *
     * @param mixed $value
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        $field ??= $this->getRouteKeyName();

        if ($field !== $this->getKeyName() && method_exists($this, 'scopeWhereAttribute')) {
            return $this->whereAttribute($field, $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /** Eager-load relations scoped by included conditions. */
    public function loadIncludedRelations(array $filter): static
    {
        $included = ParsedFilters::extractIncluded($filter);

        if (empty($included)) {
            return $this;
        }

        foreach ($this->newFilterable()->filterableRelations($included, $this) as $relation => $callback) {
            /** @var Relation $query */
            $query = $this->{$relation}();

            $callback($query);

            $this->setRelation($relation, $query->getResults());
        }

        return $this;
    }
}