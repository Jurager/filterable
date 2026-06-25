---
title: Caching
weight: 70
---

## Overview

The full result of each terminal method is stored in cache. `get()` caches a `Collection`; `paginate()` caches a `LengthAwarePaginator`; `count()` caches an `int`. Different pages of a paginated query produce separate cache entries.

A taggable cache driver (Redis, Memcached) is required.

## Global Configuration

Enable caching for all models via `.env`:

```
FILTERABLE_CACHE=true
FILTERABLE_CACHE_TTL=3600
```

When enabled globally, cache tags default to each model's table name — no per-model declarations needed.

Publish the config to change defaults:

```bash
php artisan vendor:publish --tag=filterable-config
```

## Per-Model Configuration

Override cache settings on the model via the `$cache` array:

```php
class Product extends Model
{
    use HasFilterable;

    protected array $cache = [
        'enabled' => true,
        'ttl'     => 600,
        'tags'    => ['products', 'catalogue'],
    ];
}
```

`enabled: true` takes effect regardless of the global config value.
Omitting `tags` falls back to the model's table name.

## Per-Query Control

Enable or disable caching for a single query:

```php
// explicit
Product::query()->filter()->cache()->paginate();
Product::query()->filter()->cache(ttl: 300)->paginate();

// conditional
Product::query()->filter()->cacheWhen(auth()->user()->prefersCaching())->paginate();
Product::query()->filter()->cacheWhen(fn () => Cache::has('warm'))->paginate();
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

`chunk()`, `lazy()`, and aggregates run without caching.

## Automatic Invalidation

`FilterableCacheObserver` is registered automatically on boot when caching is enabled. It flushes the tag group on `saved`, `deleted`, `restored`, and `forceDeleted`.

## Invalidating on Related Model Changes

When a relation changes independently of the main model (EAV attributes, pivot tables), the main model's observer never fires. Use Laravel's `$touches` to propagate the change:

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
