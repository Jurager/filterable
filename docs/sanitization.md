---
title: Sanitization
weight: 60
---

## Overview

Sanitizers transform filter values before they reach the query builder. They run after parsing and before limit checks, and apply uniformly to flat filters, OR groups, and AND groups.

## Configuring Sanitizers

Declare `$sanitizers` on the model as a field → handler map:

```php
protected array $sanitizers = [
    'sku'    => 'strtolower',
    'name'   => 'trim',
    'status' => 'strtoupper',
];
```

The handler is called with the filter value and its return value replaces the original. PHP built-in functions work directly as strings.

## Closures

For multi-step or conditional transforms, use a closure:

```php
protected array $sanitizers = [
    'sku' => function (mixed $value): string {
        return strtolower(trim((string) $value));
    },
];
```

## Chaining Multiple Handlers

Assign an array of handlers to apply them in sequence:

```php
protected array $sanitizers = [
    'sku' => ['trim', 'strtolower'],
];
```

Each handler receives the output of the previous one.

## Class-Based Handlers

Any callable is accepted. Invokable classes work without extra wiring:

```php
class NormalizeSlug
{
    public function __invoke(mixed $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9-]+/i', '-', (string) $value));
    }
}
```

```php
protected array $sanitizers = [
    'slug' => NormalizeSlug::class,
];
```

When a string value in `$sanitizers` is not a PHP built-in function, it is resolved through `app()` to support constructor injection.
