---
title: Relations
weight: 50
---

## Filtering Relation Columns

Use dot notation to filter on a column in a related table. The package automatically builds the appropriate `whereHas` subquery:

```
GET /products?filter[category.name][like]=phones
```

```sql
WHERE EXISTS (
  SELECT 1 FROM categories
  WHERE categories.id = products.category_id
    AND categories.name LIKE '%phones%'
)
```

Declare the relation path in `$filterable` the same way as a regular field:

```php
protected array $filterable = [
    'category.name' => ['eq', 'like'],
    'tags.slug'     => ['in'],
];
```

## Bracket Format

Some HTTP clients cannot send dots in parameter names. The bracket format is equivalent:

```
filter[category][name][like]=phones
```

The parser flattens bracket notation to dot notation before any processing, so `filter[category][name]` and `filter[category.name]` are interchangeable.

## Pivot Columns

For many-to-many relations, filter on pivot table columns by including `pivot` in the path:

```
GET /products?filter[tags.pivot.weight][gte]=5
```

```php
protected array $filterable = [
    'tags.pivot.weight' => ['gte', 'lte'],
];
```

The package detects the `pivot` segment and issues a `wherePivot` condition on the join table.

## Tree Scope

For hierarchical models, use the `tree` operator to include a node and all its descendants:

```
GET /products?filter[category_id][tree]=5
```

This expands to a descendant scope closure rather than a plain equality check. The `tree` operator must be explicitly listed in `$filterable`:

```php
protected array $filterable = [
    'category_id' => ['eq', 'tree'],
];
```

## Filtered Eager-Loading (`included.*`)

Use the `included.` prefix to eager-load a relation with a filter scoped to that relation. This is distinct from `whereHas` filtering: the main query is not constrained, but each loaded record has a pre-filtered subset of the relation.

Declare the relation field in `$filterable` as usual:

```php
protected array $filterable = [
    'prices.price_type_id' => ['eq', 'in'],
];
```

Request:

```
GET /products?filter[included.prices.price_type_id][in]=1
```

This returns **all products**, with each product's `prices` relation eager-loaded and restricted to entries where `price_type_id IN (1)`.

Compare with `filter[prices.price_type_id][in]=1` (no `included.` prefix), which filters the products themselves using a `whereHas` subquery.

**Requirements:**

- `prices.price_type_id` must be declared in the model's `$filterable`
- The related model returned by `prices()` must use `HasFilterable`
- `price_type_id` must be declared in the related model's `$filterable`

For simple eager-loading without a filter constraint, use [`with()`](https://laravel.com/docs/eloquent-relationships#eager-loading) directly in the controller or apply a [custom relation resolver](advanced.md#custom-relation-resolvers).
