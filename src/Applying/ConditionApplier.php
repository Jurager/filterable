<?php

declare(strict_types=1);

namespace Jurager\Filterable\Applying;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Support\ParsedFilters;

/** Orchestrate filter application, routing conditions to appropriate handlers. */
class ConditionApplier
{
    /** Matches a single unqualified identifier. */
    private const string IDENTIFIER = '/^\w+$/';

    /** Operator logic applier. */
    private readonly OperatorApplier $operators;

    /** Tree relation filter applier. */
    private readonly TreeConditionApplier $tree;

    /** Pivot relation filter applier. */
    private readonly PivotConditionApplier $pivot;

    public function __construct(
        int $maxInValues = 500,
        private readonly array $fieldResolvers = [],
        private readonly array $relationResolvers = [],
    ) {
        $this->operators = new OperatorApplier($maxInValues);
        $this->tree      = new TreeConditionApplier();
        $this->pivot     = new PivotConditionApplier($this->operators);
    }

    /** Apply all parsed filter conditions to the query builder. */
    public function apply(Builder $query, ParsedFilters $parsed, Model $model): void
    {
        $this->applyFilters($query, $parsed->filters, $parsed->allowed, $model);

        if (! empty($parsed->orGroups)) {
            $query->where(function (Builder $q) use ($parsed, $model): void {
                foreach ($parsed->orGroups as $i => $group) {
                    $method = $i === 0 ? 'where' : 'orWhere';
                    $q->{$method}(fn (Builder $sub) => $this->applyFilters($sub, $group, $parsed->allowed, $model));
                }
            });
        }

        foreach ($parsed->andGroups as $group) {
            if (empty($group)) {
                continue;
            }

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

    /** Apply a flat set of field and value filters to the query. */
    private function applyFilters(Builder $query, array $filters, array $allowed, Model $model): void
    {
        foreach ($filters as $name => $value) {
            if ($value !== null) {
                $this->applySingleFilter($query, (string) $name, $value, $allowed, $model);
            }
        }
    }

    /** Route a single filter to the appropriate handler. */
    private function applySingleFilter(Builder $query, string $name, mixed $value, array $allowed, Model $model): void
    {
        if (! isset($allowed[$name])) {
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

        if ($this->tree->isTreeRequest($operators, $value)) {
            $this->tree->applyDirect($query, $value['tree'], $model);

            return;
        }

        $this->operators->apply($query, is_string($config) ? $config : $name, $operators, $value);
    }

    /** Delegate an unrecognized field to the registered resolvers. */
    private function delegate(Builder $query, string $name, mixed $value, Model $model): void
    {
        if (str_contains($name, '.')) {
            foreach ($this->relationResolvers as $resolver) {
                if ($resolver->resolveRelation($query, $name, $value, $model)) {
                    break;
                }
            }

            return;
        }

        foreach ($this->fieldResolvers as $resolver) {
            if ($resolver->resolve($query, $name, $value, $model)) {
                break;
            }
        }
    }

    /** Normalize a field config into a list of allowed operators. */
    private function operatorsFor(array|string $config): array
    {
        return is_array($config) ? $config : ['eq'];
    }

    /** Determine whether a field should be treated as a boolean. */
    private function isBooleanField(string $name, array|string $config, Model $model): bool
    {
        return $config === 'boolean' || in_array($model->getCasts()[$name] ?? '', ['bool', 'boolean'], true);
    }

    /** Apply a boolean equality filter, ignoring values that are not coercible. */
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

    /** Apply a dotted-name filter through whereHas or a pivot subquery. */
    private function applyRelationFilter(Builder $query, string $name, array $operators, mixed $value, Model $model): void
    {
        $parts  = explode('.', $name);
        $column = array_pop($parts);

        foreach ($parts as $part) {
            if (! preg_match(self::IDENTIFIER, $part)) {
                return;
            }
        }

        $relationName = $parts[0];

        if ($this->tree->isTreeRequest($operators, $value)) {
            $this->tree->applyThroughRelation($query, $relationName, $value['tree']);

            return;
        }

        $pivotRelation = $this->pivot->resolve($model, $relationName);

        if ($pivotRelation !== null && $this->pivot->matches($pivotRelation, $parts, $column)) {
            $this->pivot->apply($query, $column, $operators, $value, $pivotRelation);

            return;
        }

        $query->whereHas(implode('.', $parts), function (Builder $q) use ($column, $operators, $value): void {
            $this->operators->apply($q, $column, $operators, $value);
        });
    }
}