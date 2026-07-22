<?php

declare(strict_types=1);

namespace Jurager\Filterable\Sorting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\SortResolver;

/** Apply a JSON:API formatted sort string to an Eloquent query. */
class SortApplier
{
    /**
     * @param array<int, SortResolver> $resolvers
     */
    public function __construct(
        private readonly array $resolvers = [],
    ) {
    }

    /** Apply the sort string to the query builder. */
    public function apply(Builder $query, string $sort, array $sortable, Model $model): Builder
    {
        $query->reorder();

        $allowed = $this->resolveSortable($sortable);

        foreach (explode(',', $sort) as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $desc      = str_starts_with($segment, '-');
            $col       = $desc ? substr($segment, 1) : $segment;
            $direction = $desc ? 'desc' : 'asc';

            if (! preg_match('/^\w+(\.\w+)*$/', $col)) {
                continue;
            }

            if (array_key_exists($col, $allowed)) {
                $query->orderBy($allowed[$col], $direction);
            } else {
                $this->delegateUnknown($query, $col, $direction, $model);
            }
        }

        return $query;
    }

    /** Normalize the sortable configuration to an alias-to-column map. */
    private function resolveSortable(array $sortable): array
    {
        $resolved = [];

        foreach ($sortable as $key => $value) {
            if (is_int($key)) {
                $resolved[$value] = $value;
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    /** Delegate an unknown sort field to the registered resolvers. */
    private function delegateUnknown(Builder $query, string $field, string $direction, Model $model): void
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->resolve($query, $field, $direction, $model)) {
                return;
            }
        }
    }
}