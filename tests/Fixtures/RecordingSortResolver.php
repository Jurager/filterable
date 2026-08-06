<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\SortResolver;

/** Captures the context handed to sort resolvers, applying no ordering of its own. */
class RecordingSortResolver implements SortResolver
{
    /** @var array<string, mixed>|null */
    public static ?array $seen = null;

    public function resolve(Builder $query, string $field, string $direction, Model $model, array $context = []): bool
    {
        if ($field !== 'recording_field') {
            return false;
        }

        self::$seen = $context;

        return true;
    }
}
