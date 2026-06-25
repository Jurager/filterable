<?php

namespace Jurager\Filterable\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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

    protected function invalidate(Model $model): void
    {
        if (!method_exists($model, 'filterableCacheConfig')) {
            return;
        }

        $config = $model->filterableCacheConfig();

        if (empty($config)) {
            return;
        }

        $tags = $config['tags'] ?? [$model->getTable()];

        if (empty($tags)) {
            return;
        }

        try {
            Cache::tags($tags)->flush();
        } catch (\BadMethodCallException) {
            // Driver does not support tags — skip silently.
        }
    }
}
