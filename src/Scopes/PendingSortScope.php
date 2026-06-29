<?php

namespace Jurager\Filterable\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Jurager\Filterable\Filterable;

class PendingSortScope implements Scope
{
    public function __construct(
        public readonly Filterable $filterable,
        public readonly ?string $sort,
    ) {}

    public function apply(Builder $query, Model $model): void
    {
        $this->filterable->sort($query, $this->sort);
    }
}
