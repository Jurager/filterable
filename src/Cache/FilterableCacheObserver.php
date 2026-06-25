<?php

namespace Jurager\Filterable\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Flushes tagged cache entries when a model is mutated.
 * Requires a tag-capable cache driver (Redis, Memcached).
 */
class FilterableCacheObserver
{
    /**
     * @param array $tags
     * @param string|null $store
     */
    public function __construct(
        private readonly array $tags,
        private readonly ?string $store = null,
    ) {
    }

    public function saved(Model $model): void
    {
        $this->flush();
    }

    public function deleted(Model $model): void
    {
        $this->flush();
    }

    public function restored(Model $model): void
    {
        $this->flush();
    }

    public function forceDeleted(Model $model): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        try {
            ($this->store ? Cache::store($this->store) : Cache::store())
                ->tags($this->tags)
                ->flush();
        } catch (\BadMethodCallException) {
            // Driver does not support tags — skip silently.
        }
    }
}
