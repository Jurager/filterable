<?php

declare(strict_types=1);

namespace Jurager\Filterable;

use Jurager\Filterable\Contracts\FieldResolver;
use Jurager\Filterable\Contracts\RelationResolver;
use Jurager\Filterable\Contracts\SortResolver;

/** Build a Filterable instance and inject registered resolvers. */
class FilterableFactory
{
    /** Create a new Filterable instance with the given configuration. */
    public function make(array $filterable, array $sortable, array $cache, array $sanitizers): Filterable
    {
        $instance = new Filterable($filterable, $sortable, $cache, $sanitizers);

        foreach (app()->tagged(FilterableServiceProvider::RESOLVER_TAG) as $resolver) {
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
}