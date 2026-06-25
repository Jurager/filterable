<?php

namespace Jurager\Filterable\Concerns;

trait HasCacheOptions
{
    /**
     * @var array{enabled?: bool, ttl?: int, tags?: array}
     */
    protected array $cache = [];

    /**
     * @return bool
     */
    public function isCacheEnabled(): bool
    {
        return $this->cache['enabled'] ?? false;
    }

    /**
     * @return int
     */
    public function getCacheTtl(): int
    {
        return $this->cache['ttl'] ?? (int) config('filterable.cache.ttl', 3600);
    }

    /**
     * @return array
     */
    public function getCacheTags(): array
    {
        return $this->cache['tags'] ?? [];
    }
}
