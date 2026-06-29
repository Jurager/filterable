<?php

namespace Jurager\Filterable\Query;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Jurager\Filterable\Cache\CacheKeyGenerator;
use Jurager\Filterable\Scopes\PendingFilterScope;
use Jurager\Filterable\Scopes\PendingSortScope;

class FilterableBuilder extends Builder
{
    private bool $cacheEnabled = false;
    private ?int $cacheTtl = null;

    public function enableCache(?int $ttl = null): void
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;
    }

    public function first($columns = ['*'])
    {
        return $this->cached('first', [$columns], fn () => parent::first($columns));
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        return $this->cached(
            'paginate',
            $this->pageArgs($perPage, $columns, $pageName, $page),
            fn () => parent::paginate($perPage, $columns, $pageName, $page, $total),
        );
    }

    public function simplePaginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        return $this->cached(
            'simplePaginate',
            $this->pageArgs($perPage, $columns, $pageName, $page),
            fn () => parent::simplePaginate($perPage, $columns, $pageName, $page),
        );
    }

    public function cursorPaginate($perPage = null, $columns = ['*'], $cursorName = 'cursor', $cursor = null)
    {
        $resolved = (string) ($cursor ?? CursorPaginator::resolveCurrentCursor($cursorName));

        return $this->cached(
            'cursorPaginate',
            [$perPage, $columns, $cursorName, $resolved],
            fn () => parent::cursorPaginate($perPage, $columns, $cursorName, $cursor),
        );
    }

    private function pageArgs($perPage, $columns, $pageName, $page): array
    {
        return [$perPage, $columns, $pageName, $page ?? Paginator::resolveCurrentPage($pageName)];
    }

    private function cached(string $method, array $args, Closure $execute): mixed
    {
        $scope = $this->scopes['_filterable_filter'] ?? null;

        if (! $scope instanceof PendingFilterScope) {
            return $execute();
        }

        if (! $this->cacheEnabled && ! config('filterable.cache.enabled', false)) {
            return $execute();
        }

        $filterable = $scope->filterable;
        $model = $this->getModel();

        $sortScope = $this->scopes['_filterable_sort'] ?? null;

        $key = app(CacheKeyGenerator::class)->generate(
            get_class($filterable), $model->getTable(), $scope->raw, $method, $args,
            $sortScope instanceof PendingSortScope ? $sortScope->sort : null,
        );

        return Cache::tags($filterable->getCacheTags() ?: [$model->getTable()])
            ->remember($key, $this->cacheTtl ?? $filterable->getCacheTtl(), $execute);
    }
}
