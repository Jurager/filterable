<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;

/**
 * Minimal stand-in for a nested-set query builder such as aimeos/laravel-nestedset's
 * `Aimeos\Nestedset\QueryBuilder`, which exposes `whereDescendantOrSelf` on the query
 * builder rather than on the model. Not a real nested-set implementation — just enough
 * to prove TreeConditionApplier detects and calls through to it.
 */
class TreeCategoryBuilder extends Builder
{
    public function whereDescendantOrSelf(int|string $id, string $boolean = 'and'): static
    {
        return $this->where('id', '=', $id, $boolean);
    }
}
