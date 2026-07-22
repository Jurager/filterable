<?php

declare(strict_types=1);

namespace Jurager\Filterable\Cache;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Flush model cache tags to prevent stale filter results. */
class FilterableCacheObserver
{
    public function saved(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidate($model);
    }

    /** Invalidate the cache tags associated with the model. */
    protected function invalidate(Model $model): void
    {
        $tags = CacheConfig::for($model)['tags'] ?? [$model->getTable()];

        if (empty($tags)) {
            return;
        }

        try {
            Cache::tags($tags)->flush();
        } catch (BadMethodCallException) {
            // Ignore drivers that do not support tags
        }
    }
}