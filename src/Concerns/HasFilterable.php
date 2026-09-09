<?php

declare(strict_types=1);

namespace Jurager\Filterable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jurager\Filterable\Cache\CachingConnection;
use Jurager\Filterable\Cache\FilterableCacheObserver;
use Jurager\Filterable\Filterable;
use Jurager\Filterable\FilterableFactory;
use Jurager\Filterable\Scopes\PendingFilterScope;
use Jurager\Filterable\Scopes\PendingSortScope;
use Jurager\Filterable\Support\ParsedFilters;

/** Provide filtering, sorting, and caching capabilities to Eloquent models. */
trait HasFilterable
{
    /** Global scope key under which the pending filter scope is registered. */
    public const string FILTER_SCOPE = '_filterable_filter';

    /** Global scope key under which the pending sort scope is registered. */
    public const string SORT_SCOPE = '_filterable_sort';

    /**
     * Track observed models to prevent duplicate listeners.
     *
     * @var array<class-string, true>
     */
    private static array $filterableObserved = [];

    /** Cached Filterable definition instance. */
    private ?Filterable $filterableInstance = null;

    /** @var array<string, mixed> Constraints awaiting the next sort() call on this model instance. */
    private array $pendingSortContext = [];

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

    /** Get the cache configuration for the model. */
    public function filterableCacheConfig(): array
    {
        return $this->filterablePropertyArray('cache');
    }

    /**
     * Get the fields the model allows filtering on, mapped to their permitted operators.
     *
     * @return array<string, array<string>|string>
     */
    public function filterableFields(): array
    {
        return $this->filterablePropertyArray('filterable');
    }

    /**
     * Get the fields the model allows sorting on.
     *
     * @return array<int, string>
     */
    public function sortableFields(): array
    {
        return $this->filterablePropertyArray('sortable');
    }

    /** Get the resolvers declared on the model, as class strings or instances. */
    public function filterableResolvers(): array
    {
        return $this->filterablePropertyArray('resolvers');
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
            $this->filterablePropertyArray('sanitizers'),
            $this->filterablePropertyArray('resolvers'),
        );
    }

    /**
     * Apply filter conditions to the query.
     */
    public function scopeFilter(Builder $query, array $filter): Builder
    {
        if (empty($filter)) {
            return $query;
        }

        $this->pendingSortContext = ParsedFilters::extractIncluded($filter);

        $query->withGlobalScope(self::FILTER_SCOPE, new PendingFilterScope($this->newFilterable(), $filter));

        return $query;
    }

    /**
     * Apply a sort specification to the query.
     *
     * @param array<string, mixed> $context Request-scoped values forwarded to the sort resolvers, for orderings that depend on data outside the model.
     */
    public function scopeSort(Builder $query, ?string $sort, array $context = []): Builder
    {
        $pending = $this->pendingSortContext;

        $this->pendingSortContext = [];

        $query->withGlobalScope(self::SORT_SCOPE, new PendingSortScope($this->newFilterable(), $sort, [...$pending, ...$context]));

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

    /**
     * Cache the result of the current query's terminal call
     */
    public function scopeCached(Builder $query, ?int $ttl = null): Builder
    {
        $config = $this->filterableCacheConfig();

        $raw = $query->getQuery();

        $raw->connection = new CachingConnection($raw->getConnection(), $config['tags'] ?? [$this->getTable()], $ttl ?? $config['ttl'] ?? config('filterable.cache.ttl', 3600));

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

    /**
     * Eager-load relations scoped by included conditions.
     *
     * Meant for a single already-fetched model whose query never went through `filter()` — e.g. a
     * `find()` on a show endpoint. When the model came from a query that *did* call `filter()`,
     * `Filterable::apply()` already eager-loaded these same relations, scoped the same way, for
     * the whole result set in one query; skip a relation already loaded rather than re-fetching it
     * one model at a time (`WithEagerIncludes` calls this per model in a listing's result set).
     */
    public function loadIncludedRelations(array $filter): static
    {
        $included = ParsedFilters::extractIncluded($filter);

        if (empty($included)) {
            return $this;
        }

        foreach ($this->newFilterable()->filterableRelations($included, $this) as $relation => $callback) {

            if ($this->relationLoaded($relation)) {
                continue;
            }

            /** @var Relation $query */
            $query = $this->{$relation}();

            $callback($query);

            $this->setRelation($relation, $query->getResults());
        }

        return $this;
    }

    /**
     * Eager-load relations scoped by included conditions, batched for a whole collection.
     *
     * The collection counterpart of {@see loadIncludedRelations()} — for results that never
     * touched `filter()` at all, so none of them carry the relation, and there's no query-builder
     * eager load to defer to (e.g. a search engine's results, hydrated via `whereIn(id, ...)`
     * rather than a filtered query). Loading those one model at a time is the exact N+1
     * `loadIncludedRelations()` exists to avoid on the filtered path; this does it once for the
     * whole set, the same way `Filterable::apply()` would have.
     *
     * @param  \Illuminate\Support\Collection<int, static>  $models
     */
    public static function loadIncludedRelationsForMany($models, array $filter): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $included = ParsedFilters::extractIncluded($filter);

        if (empty($included)) {
            return;
        }

        $template = $models->first();

        foreach ($template->newFilterable()->filterableRelations($included, $template) as $relation => $callback) {
            if ($template->relationLoaded($relation)) {
                continue;
            }

            $models->loadMissing([$relation => $callback]);
        }
    }

    /**
     * Determine whether this model instance satisfies the given filter conditions.
     */
    public function matchesFilter(array $filter): bool
    {
        return $this->newQuery()->whereKey($this->getKey())->filter($filter)->exists();
    }
}
