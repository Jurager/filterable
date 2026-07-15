<?php

namespace Jurager\Filterable\Applying;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Jurager\Filterable\Exceptions\InvalidBetweenOperandException;
use Jurager\Filterable\Exceptions\OperatorNotAllowedException;
use Jurager\Filterable\Exceptions\TooManyValuesException;
use Jurager\Filterable\Support\FilterOperator;
use Jurager\Filterable\Support\ParsedFilters;
use Throwable;

class ConditionApplier
{
    /**
     * Matches a single unqualified identifier.
     */
    private const string IDENTIFIER = '/^\w+$/';

    /**
     * Matches an optionally dot-qualified identifier.
     */
    private const string QUALIFIED_IDENTIFIER = '/^\w+(\.\w+)*$/';

    public function __construct(
        private readonly int $maxInValues = 500,
        private readonly array $fieldResolvers = [],
        private readonly array $relationResolvers = [],
    ) {
    }

    /**
     * Apply all parsed filter conditions to the query builder.
     *
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
     * Apply a flat set of field and value filters to the query.
     *
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
     *
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
            $this->delegate($query, $name, $value, $model);

            return;
        }

        $config    = $allowed[$name];
        $operators = $this->operatorsFor($config);

        if (str_contains($name, '.')) {
            $this->applyRelationFilter($query, $name, $operators, $value, $model);

            return;
        }

        if ($this->isBooleanField($name, $config, $model)) {
            $this->applyBooleanFilter($query, $name, $value);

            return;
        }

        if ($this->isTreeRequest($operators, $value)) {
            $this->applyDirectTreeFilter($query, $value['tree'], $model);

            return;
        }

        $this->applyOperators($query, is_string($config) ? $config : $name, $operators, $value);
    }

    /**
     * Delegate an unrecognized field to the registered resolvers, in order.
     *
     * @param Builder $query
     * @param string $name
     * @param mixed $value
     * @param Model $model
     * @return void
     */
    private function delegate(Builder $query, string $name, mixed $value, Model $model): void
    {
        if (str_contains($name, '.')) {

            if (array_any($this->relationResolvers, fn($resolver) => $resolver->resolveRelation($query, $name, $value, $model))) {
                return;
            }

            return;
        }

        if (array_any($this->fieldResolvers, fn($resolver) => $resolver->resolve($query, $name, $value, $model))) {
            return;
        }
    }

    /**
     * Normalize a field config into a list of allowed operators.
     *
     * @param array|string $config
     * @return array
     */
    private function operatorsFor(array|string $config): array
    {
        return is_array($config) ? $config : ['eq'];
    }

    /**
     * Determine whether a field should be treated as a boolean.
     *
     * @param string $name
     * @param array|string $config
     * @param Model $model
     * @return bool
     */
    private function isBooleanField(string $name, array|string $config, Model $model): bool
    {
        return $config === 'boolean' || in_array($model->getCasts()[$name] ?? '', ['bool', 'boolean'], true);
    }

    /**
     * Determine whether the value is a tree request and the field permits it.
     *
     * @param array $operators
     * @param mixed $value
     * @return bool
     */
    private function isTreeRequest(array $operators, mixed $value): bool
    {
        return is_array($value) && array_key_exists('tree', $value) && in_array('tree', $operators, true);
    }

    /**
     * Apply a boolean equality filter, ignoring values that are not coercible.
     *
     * @param Builder $query
     * @param string $name
     * @param mixed $value
     * @return void
     */
    private function applyBooleanFilter(Builder $query, string $name, mixed $value): void
    {
        $raw = is_array($value) ? ($value['eq'] ?? null) : $value;

        if ($raw === null || is_array($raw)) {
            return;
        }

        $cast = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($cast !== null) {
            $query->where($name, $cast);
        }
    }

