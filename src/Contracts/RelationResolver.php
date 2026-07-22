<?php

declare(strict_types=1);

namespace Jurager\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Handle unrecognized dotted relation filter keys for a model. */
interface RelationResolver
{
    /** Attempt to resolve and apply a custom relation filter, returning true if handled. */
    public function resolveRelation(Builder $query, string $name, mixed $value, Model $model): bool;
}