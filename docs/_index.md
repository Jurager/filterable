---
title: Filterable
weight: 1
---

## Introduction

The package converts HTTP filter query parameters into Eloquent query constraints with minimal setup. Define the allowed fields and operators, add the `HasFilterable` trait to your model, and call `->filter()` on the query builder.

The package takes care of parsing, operator resolution, relation queries, sanitization, and caching.
The filter format follows the JSON:API convention:

```
GET /products?filter[sku][like]=shirt&filter[status]=active&sort=-created_at
```

Filtering is strictly allow-listed: only fields defined in $filterable can be applied. Unknown fields are delegated to registered resolvers and are ignored when no resolver can handle them.

## Requirements

- PHP 8.4 or higher
- Laravel 11, 12, or 13

## Documentation

- [Installation](installation.md) — Composer setup, service provider
- [Quickstart](quickstart.md) — define a Filterable, attach the trait, call filter()
- [Filtering](filtering.md) — operators, custom filter methods, OR / AND groups, limits
- [Sorting](sorting.md) — sort string format, aliases, default sort, custom resolvers
- [Relations](relations.md) — dot notation, bracket format, pivot columns, tree scope, included eager loads
- [Sanitization](sanitization.md) — clean input before it reaches the query builder
- [Caching](caching.md) — ID-based result caching, automatic cache invalidation
- [Advanced](advanced.md) — custom resolvers, route binding, events
