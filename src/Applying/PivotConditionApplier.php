<?php

declare(strict_types=1);

namespace Jurager\Filterable\Applying;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Throwable;

/** Apply filter conditions to BelongsToMany/MorphToMany pivot columns. */
class PivotConditionApplier
{
    public function __construct(
        private readonly OperatorApplier $operators,
    ) {
    }

    /** Resolve a BelongsToMany or MorphToMany relation by name. */
    public function resolve(Model $model, string $name): ?BelongsToMany
    {
        if (! $model->isRelation($name)) {
            return null;
        }

        try {
            $relation = $model->{$name}();
        } catch (Throwable) {
            return null;
        }

        return $relation instanceof BelongsToMany ? $relation : null;
    }

    /** Determine if a filter path targets a pivot column on the relation. */
    public function matches(BelongsToMany $relation, array $parts, string $column): bool
    {
        if (count($parts) > 1 && $parts[1] === 'pivot') {
            return true;
        }

        $pivotKeys = [
            $relation->getForeignPivotKeyName(),
            $relation->getRelatedPivotKeyName(),
        ];

        return in_array($column, $pivotKeys, true);
    }

    /** Apply a filter condition through a pivot table subquery. */
    public function apply(Builder $query, string $column, array $operators, mixed $value, BelongsToMany $relation): void
    {
        $table = $relation->getTable();
        $parentTable = $relation->getParent()->getTable();
        $qualifiedColumn = "{$table}.{$column}";

        $query->whereExists(function (QueryBuilder $sub) use ($relation, $table, $parentTable, $qualifiedColumn, $operators, $value): void {
            $sub->selectRaw('1')
                ->from($table)
                ->whereColumn(
                    "{$table}.{$relation->getForeignPivotKeyName()}",
                    "{$parentTable}.{$relation->getParentKeyName()}"
                );

            if ($relation instanceof MorphToMany) {
                $sub->where("{$table}.{$relation->getMorphType()}", $relation->getMorphClass());
            }

            $this->operators->apply($sub, $qualifiedColumn, $operators, $value);
        });
    }
}