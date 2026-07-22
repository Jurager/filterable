<?php

declare(strict_types=1);

namespace Jurager\Filterable\Applying;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Apply the `tree` operator to expand a filter into a nested-set descendant scope. */
class TreeConditionApplier
{
    /** Determine whether the value is a tree request and the field permits it. */
    public function isTreeRequest(array $operators, mixed $value): bool
    {
        return is_array($value) && isset($value['tree']) && in_array('tree', $operators, true);
    }

    /** Apply a tree filter directly on the model. */
    public function applyDirect(Builder $query, mixed $value, Model $model): void
    {
        $ids = $this->parseIds($value);

        if (empty($ids) || ! $this->supports($model)) {
            return;
        }

        $this->constrainToDescendants($query, $ids);
    }

    /** Apply a tree filter through a named relation. */
    public function applyThroughRelation(Builder $query, string $relation, mixed $value): void
    {
        $raw = (is_array($value) && isset($value['in'])) ? $value['in'] : $value;
        $ids = $this->parseIds($raw);

        if (empty($ids)) {
            return;
        }

        $query->whereHas($relation, fn (Builder $q) => $this->constrainToDescendants($q, $ids));
    }

    /** Determine whether the model's builder exposes nested-set tree scopes. */
    private function supports(Model $model): bool
    {
        return method_exists($model, 'whereDescendantOrSelf')
            || method_exists($model, 'scopeWhereDescendantOrSelf')
            || Builder::hasGlobalMacro('whereDescendantOrSelf');
    }

    /** Constrain a query to descendants of the given node IDs. */
    private function constrainToDescendants(Builder $query, array $ids): void
    {
        $query->where(function (Builder $q) use ($ids): void {
            $first = true;

            foreach ($ids as $id) {
                $q->whereDescendantOrSelf($id, $first ? 'and' : 'or');
                $first = false;
            }
        });
    }

    /** Parse a value into a list of positive integer IDs. */
    private function parseIds(mixed $value): array
    {
        $ids = [];
        $raw = is_array($value) ? $value : explode(',', (string) $value);

        foreach ($raw as $v) {
            if (! is_scalar($v)) {
                continue;
            }

            $id = (int) $v;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}