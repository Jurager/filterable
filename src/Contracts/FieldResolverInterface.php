<?php

namespace Jurager\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface FieldResolverInterface
{
    /**
     * Handle a plain filter key not declared in $filterable.
     *
     * Return true if the filter was applied, false to pass to the next resolver.
     */
    public function resolve(Builder $query, string $name, mixed $value, Model $model): bool;
}
