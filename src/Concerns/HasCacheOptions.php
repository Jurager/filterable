<?php

declare(strict_types=1);

namespace Jurager\Filterable\Concerns;

/** Provide cache configuration options for filterable classes. */
trait HasCacheOptions
{
    /**
     * Cache configuration options.
     *
     * @var array{enabled?: bool, ttl?: int, tags?: array<int, string>}
     */
    protected array $cache = [];

    /** Determine if caching is enabled. */
    public function isCacheEnabled(): bool
    {
        return $this->cache['enabled'] ?? false;
    }
}