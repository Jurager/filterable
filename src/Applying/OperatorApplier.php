<?php

declare(strict_types=1);

namespace Jurager\Filterable\Applying;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Jurager\Filterable\Exceptions\InvalidBetweenOperandException;
use Jurager\Filterable\Exceptions\OperatorNotAllowedException;
use Jurager\Filterable\Exceptions\TooManyValuesException;
use Jurager\Filterable\Support\FilterOperator;

/** Apply operator-based query constraints to a query builder. */
class OperatorApplier
{
    /** Matches an optionally dot-qualified identifier. */
    private const string QUALIFIED_IDENTIFIER = '/^\w+(\.\w+)*$/';

    public function __construct(
        private readonly int $maxInValues = 500,
    ) {
    }

    /** Dispatch operator-keyed filter values to the corresponding query constraints. */
    public function apply(Builder|QueryBuilder $query, string $column, array $allowed, mixed $value): void
    {
        if (! preg_match(self::QUALIFIED_IDENTIFIER, $column)) {
            return;
        }

        // Handle scalar values or flat arrays directly
        if (! is_array($value) || array_is_list($value)) {
            if (is_array($value)) {
                $query->whereIn($column, $this->sanitizeList($value));
            } else {
                $this->applyScalarCondition($query, $column, '=', $value);
            }

            return;
        }

        foreach ($value as $alias => $operand) {
            $operator = FilterOperator::fromAlias((string) $alias);

            if ($operator === null || ! in_array($operator->value, $allowed, true)) {
                throw new OperatorNotAllowedException((string) $alias);
            }

            match ($operator) {
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

    /** Apply a scalar comparison, ignoring non-scalar operands. */
    private function applyComparison(Builder|QueryBuilder $query, string $column, string $sqlOp, mixed $operand): void
    {
        if (is_scalar($operand)) {
            $query->where($column, $sqlOp, $operand);
        }
    }

    /** Apply a scalar equality/inequality condition, handling null coercion. */
    private function applyScalarCondition(Builder|QueryBuilder $query, string $column, string $sqlOp, mixed $value): void
    {
        if ($value === null || (is_string($value) && strtolower($value) === 'null')) {
            if ($sqlOp === '!=') {
                $query->whereNotNull($column);
            } else {
                $query->whereNull($column);
            }

            return;
        }

        if (is_scalar($value)) {
            $query->where($column, $sqlOp, $value);
        }
    }

    /** Apply a LIKE filter, supporting multiple values as OR conditions. */
    private function applyLike(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        $values = [];

        if (is_string($value)) {
            $values[] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $val) {
                if (is_string($val)) {
                    $values[] = $val;
                }
            }
        }

        if (empty($values)) {
            return;
        }

        if (count($values) === 1) {
            $this->applyLikeCondition($query, $column, $values[0]);

            return;
        }

        $query->where(function (Builder|QueryBuilder $sub) use ($column, $values): void {
            $first = true;

            foreach ($values as $val) {
                if ($first) {
                    $this->applyLikeCondition($sub, $column, $val);
                    $first = false;
                } else {
                    $sub->orWhere(fn (Builder|QueryBuilder $q) => $this->applyLikeCondition($q, $column, $val));
                }
            }
        });
    }

    /** Apply a single LIKE condition (uses ICU collation on PostgreSQL). */
    private function applyLikeCondition(Builder|QueryBuilder $query, string $column, string $value): void
    {
        $connection = $query instanceof Builder ? $query->getQuery()->getConnection() : $query->getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $wrapped = $connection->getQueryGrammar()->wrap($column);
            $query->whereRaw("{$wrapped} COLLATE \"und-x-icu\" ILIKE ?", ['%' . $value . '%']);

            return;
        }

        $query->whereLike($column, "%{$value}%");
    }

    /** Apply a BETWEEN or NOT BETWEEN condition. */
    private function applyBetween(Builder|QueryBuilder $query, string $column, mixed $operand, bool $not): void
    {
        $values = is_array($operand)
            ? array_values($operand)
            : array_map('trim', explode(',', (string) $operand));

        if (count($values) !== 2) {
            throw new InvalidBetweenOperandException($not ? 'not_between' : 'between');
        }

        if ($not) {
            $query->whereNotBetween($column, $values);
        } else {
            $query->whereBetween($column, $values);
        }
    }

    /** Normalize an operand into a sanitized list of values. */
    private function toList(mixed $operand): array
    {
        $values = is_array($operand) ? $operand : explode(',', (string) $operand);

        return $this->sanitizeList($values);
    }

    /** Strip empty values from a list and abort if it exceeds max values. */
    private function sanitizeList(array $values): array
    {
        $list = [];

        foreach ($values as $v) {
            if ($v !== '' && $v !== null) {
                $list[] = $v;
            }
        }

        if (count($list) > $this->maxInValues) {
            throw new TooManyValuesException($this->maxInValues);
        }

        return $list;
    }
}