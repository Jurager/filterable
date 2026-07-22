<?php

declare(strict_types=1);

namespace Jurager\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Handle unrecognized filter keys for a model. */
interface FieldResolver
{
    /** Attempt to resolve and apply a custom filter, returning true if handled. */
    public function resolve(Builder $query, string $name, mixed $value, Model $model): bool;
}