<?php

namespace Jurager\Filterable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    /**
     * Prefix marking a filter key as an eager-load constraint.
     */
    private const INCLUDED_PREFIX = 'included.';

    /**
     * Models that already have the cache observer attached.
     * Persists across requests under Octane, preventing duplicate listeners.
     *
     * @var array<class-string, true>
     */
    private static array $filterableObserved = [];

    /**
     * Cached Filterable definition for this model instance.
     */
    private ?Filterable $filterableInstance = null;

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
     * @param mixed $query
     * @return FilterableBuilder
     */
    public function newEloquentBuilder($query): FilterableBuilder
    {
        return new FilterableBuilder($query);
    }

    /**
     * @return array
     */
    public function filterableCacheConfig(): array
    {
        return $this->filterablePropertyArray('cache');
    }

    /**
     * Read a trait-adjacent array property from the model, if defined.
     *
     * @param string $name
     * @return array
     */
    private function filterablePropertyArray(string $name): array
    {
        return property_exists($this, $name) && is_array($this->{$name}) ? $this->{$name} : [];
    }

    /**
     * Build (or reuse) the Filterable definition for this model.
     *
     * @return Filterable
     */
    protected function newFilterable(): Filterable
    {
        return $this->filterableInstance ??= $this->buildFilterable();
    }

    /**
     * Construct a Filterable instance and attach any container-tagged resolvers.
     *
     * @return Filterable
     */
    private function buildFilterable(): Filterable
    {
        $filterable = new Filterable(
            $this->filterablePropertyArray('filterable'),
            $this->filterablePropertyArray('sortable'),
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

        return $filterable;
    }

    /**
     * Apply filter conditions to the query. Takes the parsed array directly.
     *
     * @param Builder $query
     * @param array $filter
     * @return Builder
     */
    public function scopeFilter(Builder $query, array $filter): Builder
    {
        if (!$filter) {
            return $query;
        }

        $query->withGlobalScope('_filterable_filter', new PendingFilterScope($this->newFilterable(), $filter));

        if ($query instanceof FilterableBuilder && config('filterable.cache.enabled', false)) {
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

        return $this->newQuery()
            ->whereIn($this->qualifyColumn($keyName), $ids)
            ->filter($filter)
            ->pluck($keyName)
            ->all();
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
        if (is_callable($condition) ? $condition() : $condition) {
            return $this->scopeCache($query, $ttl);
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

        if ($field !== $this->getKeyName() && method_exists($this, 'scopeWhereAttribute')) {
            return $this->whereAttribute($field, $value)->first();
        }

        return parent::resolveRouteBinding($value, $field);
    }

    /**
     * Eager-load relations scoped by included conditions.
     *
     * @param array $filter
     * @return static
     */
    public function loadIncludedRelations(array $filter): static
    {
        $included = $this->extractIncluded($filter);

        if (!$included) {
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

    /**
     * Pull included.* keys out of a filter array, stripping the prefix.
     *
     * @param array $filter
     * @return array
     */
    private function extractIncluded(array $filter): array
    {
        $included = [];

        foreach ($filter as $key => $value) {
            if (is_string($key) && str_starts_with($key, self::INCLUDED_PREFIX)) {
                $included[substr($key, strlen(self::INCLUDED_PREFIX))] = $value;
            }
        }

        return $included;
    }
}