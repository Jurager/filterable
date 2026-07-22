<?php

declare(strict_types=1);

namespace Jurager\Filterable\Query;

use BadMethodCallException;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Jurager\Filterable\Cache\CacheConfig;
use Jurager\Filterable\Scopes\PendingFilterScope;
use Jurager\Filterable\Scopes\PendingSortScope;

/** Custom Eloquent builder with filtering, sorting, and caching support. */
class FilterableBuilder extends Builder
{
    /** Global scope key under which the pending filter scope is registered. */
    public const string FILTER_SCOPE = '_filterable_filter';

    /** Global scope key under which the pending sort scope is registered. */
    public const string SORT_SCOPE = '_filterable_sort';

    private bool $cacheEnabled = false;

    private ?int $cacheTtl = null;

    /** Get the pending filter scope if registered. */
    public function getFilterScope(): ?PendingFilterScope
    {
        return $this->scopes[self::FILTER_SCOPE] ?? null;
    }

    /** Get the pending sort scope if registered. */
    public function getSortScope(): ?PendingSortScope
    {
        return $this->scopes[self::SORT_SCOPE] ?? null;
    }

    /** Enable caching for the current query. */
    public function enableCache(?int $ttl = null): static
    {
        $this->cacheEnabled = true;
        $this->cacheTtl     = $ttl;

        return $this;
    }

    /** Execute the query as a "select" statement. */
    public function get($columns = ['*']): Collection
    {
        if (! $this->cacheEnabled) {
            return parent::get($columns);
        }

        return $this->remember($this->cacheKey('get', $columns), fn () => parent::get($columns));
    }

    /** Retrieve the "count" result of the query. */
    public function count($columns = '*'): int
    {
        if (! $this->cacheEnabled) {
            return parent::count($columns);
        }

        return $this->remember($this->cacheKey('count', $columns), fn () => parent::count($columns));
    }

    /** Determine if any rows exist for the current query. */
    public function exists(): bool
    {
        if (! $this->cacheEnabled) {
            return parent::exists();
        }

        return $this->remember($this->cacheKey('exists'), fn () => parent::exists());
    }

    /** Determine if no rows exist for the current query. */
    public function doesntExist(): bool
    {
        if (! $this->cacheEnabled) {
            return parent::doesntExist();
        }

        return $this->remember($this->cacheKey('doesntExist'), fn () => parent::doesntExist());
    }

    /** Build a unique cache key for the current query state and method. */
    private function cacheKey(string $method, mixed ...$args): string
    {
        $hash = hash('xxh3', serialize([
            get_class($this->getModel()),
            $method,
            $args,
            $this->getQuery()->toSql(),
            $this->getQuery()->getBindings(),
            $this->getFilterScope()?->raw,
            $this->getSortScope()?->sort,
        ]));

        return "filterable:{$hash}";
    }

    /** Execute the callback and cache the result using tags. */
    private function remember(string $key, Closure $callback): mixed
    {
        $config = CacheConfig::for($this->getModel());
        $tags   = $config['tags'] ?? [$this->getModel()->getTable()];
        $ttl    = $this->cacheTtl ?? $config['ttl'] ?? 3600;

        try {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        } catch (BadMethodCallException) {
            // Ignore drivers that do not support tags and execute directly
            return $callback();
        }
    }
}