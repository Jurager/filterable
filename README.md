# Jurager/Filterable

[![Latest Stable Version](https://poser.pugx.org/jurager/filterable/v/stable)](https://packagist.org/packages/jurager/filterable)
[![Total Downloads](https://poser.pugx.org/jurager/filterable/downloads)](https://packagist.org/packages/jurager/filterable)
[![PHP Version Require](https://poser.pugx.org/jurager/filterable/require/php)](https://packagist.org/packages/jurager/filterable)
[![License](https://poser.pugx.org/jurager/filterable/license)](https://packagist.org/packages/jurager/filterable)

A Laravel package that adds JSON:API-compatible `filter` and `sort` query parameter support to Eloquent models.

Features:

- Declarative `$filterable` and `$sortable` arrays on the model — no boilerplate controllers
- Full operator set: `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, `like`, `in`, `nin`, `null`, `not_null`, `between`, `not_between`, `tree`
- Relation filtering via `whereHas` subqueries and pivot column support
- Filtered eager-loading with `filter[included.*]`
- OR / AND condition groups
- Field, relation, and sort resolver interfaces for custom SQL
- Full-result caching (Redis / Memcached) with automatic tag-based invalidation
- Entity-Attribute-Value attribute filtering via `jurager/eav` resolver
- NestedSet descendant expansion via the `tree` operator

## Requirements

- PHP 8.2+
- Laravel 11+

## Installation

```bash
composer require jurager/filterable
```

Add `HasFilterable` to your model and declare allowed fields:

```php
use Jurager\Filterable\Concerns\HasFilterable;

class Product extends Model
{
    use HasFilterable;

    protected array $filterable = [
        'sku'             => ['eq', 'like'],
        'price'           => ['gte', 'lte', 'between'],
        'status'          => ['eq', 'in'],
        'category.name'   => ['eq', 'like'],
        'is_active',
    ];

    protected array $sortable = ['id', 'sku', 'price', 'created_at'];
}
```

Apply in the controller:

```php
Product::query()->filter()->sort()->paginate();
```

Request:

```
GET /products?filter[status][in][]=active&filter[price][gte]=100&sort=-created_at
```

## Documentation

To learn more about filtering, sorting, relations, caching, and advanced usage please go to the [Documentation](https://docs.gerassimov.me/filterable/).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
