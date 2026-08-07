<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\FieldResolver;
use Jurager\Filterable\Contracts\SortResolver;

/**
 * Implements both contracts to prove one declared resolver can serve filtering and sorting.
 *
 * The two `resolve()` signatures line up only because the third parameter is widened to `mixed`:
 * FieldResolver passes the filter value there, SortResolver the direction.
 */
class CheapFieldResolver implements FieldResolver, SortResolver
{
    public function resolve(object $query, string $name, mixed $value, Model $model, array $context = []): bool
    {
        if ($name === 'cheap') {
            $query->where('price', '<', (float) $value);

            return true;
        }

        if ($name === 'cheapness') {
            $query->orderByRaw('price ' . ($value === 'desc' ? 'desc' : 'asc'));

            return true;
        }

        return false;
    }
}
