<?php

namespace Jurager\Filterable\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Jurager\Filterable\Scopes\PendingFilterScope;
use Jurager\Filterable\Scopes\PendingSortScope;

class FilterableBuilder extends Builder
{
    private bool $cacheEnabled = false;
    private ?int $cacheTtl = null;

    public function getFilterScope(): ?PendingFilterScope
    {
        return $this->scopes['_filterable_filter'] ?? null;
    }

    public function getSortScope(): ?PendingSortScope
    {
        return $this->scopes['_filterable_sort'] ?? null;
    }

    public function enableCache(?int $ttl = null): static
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;

        return $this;
    }

    public function get($columns = ['*']): Collection
    {
        if (!$this->cacheEnabled) {
            return parent::get($columns);
        }

        $model  = $this->getModel();
        $config = method_exists($model, 'filterableCacheConfig') ? $model->filterableCacheConfig() : [];
        $tags   = $config['tags'] ?? [$model->getTable()];
        $ttl    = $this->cacheTtl ?? $config['ttl'] ?? 3600;

        $key = 'filterable:' . hash('xxh3', serialize([
            get_class($model),
            $this->getQuery()->toSql(),
            $this->getQuery()->getBindings(),
            $this->getFilterScope()?->raw,
            $this->getSortScope()?->sort,
        ]));

        return Cache::tags($tags)->remember($key, $ttl, fn () => parent::get($columns));
    }
}
