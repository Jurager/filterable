<?php

namespace Jurager\Filterable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Applying\ConditionApplier;
use Jurager\Filterable\Applying\Sanitizer;
use Jurager\Filterable\Concerns\HasCacheOptions;
use Jurager\Filterable\Concerns\HasFilterable;
use Jurager\Filterable\Contracts\FieldResolverInterface;
use Jurager\Filterable\Contracts\RelationResolverInterface;
use Jurager\Filterable\Contracts\SortResolverInterface;
use Jurager\Filterable\Events\FilterApplied;
use Jurager\Filterable\Events\FilterApplying;
use Jurager\Filterable\Parsing\FilterParser;
use Jurager\Filterable\Sorting\SortApplier;
use ReflectionMethod;

/**
 * Base class for model-scoped filter definitions.
 * Subclass per model and declare $filterable, $sortable, and custom filter methods.
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
     * Default sort applied when no sort param is present.
     * @var string|null
     */
    protected ?string $defaultSort = null;

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
     * Create a new Filterable instance.
     * @param array $filterable
     * @param array $sortable
     * @param array $cache
     */
    public function __construct(array $filterable = [], array $sortable = [], array $cache = [])
    {
        if (!empty($filterable)) {
            $this->filterable = $filterable;
        }
        if (!empty($sortable)) {
            $this->sortable = $sortable;
        }
        if (!empty($cache)) {
            $this->cache = $cache;
        }
    }

    /**
     * Register a field resolver.
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
     * @param SortResolverInterface $resolver
     * @return static
     */
    public function addSortResolver(SortResolverInterface $resolver): static
    {
        $this->sortResolvers[] = $resolver;

        return $this;
    }

    /**
     * Apply filters to the query builder.
     * @param Builder $query
     * @param array $filters
     * @return Builder
     */
    public function apply(Builder $query, array $filters): Builder
    {
        $model  = $query->getModel();
        $parsed = (new FilterParser())->parse($filters, $this->filterable);

        if (!empty($this->sanitizers)) {
            $san    = new Sanitizer($this->sanitizers);
            $parsed = $parsed->withSanitized(
                filters:   $san->apply($parsed->filters),
                orGroups:  $san->applyToGroups($parsed->orGroups),
                andGroups: $san->applyToGroups($parsed->andGroups),
            );
        }

        $total = count($parsed->filters)
            + array_sum(array_map('count', $parsed->orGroups))
            + array_sum(array_map('count', $parsed->andGroups));

        abort_if($total > $this->maxFilters, 400, 'Too many filter parameters.');

        event(new FilterApplying($this, $query, $filters));

        (new ConditionApplier(
            maxInValues:       $this->maxInValues,
            fieldResolvers:    $this->fieldResolvers,
            relationResolvers: $this->relationResolvers,
            subclassHook:      $this->resolveSubclassMethod(...),
        ))->apply($query, $parsed, $model);

        foreach ($this->filterableRelations($parsed->included, $model) as $relation => $callback) {
            $query->with([$relation => $callback]);
        }

        event(new FilterApplied($this, $query));

        return $query;
    }

    /**
     * Apply sorting to the query builder.
     * @param Builder $query
     * @param string|null $sort
     * @return Builder
     */
    public function sort(Builder $query, ?string $sort): Builder
    {
        $sort ??= $this->defaultSort;

        if (!$sort) {
            return $query;
        }

        return (new SortApplier($this->sortResolvers))
            ->apply($query, $sort, $this->sortable, $query->getModel());
    }

    /**
     * Resolve eager-loadable relations from included filter params.
     * @param array $included
     * @param Model $model
     * @return array<string, callable>
     */
    public function filterableRelations(array $included, Model $model): array
    {
        static $traitCache = [];

        $result = [];

        foreach ($this->filterable as $key => $_) {
            if (!is_string($key) || !str_contains($key, '.')) {
                continue;
            }

            [$relation, $column] = explode('.', $key, 2);

            if (isset($result[$relation]) || !$model->isRelation($relation)) {
                continue;
            }

            if (!array_key_exists($key, $included)) {
                continue;
            }

            $related = $model->{$relation}()->getRelated();
            $uses    = $traitCache[$related::class] ??= class_uses_recursive($related);

            if (in_array(HasFilterable::class, $uses, true)) {
                $result[$relation] = static fn ($q) => $q->filter([$column => $included[$key]]);
            }
        }

        return $result;
    }

    /**
     * Dispatch a filter to a subclass method if one exists.
     * Returns false if the method is defined on Filterable itself (not a subclass).
     * @param Builder $query
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    protected function resolveSubclassMethod(Builder $query, string $name, mixed $value): bool
    {
        if (!method_exists($this, $name)) {
            return false;
        }

        $declaring = (new ReflectionMethod($this, $name))->getDeclaringClass()->getName();

        if ($declaring === self::class) {
            return false;
        }

        $this->{$name}($query, $value);

        return true;
    }
}
