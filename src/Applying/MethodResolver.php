<?php

namespace Jurager\Filterable\Applying;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\FieldResolverInterface;

final class MethodResolver implements FieldResolverInterface
{
    /**
     * @param Closure $hook Signature: (Builder, string $name, mixed $value): bool
     */
    public function __construct(
        private readonly Closure $hook,
    ) {
    }

    public function resolve(Builder $query, string $name, mixed $value, Model $model): bool
    {
        return ($this->hook)($query, $name, $value);
    }
}
