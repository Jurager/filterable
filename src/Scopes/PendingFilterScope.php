<?php

declare(strict_types=1);

namespace Jurager\Filterable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Jurager\Filterable\Filterable;

/** Defer filter application until the query executes. */
class PendingFilterScope implements Scope
{
    public function __construct(
        public readonly Filterable $filterable,
        public readonly array $raw,
    ) {
    }

    /** Apply the pending filters to the query builder. */
    public function apply(Builder $query, Model $model): void
    {
        $this->filterable->apply($query, $this->raw);
    }
}