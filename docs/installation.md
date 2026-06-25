---
title: Installation
weight: 10
---

## Installing the Package

```bash
composer require jurager/filterable
```

The package registers `FilterableServiceProvider` automatically via Laravel's package discovery.

## Manual Registration

If auto-discovery is disabled, add the provider to `bootstrap/providers.php`:

```php
return [
    Jurager\Filterable\FilterableServiceProvider::class,
];
```
