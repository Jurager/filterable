---
title: Filtering
weight: 30
---

## Filter Format

Filters are sent as nested query parameters under the `filter` key. Each entry names a field and may optionally specify an operator:

```
filter[field]=value                    — shorthand for eq
filter[field][operator]=value          — explicit operator
filter[field][in][]=a&filter[field][in][]=b  — list value
```

## Supported Operators

| Alias | Symbolic alias | SQL |
|---|---|---|
| `eq` | `=` | `= value` |
| `ne` | `!=` | `!= value` |
| `gt` | `>` | `> value` |
| `gte` | `>=` | `>= value` |
| `lt` | `<` | `< value` |
| `lte` | `<=` | `<= value` |
| `like` | — | `LIKE '%value%'` (ILIKE with ICU collation on PostgreSQL) |
| `in` | — | `IN (...)` |
| `nin` | — | `NOT IN (...)` |
| `null` | — | `IS NULL` |
| `not_null` | — | `IS NOT NULL` |
| `between` | — | `BETWEEN a AND b` — requires exactly 2 values |
| `not_between` | — | `NOT BETWEEN a AND b` — requires exactly 2 values |
| `tree` | — | Descendant expansion — see [Relations](relations.md#tree-scope) |

Both the short alias (`eq`) and the symbolic alias (`=`) are accepted interchangeably in requests.

## Declaring Allowed Fields

Declare `$filterable` on the model as a field → operator array map:

```php
protected array $filterable = [
    'sku'    => ['eq', 'like'],
    'price'  => ['gte', 'lte', 'between'],
    'status' => ['eq', 'in'],
    'is_active',                 // shorthand — eq only
];
```

A request may only use the operators declared for that field. Any other operator returns HTTP 400.

## Custom Filter Methods

Define a method with the same name as the filter field on a [custom Filterable subclass](advanced.md#custom-filterable-class) to replace the default operator dispatch for unknown fields:

```php
class ProductFilterable extends Filterable
{
    protected function status(Builder $query, mixed $value): void
    {
        match ($value) {
            'published' => $query->whereNotNull('published_at'),
            'draft'     => $query->whereNull('published_at'),
            default     => $query->where('status', $value),
        };
    }
}
```

The method is called when `filter[status]=…` is present and `status` is not declared in `$filterable`. Methods defined on the `Filterable` base class itself are never treated as filter handlers.

## OR Groups

To apply conditions with OR logic, send filters under `filter[or]`:

```
filter[or][status]=active&filter[or][featured]=1
```

Produces `WHERE (status = 'active' OR featured = 1)`.

To send multiple independent OR groups as a list:

```
filter[or][0][status]=active&filter[or][0][type]=bundle
filter[or][1][status]=sale&filter[or][1][type]=simple
```

Produces `WHERE (status = 'active' AND type = 'bundle') OR (status = 'sale' AND type = 'simple')`.

## AND Groups

`filter[and]` accepts a list of condition groups. Within each group, conditions are OR'd; groups themselves are AND'd:

```
filter[and][0][status]=active&filter[and][0][type]=bundle
filter[and][1][price][gte]=100
```

Produces `WHERE (status = 'active' OR type = 'bundle') AND (price >= 100)`.

## Empty Values

Null and empty-string values are stripped before any filtering logic runs. A request parameter with an empty value is silently dropped:

```
filter[sku]=&filter[status]=active   →  only status filter is applied
```

This also applies inside operator arrays and list values (`in`, `nin`).

## Limits

| Property | Default | Description |
|---|---|---|
| `$maxFilters` | `50` | Maximum total number of conditions across filters, OR groups, and AND groups |
| `$maxInValues` | `500` | Maximum number of values in a single `in` or `nin` filter |

Exceeding either limit returns HTTP 400. Override the properties on the model to change the defaults:

```php
protected int $maxFilters  = 20;
protected int $maxInValues = 100;
```
