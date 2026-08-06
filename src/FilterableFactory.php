<?php

declare(strict_types=1);

namespace Jurager\Filterable;

use Jurager\Filterable\Contracts\FieldResolver;
use Jurager\Filterable\Contracts\RelationResolver;
use Jurager\Filterable\Contracts\SortResolver;

/** Build a Filterable instance and inject registered resolvers. */
class FilterableFactory
{
    /**
     * Create a new Filterable instance with the given configuration.
     *
     * @param array<int, object|class-string> $resolvers Resolvers declared on the model, applied on top of the container-tagged ones.
     */
    public function make(array $filterable, array $sortable, array $sanitizers, array $resolvers = []): Filterable
    {
        $instance = new Filterable($filterable, $sortable, $sanitizers);

        foreach ([...app()->tagged(FilterableServiceProvider::RESOLVER_TAG), ...$this->instantiate($resolvers)] as $resolver) {
            if ($resolver instanceof FieldResolver) {
                $instance->addFieldResolver($resolver);
            }

            if ($resolver instanceof RelationResolver) {
                $instance->addRelationResolver($resolver);
            }

            if ($resolver instanceof SortResolver) {
                $instance->addSortResolver($resolver);
            }
        }

        return $instance;
    }

    /**
     * Resolve class-string resolvers through the container, passing instances through untouched.
     *
     * @param array<int, object|class-string> $resolvers
     * @return array<int, object>
     */
    private function instantiate(array $resolvers): array
    {
        return array_map(static fn (object|string $resolver): object => is_string($resolver) ? app($resolver) : $resolver, $resolvers);
    }
}