    /**
     * Apply a dotted-name filter through whereHas or a pivot subquery.
     *
     * @param Builder $query
     * @param string $name
     * @param array $operators
     * @param mixed $value
     * @param Model $model
     * @return void
     */
    private function applyRelationFilter(Builder $query, string $name, array $operators, mixed $value, Model $model): void
    {
        $parts  = explode('.', $name);
        $column = array_pop($parts);

        if (array_any($parts, fn($part) => !preg_match(self::IDENTIFIER, $part))) {
            return;
        }

        $relationName = $parts[0];

        if ($this->isTreeRequest($operators, $value)) {
            $this->applyTreeFilter($query, $relationName, $value['tree']);

            return;
        }

        $pivotRelation = $this->resolvePivotRelation($model, $relationName);

        $isPivot = $pivotRelation && (
                (count($parts) > 1 && $parts[1] === 'pivot')
                || in_array($column, [
                    $pivotRelation->getForeignPivotKeyName(),
                    $pivotRelation->getRelatedPivotKeyName(),
                ], true)
            );

        if ($isPivot) {
            $this->applyPivotFilter($query, $column, $operators, $value, $pivotRelation);

            return;
        }

        $query->whereHas(implode('.', $parts), function (Builder $q) use ($column, $operators, $value): void {
            $this->applyOperators($q, $column, $operators, $value);
        });
    }

    /**
     * Apply a tree filter directly on the model (no relation).
     *
     * @param Builder $query
     * @param mixed $value
     * @param Model $model
     * @return void
     */
    private function applyDirectTreeFilter(Builder $query, mixed $value, Model $model): void
    {
        $ids = $this->parseIds($value);

        if (!$ids || !$this->supportsTree($model)) {
            return;
        }

        $this->constrainToDescendants($query, $ids);
    }

    /**
     * Apply a tree filter through a named relation.
     *
     * @param Builder $query
     * @param string $relation
     * @param mixed $value
     * @return void
     */
    private function applyTreeFilter(Builder $query, string $relation, mixed $value): void
    {
        $ids = $this->parseIds(is_array($value) && isset($value['in']) ? $value['in'] : $value);

        if (!$ids) {
            return;
        }

        $query->whereHas($relation, fn (Builder $q) => $this->constrainToDescendants($q, $ids));
    }

    /**
     * Determine whether the model's builder exposes nested-set tree scopes.
     *
     * @param Model $model
     * @return bool
     */
    private function supportsTree(Model $model): bool
    {
        return method_exists($model, 'whereDescendantOrSelf') || method_exists($model, 'scopeWhereDescendantOrSelf') || Builder::hasGlobalMacro('whereDescendantOrSelf');
    }

