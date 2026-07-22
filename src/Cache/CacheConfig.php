<?php

declare(strict_types=1);

namespace Jurager\Filterable\Cache;

use Illuminate\Database\Eloquent\Model;

/** Extract filterable cache configuration from a model. */
final class CacheConfig
{
    /**
     * Get the cache configuration for the given model.
     *
     * @return array{enabled?: bool, ttl?: int, tags?: array<int, string>}
     */
    public static function for(Model $model): array
    {
        if (! method_exists($model, 'filterableCacheConfig')) {
            return [];
        }

        return $model->filterableCacheConfig();
    }
}