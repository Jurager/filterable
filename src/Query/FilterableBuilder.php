<?php

namespace Jurager\Filterable\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Jurager\Filterable\Cache\CacheKeyGenerator;
use Jurager\Filterable\Scopes\PendingFilterScope;

/**
 * Eloquent Builder with full-result caching for filterable queries.
 */
class FilterableBuilder extends Builder
{
    private bool $useCache = false;
    private ?int $cacheTtl = null;

    // Guards against re-entrant cache wrapping: paginate() internally calls get(),
    // which would otherwise try to cache again under the same execution.
    private bool $inCachedExecution = false;

    /**
     * @param int|null $ttl
     * @return void
     */
    public function enableCache(?int $ttl = null): void
    {
        $this->useCache = true;

        if ($ttl !== null) {
            $this->cacheTtl = $ttl;
        }
    }

    /**
     * @param array|string $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        return $this->rememberResult(__FUNCTION__, func_get_args(), fn () => parent::get($columns));
    }

    /**
     * @param array|string $columns
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function first($columns = ['*'])
    {
        return $this->rememberResult(__FUNCTION__, func_get_args(), fn () => parent::first($columns));
    }

    /**
     * @param int|null $perPage
     * @param array|string $columns
     * @param string $pageName
     * @param int|null $page
     * @param int|null $total
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        return $this->rememberResult(__FUNCTION__, func_get_args(), fn () => parent::paginate($perPage, $columns, $pageName, $page, $total));
    }

    /**
     * @param int|null $perPage
     * @param array|string $columns
     * @param string $pageName
     * @param int|null $page
     * @return \Illuminate\Pagination\Paginator
     */
    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return $this->rememberResult(__FUNCTION__, func_get_args(), fn () => parent::simplePaginate($perPage, $columns, $pageName, $page));
    }

    /**
     * @param int|null $perPage
     * @param array|string $columns
     * @param string $cursorName
     * @param \Illuminate\Pagination\Cursor|string|null $cursor
     * @return \Illuminate\Pagination\CursorPaginator
     */
    public function cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        return $this->rememberResult(__FUNCTION__, func_get_args(), fn () => parent::cursorPaginate($perPage, $columns, $cursorName, $cursor));
    }

    /**
     * @param string $columns
     * @return int
     */
    public function count($columns = '*')
    {
        return $this->rememberResult(__FUNCTION__, func_get_args(), fn () => parent::count($columns));
    }

    /**
     * @return bool
     */
    public function exists()
    {
        return $this->rememberResult(__FUNCTION__, [], fn () => parent::exists());
    }

    /**
     * @return bool
     */
    public function doesntExist()
    {
        return $this->rememberResult(__FUNCTION__, [], fn () => parent::doesntExist());
    }

    /**
     * Wrap a terminal method in Cache::remember when caching is enabled.
     * Requires a PendingFilterScope to be registered (via ->filter()) to generate the cache key.
     * @param string $method
     * @param array $args
     * @param Closure $execute
     * @return mixed
     */
    private function rememberResult(string $method, array $args, Closure $execute): mixed
    {
        /** @var PendingFilterScope|null $scope */
        $scope = $this->scopes['_filterable_filter'] ?? null;

        if (!$this->useCache || $scope === null || $this->inCachedExecution) {
            return $execute();
        }

        $this->inCachedExecution = true;

        try {
            $filterable = $scope->filterable;

            $key = app(CacheKeyGenerator::class)->generate(
                get_class($filterable),
                $this->getModel()->getTable(),
                $scope->raw,
                $method,
                $args,
            );

            $tags = $filterable->getCacheTags() ?: [$this->getModel()->getTable()];

            return Cache::tags($tags)->remember(
                $key,
                $this->cacheTtl ?? $filterable->getCacheTtl(),
                $execute,
            );
        } finally {
            $this->inCachedExecution = false;
        }
    }
}