    /**
     * Constrain a query to descendants of the given node IDs.
     *
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
     *
     * @param Builder $query
     * @param string $column
     * @param array $operators
     * @param mixed $value
     * @param BelongsToMany $rel
     * @return void
     */
    private function applyPivotFilter(Builder $query, string $column, array $operators, mixed $value, BelongsToMany $rel): void
    {
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
     *
     * @param Model $model
     * @param string $name
     * @return BelongsToMany|null
     */
    private function resolvePivotRelation(Model $model, string $name): ?BelongsToMany
    {
        if (!$model->isRelation($name)) {
            return null;
        }

        try {
            $rel = $model->{$name}();
        } catch (Throwable) {
            return null;
        }

        return $rel instanceof BelongsToMany ? $rel : null;
    }

    /**
     * Dispatch operator-keyed filter values to the corresponding query constraints.
     *
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param array $allowed
     * @param mixed $value
     * @return void
     */
    private function applyOperators(Builder|QueryBuilder $query, string $column, array $allowed, mixed $value): void
    {
        if (!preg_match(self::QUALIFIED_IDENTIFIER, $column)) {
            return;
        }

        if (!is_array($value) || array_is_list($value)) {
            is_array($value)
                ? $query->whereIn($column, $this->sanitizeList($value))
                : $this->applyScalarCondition($query, $column, '=', $value);

            return;
        }

        foreach ($value as $alias => $operand) {

            $op = FilterOperator::fromAlias((string) $alias);

            if ($op === null || !in_array($op->value, $allowed, true)) {
                throw new OperatorNotAllowedException((string) $alias);
            }

            match ($op) {
                FilterOperator::Eq         => $this->applyScalarCondition($query, $column, '=', $operand),
                FilterOperator::Ne         => $this->applyScalarCondition($query, $column, '!=', $operand),
                FilterOperator::Gt         => $this->applyComparison($query, $column, '>', $operand),
                FilterOperator::Gte        => $this->applyComparison($query, $column, '>=', $operand),
                FilterOperator::Lt         => $this->applyComparison($query, $column, '<', $operand),
                FilterOperator::Lte        => $this->applyComparison($query, $column, '<=', $operand),
                FilterOperator::Like       => $this->applyLike($query, $column, $operand),
                FilterOperator::In         => $query->whereIn($column, $this->toList($operand)),
                FilterOperator::Nin        => $query->whereNotIn($column, $this->toList($operand)),
                FilterOperator::IsNull     => $query->whereNull($column),
                FilterOperator::IsNotNull  => $query->whereNotNull($column),
                FilterOperator::Between    => $this->applyBetween($query, $column, $operand, false),
                FilterOperator::NotBetween => $this->applyBetween($query, $column, $operand, true),
                FilterOperator::Tree       => null,
            };
        }
    }

    /**
     * Apply a scalar comparison, ignoring non-scalar operands.
     *
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param string $sqlOp
     * @param mixed $operand
     * @return void
     */
    private function applyComparison(Builder|QueryBuilder $query, string $column, string $sqlOp, mixed $operand): void
    {
        if (is_scalar($operand)) {
            $query->where($column, $sqlOp, $operand);
        }
    }

    /**
     * Apply a scalar equality/inequality condition, handling null coercion.
     *
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

        if (is_scalar($value)) {
            $query->where($column, $sqlOp, $value);
        }
    }

    /**
     * Apply a LIKE filter, supporting multiple values as OR conditions.
     * Uses ILIKE with ICU collation on PostgreSQL for Unicode-aware matching.
     *
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

        if (!$values) {
            return;
        }

        if (count($values) === 1) {
            $this->applyLikeCondition($query, $column, $values[0]);

            return;
        }

        $query->where(function (Builder|QueryBuilder $sub) use ($column, $values): void {
            foreach ($values as $i => $val) {
                $i === 0
                    ? $this->applyLikeCondition($sub, $column, $val)
                    : $sub->orWhere(fn (Builder|QueryBuilder $q) => $this->applyLikeCondition($q, $column, $val));
            }
        });
    }

    /**
     * Apply a single LIKE condition to the query.
     *
     * @param Builder|QueryBuilder $query
     * @param string $column
     * @param string $value
     * @return void
     */
    private function applyLikeCondition(Builder|QueryBuilder $query, string $column, string $value): void
    {
        $connection = $query->getConnection();

        if ($connection->getDriverName() === 'pgsql') {

            // Unicode-aware case folding
            $wrapped = $connection->getQueryGrammar()->wrap($column);

            $query->whereRaw("$wrapped COLLATE \"und-x-icu\" ILIKE ?", ['%'.$value.'%']);

            return;
        }

        $query->whereLike($column, "%$value%");
    }

    /**
     * Apply a BETWEEN or NOT BETWEEN condition. Aborts with 400 if operand is not exactly 2 values.
     *
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

        $operand = array_values($operand);

        if (count($operand) !== 2) {
            throw new InvalidBetweenOperandException($not ? 'not_between' : 'between');
        }

        $not
            ? $query->whereNotBetween($column, $operand)
            : $query->whereBetween($column, $operand);
    }

    /**
     * Normalize an operand into a sanitized list of values.
     *
     * @param mixed $operand
     * @return array
     */
    private function toList(mixed $operand): array
    {
        return $this->sanitizeList(is_array($operand) ? $operand : explode(',', (string) $operand));
    }

    /**
     * Strip empty values from a list and abort if it exceeds max values.
     *
     * @param array $values
     * @return array
     */
    private function sanitizeList(array $values): array
    {
        $list = array_values(array_filter($values, static fn ($v) => $v !== '' && $v !== null));

        if (count($list) > $this->maxInValues) {
            throw new TooManyValuesException($this->maxInValues);
        }

        return $list;
    }

    /**
     * Parse a value into a list of positive integer IDs.
     *
     * @param mixed $value
     * @return array
     */
    private function parseIds(mixed $value): array
    {
        $ids = [];
        $raw = is_array($value) ? $value : explode(',', (string) $value);

        foreach ($raw as $v) {
            if (!is_scalar($v)) {
                continue;
            }

            $id = (int) trim((string) $v);

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}