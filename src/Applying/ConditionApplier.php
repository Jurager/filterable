<?php

namespace Jurager\Filterable\Applying;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Jurager\Filterable\Contracts\FieldResolverInterface;
use Jurager\Filterable\Contracts\RelationResolverInterface;
use Jurager\Filterable\Support\FilterOperator;
use Jurager\Filterable\Support\ParsedFilters;

/**
 * Applies parsed filter conditions to an eloquent query builder.
 */
class ConditionApplier
{
    /**
     * @param int $maxInValues Maximum values allowed in a single in/nin filter.
     * @param FieldResolverInterface[] $fieldResolvers
     * @param RelationResolverInterface[] $relationResolvers
     * @param Closure|null $subclassHook Signature: (Builder, string $name, mixed $value): bool
     */
    public function __construct(
        private readonly int $maxInValues = 500,
        private readonly array $fieldResolvers = [],
        private readonly array $relationResolvers = [],
        private readonly ?Closure $subclassHook = null,
    ) {
    }

    /**
     * Apply all parsed filter conditions to the query builder.
     * @param Builder $query
     * @param ParsedFilters $parsed
     * @param Model $model
     * @return void
     */
    public function apply(Builder $query, ParsedFilters $parsed, Model $model): void
    {
        $this->applyFilters($query, $parsed->filters, $parsed->allowed, $model);

        if ($parsed->orGroups) {
            $query->where(function (Builder $q) use ($parsed, $model): void {
                foreach ($parsed->orGroups as $i => $group) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}(fn (Builder $sub) => $this->applyFilters($sub, $group, $parsed->allowed, $model));
                }
            });
        }

        foreach ($parsed->andGroups as $group) {
            $query->where(function (Builder $q) use ($group, $parsed, $model): void {
                $first = true;
                foreach ($group as $name => $value) {
                    if ($value === null) {
                        continue;
                    }
                    $method = $first ? 'where' : 'orWhere';
                    $first  = false;
                    $q->{$method}(fn (Builder $sub) => $this->applySingleFilter($sub, (string) $name, $value, $parsed->allowed, $model));
                }
            });
        }
    }

    /**
     * Apply a flat set of field → value filters to the query.
     * @param Builder $query
     * @param array $filters
     * @param array $allowed
     * @param Model $model
     * @return void
     */
    private function applyFilters(Builder $query, array $filters, array $allowed, Model $model): void
    {
        foreach ($filters as $name => $value) {
            if ($value !== null) {
                $this->applySingleFilter($query, (string) $name, $value, $allowed, $model);
            }
        }
    }

    /**
     * Route a single filter to the appropriate handler.
     * Priority: unknown fields → subclass hook → resolvers → relation → boolean → tree → operators.
     * @param Builder $query
     * @param string $name
     * @param mixed $value
     * @param array $allowed
     * @param Model $model
     * @return void
     */
    private function applySingleFilter(Builder $query, string $name, mixed $value, array $allowed, Model $model): void
    {
        if (!isset($allowed[$name])) {
            str_contains($name, '.')
                ? $this->delegateRelationField($query, $name, $value, $model)
                : $this->delegatePlainField($query, $name, $value, $model);

            return;
        }

        $config = $allowed[$name];

        if (str_contains($name, '.')) {
            $this->applyRelationFilter($query, $name, $config, $value, $model);

            return;
        }

        if ($config === 'boolean' || in_array($model->getCasts()[$name] ?? '', ['bool', 'boolean'], true)) {
            $raw  = is_array($value) ? ($value['eq'] ?? null) : $value;
            $cast = $raw !== null ? filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
            if ($cast !== null) {
                $query->where($name, $cast);
            }

            return;
        }

        $operators = is_array($config) ? $config : ['eq'];

        if (in_array('tree', $operators, true) && is_array($value) && array_key_exists('tree', $value)) {
            $this->applyDirectTreeFilter($query, $value['tree']);

            return;
        }

        $this->applyOperators($query, is_string($config) ? $config : $name, $operators, $value);
    }

    /**
     * Delegate an unknown plain field to the subclass hook, then field resolvers.
     * @param Builder $query
     * @param string $name
     * @param mixed $value
     * @param Model $model
     * @return void
     */
    private function delegatePlainField(Builder $query, string $name, mixed $value, Model $model): void
    {
        if ($this->subclassHook !== null && ($this->subclassHook)($query, $name, $value)) {
            return;
        }

        foreach ($this->fieldResolvers as $resolver) {
            if ($resolver->resolve($query, $name, $value, $model)) {
                return;
            }
        }
    }

    /**
     * Delegate an unknown dotted field to relation resolvers.
     * @param Builder $query
     * @param string $name
     * @param mixed $value
     * @param Model $model
     * @return void
     */
    private function delegateRelationField(Builder $query, string $name, mixed $value, Model $model): void
    {
        foreach ($this->relationResolvers as $resolver) {
            if ($resolver->resolveRelation($query, $name, $value, $model)) {
                return;
            }
        }
    }

    /**
     * Apply a dotted-name filter through whereHas or a pivot subquery.
     * @param Builder $query
     * @param string $name
     * @param array|string $config
     * @param mixed $value
     * @param Model $model
     * @return void
     */
    private function applyRelationFilter(Builder $query, string $name, array|string $config, mixed $value, Model $model): void
    {
        $parts        = explode('.', $name);
        $column       = array_pop($parts);
        $relationName = $parts[0];

        if (!preg_match('/^\w+$/', $relationName)) {
            return;
        }

        $operators = is_array($config) ? $config : ['eq'];

        if (is_array($value) && array_key_exists('tree', $value) && in_array('tree', $operators, true)) {
            $this->applyTreeFilter($query, $relationName, $value['tree']);

            return;
        }

        $pivotRelation = $this->resolvePivotRelation($model, $relationName);

        if (count($parts) > 1 && $parts[1] === 'pivot') {
            $this->applyPivotFilter($query, $column, $operators, $value, $pivotRelation);

            return;
        }

        if ($pivotRelation && in_array($column, [
            $pivotRelation->getForeignPivotKeyName(),
            $pivotRelation->getRelatedPivotKeyName(),
        ], true)) {
            $this->applyPivotFilter($query, $column, $operators, $value, $pivotRelation);

            return;
        }

        $query->whereHas(implode('.', $parts), function (Builder $q) use ($column, $operators, $value): void {
            $this->applyOperators($q, $column, $operators, $value);
        });
    }

    /**
     * Apply a tree filter directly on the model (no relation).
     * @param Builder $query
     * @param mixed $value
     * @return void
     */
    private function applyDirectTreeFilter(Builder $query, mixed $value): void
    {
        $ids = $this->parseIds($value);

        if (empty($ids) || !method_exists($query, 'whereDescendantOrSelf')) {
            return;
        }

        $this->constrainToDescendants($query, $ids);
    }

    /**
     * Apply a tree filter through a named relation.
     * @param Builder $query
     * @param string $relation
     * @param mixed $value
     * @return void
     */
    private function applyTreeFilter(Builder $query, string $relation, mixed $value): void
    {
        $ids = $this->parseIds(is_array($value) && isset($value['in']) ? $value['in'] : $value);

        if (empty($ids)) {
            return;
        }

        $query->whereHas($relation, fn (Builder $q) => $this->constrainToDescendants($q, $ids));
    }

    /**
     * Constrain a query to descendants of the given node IDs.
     * @param Builder $query
     * @param array $ids
     * @return void
     */
    private function constrainToDescendants(Builder $query, array $ids): void
    {
        $query->where(function (Builder $q) use ($ids): void {
            foreach (array_values($ids) as $i => $id) {
                $q->whereDescendantOrSelf($id, $i === 0 ? 'and' : 'or');
            }
        });
    }

    /**
     * Apply a filter condition through a pivot table subquery.
     * @param Builder $query
     * @param string $column
     * @param array $operators
     * @param mixed $value
     * @param BelongsToMany|MorphToMany|null $rel
     * @return void
     */
    private function applyPivotFilter(
        Builder $query,
        string $column,
        array $operators,
        mixed $value,
        BelongsToMany|MorphToMany|null $rel,
    ): void {
        if (!$rel) {
            return;
        }

        $table           = $rel->getTable();
        $parentTable     = $rel->getParent()->getTable();
        $qualifiedColumn = "$table.$column";

        $query->whereExists(function (QueryBuilder $sub) use ($rel, $table, $parentTable, $qualifiedColumn, $operators, $value): void {
            $sub->selectRaw('1')
                ->from($table)
                ->whereColumn("$table.{$rel->getForeignPivotKeyName()}", "$parentTable.{$rel->getParentKeyName()}");

            if ($rel instanceof MorphToMany) {
                $sub->where("$table.{$rel->getMorphType()}", $rel->getMorphClass());
            }

            $this->applyOperators($sub, $qualifiedColumn, $operators, $value);
        });
    }

    /**
     * Resolve a BelongsToMany or MorphToMany relation by name, or return null.
     * @param Model $model
     * @param string $name
     * @return BelongsToMany|MorphToMany|null
     */
    private function resolvePivotRelation(Model $model, string $name): BelongsToMany|MorphToMany|null
    {
        if (!method_exists($model, $name)) {
            return null;
        }

        $rel = $model->{$name}();

        return $rel instanceof BelongsToMany ? $rel : null;
    }

    /**
     * Dispatch operator-keyed filter values to the corresponding query constraints.
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param array $allowed
     * @param mixed $value
     * @return void
     */
    private function applyOperators(Builder|QueryBuilder $query, string $column, array $allowed, mixed $value): void
    {
        if (!preg_match('/^\w+(\.\w+)*$/', $column)) {
            return;
        }

        if (!is_array($value) || array_is_list($value)) {
            is_array($value)
                ? $query->whereIn($column, $this->sanitizeList($value))
                : $this->applyScalarCondition($query, $column, '=', $value);

            return;
        }

        $toList = fn ($v) => $this->sanitizeList(is_array($v) ? $v : explode(',', (string) $v));

        foreach ($value as $alias => $operand) {
            $op = FilterOperator::fromAlias((string) $alias);

            abort_unless($op !== null && in_array($op->value, $allowed, true), 400, "Filter operator '$alias' is not allowed for this field.");

            match ($op) {
                FilterOperator::Eq         => $this->applyScalarCondition($query, $column, '=', $operand),
                FilterOperator::Ne         => $this->applyScalarCondition($query, $column, '!=', $operand),
                FilterOperator::Gt         => $query->where($column, '>', $operand),
                FilterOperator::Gte        => $query->where($column, '>=', $operand),
                FilterOperator::Lt         => $query->where($column, '<', $operand),
                FilterOperator::Lte        => $query->where($column, '<=', $operand),
                FilterOperator::Like       => $this->applyLike($query, $column, $operand),
                FilterOperator::In         => $query->whereIn($column, $toList($operand)),
                FilterOperator::Nin        => $query->whereNotIn($column, $toList($operand)),
                FilterOperator::IsNull     => $query->whereNull($column),
                FilterOperator::IsNotNull  => $query->whereNotNull($column),
                FilterOperator::Between    => $this->applyBetween($query, $column, $operand, false),
                FilterOperator::NotBetween => $this->applyBetween($query, $column, $operand, true),
                FilterOperator::Tree       => null,
            };
        }
    }

    /**
     * Apply a scalar equality/inequality condition, handling null coercion.
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param string $sqlOp
     * @param mixed $value
     * @return void
     */
    private function applyScalarCondition(Builder|QueryBuilder $query, string $column, string $sqlOp, mixed $value): void
    {
        if ($value === null || (is_string($value) && strtolower($value) === 'null')) {
            $sqlOp === '!=' ? $query->whereNotNull($column) : $query->whereNull($column);

            return;
        }

        $query->where($column, $sqlOp, $value);
    }

    /**
     * Apply a LIKE filter, supporting multiple values as OR conditions.
     * Uses ILIKE with ICU collation on PostgreSQL for Unicode-aware matching.
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param mixed $value
     * @return void
     */
    private function applyLike(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = match (true) {
            is_string($value) => [$value],
            is_array($value)  => array_values(array_filter($value, 'is_string')),
            default           => [],
        };

        if (empty($values)) {
            return;
        }

        if (count($values) === 1) {
            $this->applyLikeCondition($query, $column, $values[0]);

            return;
        }

        $query->where(function (Builder $sub) use ($column, $values): void {
            foreach ($values as $i => $val) {
                $i === 0
                    ? $this->applyLikeCondition($sub, $column, $val)
                    : $sub->orWhere(fn ($q) => $this->applyLikeCondition($q, $column, $val));
            }
        });
    }

    /**
     * Apply a single LIKE condition to the query.
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param string $value
     * @return void
     */
    private function applyLikeCondition(Builder|QueryBuilder $query, string $column, string $value): void
    {
        if ($query->getConnection()->getDriverName() === 'pgsql') {

            $grammar = $query->getConnection()->getQueryGrammar();

            // Unicode-aware case folding
            $query->whereRaw($grammar->wrap($column).' COLLATE "und-x-icu" ILIKE ?', ['%'.$value.'%']);

            return;
        }

        $query->whereLike($column, $value);
    }

    /**
     * Apply a BETWEEN or NOT BETWEEN condition. Aborts with 400 if operand is not exactly 2 values.
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param mixed $operand
     * @param bool $not
     * @return void
     */
    private function applyBetween(Builder|QueryBuilder $query, string $column, mixed $operand, bool $not): void
    {
        if (!is_array($operand)) {
            $operand = Str::of($operand)->explode(',')->map(trim(...))->all();
        }

        abort_if(count($operand) !== 2, 400, 'Operator \''.($not ? 'not_between' : 'between').'\' requires exactly 2 values.');

        $not
            ? $query->whereNotBetween($column, $operand)
            : $query->whereBetween($column, $operand);
    }

    /**
     * Strip empty values from a list and abort if it exceeds maxInValues.
     * @param array $values
     * @return array
     */
    private function sanitizeList(array $values): array
    {
        $list = array_values(array_filter($values, static fn ($v) => $v !== '' && $v !== null));

        abort_if(count($list) > $this->maxInValues, 400, "Filter list exceeds maximum of $this->maxInValues values.");

        return $list;
    }

    /**
     * Parse a value into a list of positive integer IDs.
     * @param mixed $value
     * @return array
     */
    private function parseIds(mixed $value): array
    {
        $raw = is_array($value) ? $value : explode(',', (string) $value);
        $ids = [];

        foreach ($raw as $v) {
            $id = (int) trim((string) $v);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
