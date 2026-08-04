---
title: Caching
weight: 70
---

## Overview

The full result of each terminal method is stored in cache. `get()` caches a `Collection`; `paginate()` caches a `LengthAwarePaginator`; `count()` caches an `int`. Different pages of a paginated query produce separate cache entries.

Caching is always explicit — call `cached()` on the query you want cached. There is no global or per-model switch that enables it automatically; a query that never calls `cached()` never touches the cache.

A taggable cache driver (Redis, Memcached) is required.

## Per-Query Control

```php
Product::query()->filter($filter)->cached()->paginate();
Product::query()->filter($filter)->cached(ttl: 300)->paginate();
```

For conditional caching, use Laravel's own `when()`:

```php
Product::query()->filter($filter)
    ->when(auth()->user()->prefersCaching(), fn ($query) => $query->cached())
    ->paginate();
```

## Per-Model Configuration

`cached()` reads its tags and default TTL from the model's `$cache` array:

```php
class Product extends Model
{
    use HasFilterable;

    protected array $cache = [
        'ttl'  => 600,
        'tags' => ['products', 'catalogue'],
    ];
}
```

An explicit `ttl` argument to `cached()` takes priority over `$cache['ttl']`. Omitting `tags` falls back to the model's table name.

## Global Configuration

Set the default TTL used when neither `cached()` nor the model's `$cache` array specifies one:

```
FILTERABLE_CACHE_TTL=3600
```

Publish the config to change it directly:

```bash
php artisan vendor:publish --tag=filterable-config
```

## Cached Methods

| Method | Cached value |
|---|---|
| `get()` | `Collection<Model>` |
| `first()` | `Model\|null` |
| `paginate()` | `LengthAwarePaginator` |
| `simplePaginate()` | `Paginator` |
| `cursorPaginate()` | `CursorPaginator` |
| `count()` | `int` |
| `exists()` | `bool` |
| `doesntExist()` | `bool` |

`chunk()`, `lazy()`, and `cursor()` run without caching — they stream results instead of returning a value that could be cached as a whole.

## Automatic Invalidation

`FilterableCacheObserver` is registered automatically for every model using `HasFilterable`. It flushes the tag group on `saved`, `deleted`, `restored`, and `forceDeleted` — regardless of whether that model's queries actually use `cached()`, so invalidation is always correct once you start caching.

## Invalidating on Related Model Changes

When a relation changes independently of the main model (e.g. pivot tables), the main model's observer never fires. Use Laravel's `$touches` to propagate the change:

```php
class AttributeValue extends Model
{
    protected $touches = ['product'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

Touching `Product` fires a `saved` event, which `FilterableCacheObserver` catches.

## Cache Store

Uses the default store from `config/cache.php`. To switch:

```
CACHE_STORE=redis
```
