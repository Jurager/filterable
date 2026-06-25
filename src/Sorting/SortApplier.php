<?php

namespace Jurager\Filterable\Sorting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\SortResolverInterface;

/**
 * Applies a JSON:API sort string to an Eloquent query.
 * Format: sort=name,-created_at (comma-separated, "-" prefix = DESC).
 */
class SortApplier
{
    /**
     * @param SortResolverInterface[] $resolvers
     */
    public function __construct(
        private readonly array $resolvers = [],
    ) {
    }

    /**
     * Apply sort string to the query builder.
     * @param Builder $query
     * @param string $sort
     * @param array $sortable
     * @param Model $model
     * @return Builder
     */
    public function apply(Builder $query, string $sort, array $sortable, Model $model): Builder
    {
        $allowed = $this->resolveSortable($sortable);

        foreach (explode(',', $sort) as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $desc      = str_starts_with($segment, '-');
            $col       = $desc ? substr($segment, 1) : $segment;
            $direction = $desc ? 'desc' : 'asc';

            if (!preg_match('/^\w+(\.\w+)*$/', $col)) {
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

    /**
     * Normalise $sortable to an alias → column map.
     * Plain entries ('id') map to themselves; keyed entries ('date' => 'created_at') map alias to column.
     * @param array $sortable
     * @return array<string, string>
     */
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

    /**
     * Delegate an unknown sort field to registered resolvers.
     * @param Builder $query
     * @param string $field
     * @param string $direction
     * @param Model $model
     * @return void
     */
    private function delegateUnknown(Builder $query, string $field, string $direction, Model $model): void
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->resolve($query, $field, $direction, $model)) {
                return;
            }
        }
    }
}
