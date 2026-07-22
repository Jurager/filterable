<?php

declare(strict_types=1);

namespace Jurager\Filterable\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Handle unrecognized sort fields for a model. */
interface SortResolver
{
    /** Attempt to resolve and apply a custom sort, returning true if handled. */
    public function resolve(Builder $query, string $field, string $direction, Model $model): bool;
}