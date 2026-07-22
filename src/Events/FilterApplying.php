<?php

declare(strict_types=1);

namespace Jurager\Filterable\Events;

use Illuminate\Database\Eloquent\Builder;
use Jurager\Filterable\Filterable;

/** Dispatched before filter conditions are applied to the query. */
class FilterApplying
{
    public function __construct(
        public readonly Filterable $filterable,
        public readonly Builder $query,
        public readonly array $filters,
    ) {
    }
}