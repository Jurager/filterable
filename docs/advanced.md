---
title: Advanced
weight: 80
---

## Custom Filterable Class

When a model needs custom resolvers or reusable filter logic, create a class that extends `Filterable` and register it via `newFilterable()`:

```php
class ProductFilterable extends Filterable
{
    protected array $filterable = [...];
    protected array $sortable   = [...];

    public function __construct()
    {
        $this->addFieldResolver(new PriceRangeResolver);
        $this->addSortResolver(new PriceWithTaxSortResolver);
    }
}
```

```php
class Product extends Model
{
    use HasFilterable;

    protected function newFilterable(): ProductFilterable
    {
        return new ProductFilterable;
    }
}
```

## Custom Field Resolvers

A field resolver intercepts plain filter keys that are **not declared in `$filterable`**. Implement `FieldResolverInterface` — return `true` if the filter was handled, `false` to pass to the next resolver:

```php
use Jurager\Filterable\Contracts\FieldResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PriceRangeResolver implements FieldResolverInterface
{
    public function resolve(Builder $query, string $name, mixed $value, Model $model): bool
    {
        if ($name !== 'price_range') {
            return false;
        }

        [$min, $max] = explode(',', (string) $value, 2);
        $query->whereBetween('price', [(float) $min, (float) $max]);

        return true;
    }
}
```

Register via `addFieldResolver()` in the Filterable subclass constructor (see above). The field does not need to appear in `$filterable`.

## Custom Relation Resolvers

A relation resolver intercepts dotted filter keys (`relation.column`) that are **not declared in `$filterable`**. Implement `RelationResolverInterface` — return `true` if handled, `false` to pass to the next resolver:

```php
use Jurager\Filterable\Contracts\RelationResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActiveTagsResolver implements RelationResolverInterface
{
    public function resolveRelation(Builder $query, string $name, mixed $value, Model $model): bool
    {
        if ($name !== 'tags.active') {
            return false;
        }

        $query->whereHas('tags', fn (Builder $q) => $q->where('active', (bool) $value));

        return true;
    }
}
```

## Custom Sort Resolvers

For sort fields that require custom SQL — joined columns, expressions, or relations — implement `SortResolverInterface`. Return `true` if the sort was applied, `false` to pass to the next resolver:

```php
use Jurager\Filterable\Contracts\SortResolverInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PriceWithTaxSortResolver implements SortResolverInterface
{
    public function resolve(Builder $query, string $field, string $direction, Model $model): bool
    {
        if ($field !== 'price_with_tax') {
            return false;
        }

        $query->orderByRaw('price * 1.2 ' . $direction);

        return true;
    }
}
```

Do **not** add the field to `$sortable` — resolvers are only called for fields not listed there. The resolver itself is the declaration. The `$direction` argument is either `'asc'` or `'desc'`.

## Route Model Binding

`HasFilterable` overrides `resolveRouteBinding()` to call `loadFilteredRelations()` after the model is resolved. This applies the same `filter[included.*]` eager-loading that works for collection queries:

```php
// Route definition
Route::get('/products/{product}', [ProductController::class, 'show']);

// Request — eager-loads prices restricted to price_type_id = 1
GET /products/42?filter[included.prices.price_type_id][in]=1
```

The resolved `Product` instance will have `prices` already loaded with the filter applied. See [Filtered Eager-Loading](relations.md#filtered-eager-loading-included) for the full requirements.

To change the lookup field, override `resolveRouteBindingField()` on the model:

```php
protected function resolveRouteBindingField(mixed $value): string
{
    return 'slug';
}
```

## Events

The package dispatches two events during `Filterable::apply()`:

| Event | When | Payload |
|---|---|---|
| `FilterApplying` | Before conditions are applied | `$filterable`, `$query`, raw `$filters` array |
| `FilterApplied` | After all conditions have been applied | `$filterable`, `$query` |

```php
use Jurager\Filterable\Events\FilterApplying;

Event::listen(FilterApplying::class, function (FilterApplying $event) {
    Log::debug('filter applied', [
        'class'   => get_class($event->filterable),
        'filters' => $event->filters,
    ]);
});
```
