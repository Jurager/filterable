<?php

namespace Jurager\Filterable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jurager\Filterable\Applying\ConditionApplier;
use Jurager\Filterable\Applying\MethodResolver;
use Jurager\Filterable\Applying\Sanitizer;
use Jurager\Filterable\Concerns\HasCacheOptions;
use Jurager\Filterable\Concerns\HasFilterable;
use Jurager\Filterable\Contracts\FieldResolverInterface;
use Jurager\Filterable\Contracts\RelationResolverInterface;
use Jurager\Filterable\Contracts\SortResolverInterface;
use Jurager\Filterable\Events\FilterApplied;
use Jurager\Filterable\Events\FilterApplying;
use Jurager\Filterable\Exceptions\TooManyFiltersException;
use Jurager\Filterable\Parsing\FilterParser;
use Jurager\Filterable\Sorting\SortApplier;

/**
 * Base class for model-scoped filter definitions.
 */
class Filterable
{
    use HasCacheOptions;

    /**
     * Allowed filter fields and their permitted operators.
     * @var array
     */
    protected array $filterable = [];

    /**
     * Allowed sort fields.
     * @var array
     */
    protected array $sortable = [];

    /**
     * Maximum total number of filter conditions per request.
     * @var int
     */
    protected int $maxFilters = 50;

    /**
     * Maximum number of values in a single `in`/`nin` filter.
     * @var int
     */
    protected int $maxInValues = 500;

    /**
     * Field-level sanitizers applied before query building.
     * Keys use dot notation. Values are a callable, function name, or array of both.
     * @var array
     */
    protected array $sanitizers = [];

    /**
     * Cache of traits used by related models, keyed by class name.
     * @var array<class-string, array<string, string>>
     */
    private static array $traitCache = [];

    /**
     * @var FieldResolverInterface[]
     */
    private array $fieldResolvers = [];

    /**
     * @var RelationResolverInterface[]
     */
    private array $relationResolvers = [];

    /**
     * @var SortResolverInterface[]
     */
    private array $sortResolvers = [];

    /**
     * Lazily built sanitizer instance.
     * @var Sanitizer|null
     */
    private ?Sanitizer $sanitizer = null;

    public function __construct(array $filterable = [], array $sortable = [], array $cache = [])
    {
        $this->filterable = $filterable ?: $this->filterable;
        $this->sortable   = $sortable ?: $this->sortable;
        $this->cache      = $cache ?: $this->cache;
    }

    /**
     * Register a field resolver.
     *
     * @param FieldResolverInterface $resolver
     * @return static
     */
    public function addFieldResolver(FieldResolverInterface $resolver): static
    {
        $this->fieldResolvers[] = $resolver;

        return $this;
    }

    /**
     * Register a relation resolver.
     *
     * @param RelationResolverInterface $resolver
     * @return static
     */
    public function addRelationResolver(RelationResolverInterface $resolver): static
    {
        $this->relationResolvers[] = $resolver;

        return $this;
    }

    /**
     * Register a sort resolver.
     *
     * @param SortResolverInterface $resolver
     * @return static
     */
    public function addSortResolver(SortResolverInterface $resolver): static
    {
        $this->sortResolvers[] = $resolver;

        return $this;
    }

    /**
     * Build the condition applier used to apply parsed filters to the query.
     *
     * Override to swap in a custom applier.
     * @return ConditionApplier
     */
    protected function newConditionApplier(): ConditionApplier
    {
        return new ConditionApplier(
            maxInValues:       $this->maxInValues,
            fieldResolvers:    [new MethodResolver($this->resolveSubclassMethod(...)), ...$this->fieldResolvers],
            relationResolvers: $this->relationResolvers,
        );
    }

    /**
     * Build the sort applier used to apply the sort string to the query.
     *
     * Override to swap in a custom applier.
     * @return SortApplier
     */
    protected function newSortApplier(): SortApplier
    {
        return new SortApplier($this->sortResolvers);
    }

    /**
     * Apply filters to the query builder.
     *
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $model  = $query->getModel();
        $parsed = (new FilterParser())->parse($filters, $this->filterable);

        if ($this->sanitizers) {
            $san    = $this->sanitizer ??= new Sanitizer($this->sanitizers);
            $parsed = $parsed->withSanitized(
                filters:   $san->apply($parsed->filters),
                orGroups:  $san->applyToGroups($parsed->orGroups),
                andGroups: $san->applyToGroups($parsed->andGroups),
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

    /**
     * Apply sorting to the query builder.
     *
     * @param Builder $query
     * @param string|null $sort
     * @return Builder
     */
    public function sort(Builder $query, ?string $sort): Builder
    {
        if (!$sort) {
            return $query;
        }

        return $this->newSortApplier()->apply($query, $sort, $this->sortable, $query->getModel());
    }

    /**
     * Resolve eager-loadable relations from included filter params.
     *
     * @param array $included
     * @param Model $model
     * @return array<string, callable>
     */
    public function filterableRelations(array $included, Model $model): array
    {
        $result = [];

        foreach ($included as $key => $value) {

            if (!is_string($key) || !str_contains($key, '.')) {
                continue;
            }

            [$relation, $column] = explode('.', $key, 2);

            if (isset($result[$relation])
                || !array_key_exists($key, $this->filterable)
                || !$model->isRelation($relation)
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

    /**
     * Count the total number of parsed conditions across all groups.
     *
     * @param object $parsed
     * @return int
     */
    private function countConditions(object $parsed): int
    {
        return count($parsed->filters) + array_sum(array_map('count', [...$parsed->orGroups, ...$parsed->andGroups]));
    }

    /**
     * Dispatch a filter to a method on the subclass, if one exists.
     *
     * @param Builder $query
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    protected function resolveSubclassMethod(Builder $query, string $name, mixed $value): bool
    {
        $method = 'filter' . Str::studly($name);

        if (method_exists($this, $method)) {
            $this->$method($query, $value);

            return true;
        }

        return false;
    }
}