<?php

namespace Jurager\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface SortResolverInterface
{
    /**
     * Handle a sort field not listed in $sortable.
     *
     * Return true if the sort was applied, false to pass to the next resolver.
     */
    public function resolve(Builder $query, string $field, string $direction, Model $model): bool;
}
