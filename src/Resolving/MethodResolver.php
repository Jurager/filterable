<?php

declare(strict_types=1);

namespace Jurager\Filterable\Resolving;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\FieldResolver;

/** Adapt custom model methods to the FieldResolver contract. */
final class MethodResolver implements FieldResolver
{
    /**
     * @param Closure(Builder, string, mixed): bool $hook
     */
    public function __construct(
        private readonly Closure $hook,
    ) {
    }

    /** Resolve the field using the underlying closure hook. */
    public function resolve(Builder $query, string $name, mixed $value, Model $model): bool
    {
        return ($this->hook)($query, $name, $value);
    }
}