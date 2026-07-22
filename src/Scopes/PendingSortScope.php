<?php

declare(strict_types=1);

namespace Jurager\Filterable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Jurager\Filterable\Filterable;

/** Defer sort application until the query executes. */
class PendingSortScope implements Scope
{
    public function __construct(
        public readonly Filterable $filterable,
        public readonly ?string $sort,
    ) {
    }

    /** Apply the pending sort to the query builder. */
    public function apply(Builder $query, Model $model): void
    {
        $this->filterable->sort($query, $this->sort);
    }
}