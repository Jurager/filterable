<?php

declare(strict_types=1);

namespace Jurager\Filterable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jurager\Filterable\Applying\ConditionApplier;
use Jurager\Filterable\Concerns\HasCacheOptions;
use Jurager\Filterable\Concerns\HasFilterable;
use Jurager\Filterable\Contracts\FieldResolver;
use Jurager\Filterable\Contracts\RelationResolver;
use Jurager\Filterable\Contracts\SortResolver;
use Jurager\Filterable\Events\FilterApplied;
use Jurager\Filterable\Events\FilterApplying;
use Jurager\Filterable\Exceptions\TooManyFiltersException;
use Jurager\Filterable\Parsing\FilterParser;
use Jurager\Filterable\Resolving\MethodResolver;
use Jurager\Filterable\Sanitizing\Sanitizer;
use Jurager\Filterable\Sorting\SortApplier;
use Jurager\Filterable\Support\ParsedFilters;

/** Base class for model-scoped filter definitions. */
class Filterable
{
    use HasCacheOptions;

    /** Allowed filter fields and their permitted operators. */
    protected array $filterable = [];

    /** Allowed sort fields. */
    protected array $sortable = [];

    /** Maximum total number of filter conditions per request. */
    protected int $maxFilters = 50;

    /** Maximum number of values in a single `in`/`nin` filter. */
    protected int $maxInValues = 500;

    /** Field-level sanitizers applied before query building. */
    protected array $sanitizers = [];

    /** @var array<class-string, array<string, string>> Cache of traits used by related models. */
    private static array $traitCache = [];

    /** @var array<int, FieldResolver> */
    private array $fieldResolvers = [];

    /** @var array<int, RelationResolver> */
    private array $relationResolvers = [];

    /** @var array<int, SortResolver> */
    private array $sortResolvers = [];

    /** Lazily built sanitizer instance. */
    private ?Sanitizer $sanitizer = null;

    public function __construct(array $filterable = [], array $sortable = [], array $cache = [], array $sanitizers = [])
    {
        $this->filterable = $filterable ?: $this->filterable;
        $this->sortable   = $sortable ?: $this->sortable;
        $this->cache      = $cache ?: $this->cache;
        $this->sanitizers = $sanitizers ?: $this->sanitizers;
    }

    /** Register a field resolver. */
    public function addFieldResolver(FieldResolver $resolver): static
    {
        $this->fieldResolvers[] = $resolver;

        return $this;
    }

    /** Register a relation resolver. */
    public function addRelationResolver(RelationResolver $resolver): static
    {
        $this->relationResolvers[] = $resolver;

        return $this;
    }

    /** Register a sort resolver. */
    public function addSortResolver(SortResolver $resolver): static
    {
        $this->sortResolvers[] = $resolver;

        return $this;
    }

    /** Build the condition applier used to apply parsed filters to the query. */
    protected function newConditionApplier(): ConditionApplier
    {
        return new ConditionApplier(
            maxInValues:       $this->maxInValues,
            fieldResolvers:    [new MethodResolver($this->resolveSubclassMethod(...)), ...$this->fieldResolvers],
            relationResolvers: $this->relationResolvers,
        );
    }

    /** Build the sort applier used to apply the sort string to the query. */
    protected function newSortApplier(): SortApplier
    {
        return new SortApplier($this->sortResolvers);
    }

    /** Apply filters to the query builder. */
    public function apply(Builder $query, array $filters): Builder
    {
        $model  = $query->getModel();
        $parsed = (new FilterParser())->parse($filters, $this->filterable);

        if (! empty($this->sanitizers)) {
            $sanitizer = $this->sanitizer ??= new Sanitizer($this->sanitizers);
            $parsed    = $parsed->withSanitized(
                filters:   $sanitizer->apply($parsed->filters),
                orGroups:  $sanitizer->applyToGroups($parsed->orGroups),
                andGroups: $sanitizer->applyToGroups($parsed->andGroups),
            );
        }

        $total = $this->countConditions($parsed);

        if ($total > $this->maxFilters) {
            throw new TooManyFiltersException($total, $this->maxFilters);
        }

        event(new FilterApplying($this, $query, $filters));

        $this->newConditionApplier()->apply($query, $parsed, $model);

        foreach ($this->filterableRelations($parsed->included, $model) as $relation => $callback) {
            $query->with([$relation => $callback]);
        }

        event(new FilterApplied($this, $query));

        return $query;
    }

    /** Apply sorting to the query builder. */
    public function sort(Builder $query, ?string $sort): Builder
    {
        if (! $sort) {
            return $query;
        }

        return $this->newSortApplier()->apply($query, $sort, $this->sortable, $query->getModel());
    }

    /**
     * Resolve eager-loadable relations from included filter params.
     *
     * @return array<string, callable>
     */
    public function filterableRelations(array $included, Model $model): array
    {
        $result = [];

        foreach ($included as $key => $value) {

            if (! is_string($key) || ! str_contains($key, '.')) {
                continue;
            }

            [$relation, $column] = explode('.', $key, 2);

            if (isset($result[$relation])
                || ! array_key_exists($key, $this->filterable)
                || ! $model->isRelation($relation)
            ) {
                continue;
            }

            $related = $model->{$relation}()->getRelated();
            $uses    = self::$traitCache[$related::class] ??= class_uses_recursive($related);

            if (in_array(HasFilterable::class, $uses, true)) {
                $result[$relation] = static fn ($q) => $q->filter([$column => $value]);
            }
        }

        return $result;
    }

    /** Count the total number of parsed conditions across all groups. */
    private function countConditions(ParsedFilters $parsed): int
    {
        return count($parsed->filters) + array_sum(array_map('count', [...$parsed->orGroups, ...$parsed->andGroups]));
    }

    /** Dispatch a filter to a method on the subclass, if one exists. */
    protected function resolveSubclassMethod(Builder $query, string $name, mixed $value): bool
    {
        $method = "filter" . Str::studly($name);

        if (method_exists($this, $method)) {
            $this->{$method}($query, $value);

            return true;
        }

        return false;
    }
}