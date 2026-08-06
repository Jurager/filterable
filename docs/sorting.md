---
title: Sorting
weight: 40
---

## Sort Format

Sorting is controlled by a single `sort` query parameter. Prefix a field name with `-` for descending order:

```
GET /products?sort=price          — ORDER BY price ASC
GET /products?sort=-created_at    — ORDER BY created_at DESC
```

Multiple sort fields are not supported. Use [custom sort resolvers](#custom-sort-resolvers) for compound ordering.

## Declaring Allowed Sort Fields

Declare `$sortable` on the model with the list of fields that may be sorted on:

```php
protected array $sortable = ['id', 'sku', 'price', 'created_at'];
```

A request that attempts to sort by an unlisted field is silently ignored. Only exact matches are checked — there is no wildcard syntax.

## Applying Sort in the Controller

`->sort()` takes the raw `sort` string explicitly — pull it from the request yourself, or pass `null` to skip sorting:

```php
Product::query()->sort($request->query('sort'))->get();
```

## Custom Sort Resolvers

For sort fields that require custom SQL — joined columns, expressions, or relations — implement `SortResolver`. Return `true` if the sort was applied, `false` to pass to the next resolver:

```php
use Jurager\Filterable\Contracts\SortResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PriceWithTaxSortResolver implements SortResolver
{
    public function resolve(Builder $query, string $field, string $direction, Model $model, array $context = []): bool
    {
        if ($field !== 'price_with_tax') {
            return false;
        }

        $query->orderByRaw('price * 1.2 ' . $direction);

        return true;
    }
}
```

Register it by overriding `newFilterable()` on the model — see [Advanced](advanced.md#custom-sort-resolvers).

Do **not** add the field to `$sortable` — resolvers are called only for fields that are not listed there. The resolver is the declaration. The `$direction` argument is either `'asc'` or `'desc'`.

## Sort Context

An ordering that spans a relation usually has to agree with how that relation was narrowed. `included.` constraints are therefore handed to every sort resolver as `$context`, with the prefix stripped — no wiring required at the call site:

```php
// filter[included.stocks.warehouse_id][in]=1,2&sort=-in_stock
Product::query()->filter($filter)->sort($sort)->paginate();
```

```php
public function resolve(Builder $query, string $field, string $direction, Model $model, array $context = []): bool
{
    $warehouseIds = ParsedFilters::ids($context, 'stocks.warehouse_id');

    if ($field !== 'in_stock' || $warehouseIds === []) {
        return false;
    }

    // ... order by a correlated subquery over those warehouses

    return true;
}
```

Returning `false` when the context is missing is the idiomatic guard: the field stays unresolved and is silently ignored, exactly like any other unsortable field.

`sort()` picks the constraints up as it is called, so **chain `filter()` first** — the reverse order leaves the context empty and the field simply goes unresolved. To supply values that are not filters, pass them as the second argument to `->sort()`; they override anything picked up under the same key:

```php
Product::query()->sort($sort, ['stocks.warehouse_id' => ['in' => '1,2']]);
```
