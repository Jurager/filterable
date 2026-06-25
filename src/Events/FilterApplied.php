<?php

namespace Jurager\Filterable\Events;

use Illuminate\Database\Eloquent\Builder;
use Jurager\Filterable\Filterable;

class FilterApplied
{
    public function __construct(
        public readonly Filterable $filterable,
        public readonly Builder $query,
    ) {
    }
}
