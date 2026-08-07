<?php

declare(strict_types=1);

namespace Jurager\Filterable\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Handle unrecognized sort fields for a model. */
interface SortResolver
{
    /**
     * Attempt to resolve and apply a custom sort, returning true if handled.
     *
     * @param array<string, mixed> $context Request-scoped values passed to sort, for orderings that depend on data outside the model.
     */
    public function resolve(object $query, string $field, string $direction, Model $model, array $context = []): bool;
}
