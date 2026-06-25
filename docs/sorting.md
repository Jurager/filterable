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

## Default Sort

Set `$defaultSort` on the model to apply ordering when no `sort` parameter is present in the request:

```php
protected ?string $defaultSort = '-created_at';
```

The same prefix convention applies: a leading `-` means descending.

## Applying Sort in the Controller

```php
Product::query()->filter()->sort()->paginate();
```

`->sort()` reads the `sort` parameter from the current request. Pass an explicit `Request` instance or `null` to skip:

```php
Product::query()->sort($request)->get();
```

## Custom Sort Resolvers

For sort fields that require custom SQL — joined columns, expressions, or relations — implement `SortResolverInterface`:

```php
use Jurager\Filterable\Contracts\SortResolverInterface;
use Illuminate\Database\Eloquent\Builder;

class PriceWithTaxSortResolver implements SortResolverInterface
{
    public function handles(): string
    {
        return 'price_with_tax';
    }

    public function apply(Builder $query, string $direction): void
    {
        $query->orderByRaw('price * 1.2 ' . $direction);
    }
}
```

Register it by overriding `newFilterable()` on the model — see [Advanced](advanced.md#custom-sort-resolvers).

Do **not** add the field to `$sortable` — resolvers are called only for fields that are not listed there. The resolver is the declaration. The `$direction` argument is either `'asc'` or `'desc'`.
