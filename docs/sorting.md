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

Register it by overriding `newFilterable()` on the model — see [Advanced](advanced.md#custom-sort-resolvers).

Do **not** add the field to `$sortable` — resolvers are called only for fields that are not listed there. The resolver is the declaration. The `$direction` argument is either `'asc'` or `'desc'`.
