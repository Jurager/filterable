---
title: Quickstart
weight: 20
---

## Setup

Add `HasFilterable` to the model and declare which fields and operators are allowed:

```php
use Jurager\Filterable\Concerns\HasFilterable;

class Product extends Model
{
    use HasFilterable;

    protected array $filterable = [
        'sku'        => ['eq', 'like'],
        'status'     => ['eq', 'in'],
        'price'      => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
        'created_at' => ['gte', 'lte'],
        'is_active',               // shorthand — only eq allowed
    ];

    protected array $sortable = ['id', 'sku', 'price', 'created_at'];
}
```

Fields listed without an operator array (`'is_active'`) default to `['eq']`.

## Applying Filters in a Controller

```php
public function index(): JsonResponse
{
    $products = Product::query()
        ->filter()
        ->sort()
        ->paginate();

    return response()->json($products);
}
```

`->filter()` reads `filter[]` from the current request. `->sort()` reads the `sort` parameter. Both accept an explicit `Request` or a raw array:

```php
Product::query()->filter(['sku' => ['like' => 'shirt']])->paginate();
```

## Example Request

```
GET /products?filter[sku][like]=shirt&filter[status]=active&filter[price][gte]=100&sort=-created_at
```

```sql
SELECT * FROM products
WHERE sku LIKE '%shirt%'
  AND status = 'active'
  AND price >= 100
ORDER BY created_at DESC
```

## Custom Filter Logic

To replace the default operator dispatch for a field, define a method with the same name on the model:

```php
protected function status(Builder $query, mixed $value): void
{
    $query->where('status', $value)->whereNotNull('published_at');
}
```

Custom methods take priority over the operator dispatch for their field.

## Custom Filterable Class

For complex cases — custom resolvers, EAV integration, reusable filter logic across models — create a class that extends `Filterable` and override `newFilterable()` on the model:

```php
class ProductFilterable extends Filterable
{
    protected array $filterable = [...];
    protected array $sortable   = [...];

    public function __construct()
    {
        $this->addFieldResolver(new PriceRangeResolver);
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

See [Advanced](advanced.md) for details on resolvers.
