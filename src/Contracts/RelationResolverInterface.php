<?php

namespace Jurager\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface RelationResolverInterface
{
    /**
     * Handle a dotted filter key (relation.attribute) not declared in $filterable.
     *
     * Return true if the filter was applied, false to pass to the next resolver.
     */
    public function resolveRelation(Builder $query, string $name, mixed $value, Model $model): bool;
}
