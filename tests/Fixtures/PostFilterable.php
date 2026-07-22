<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Jurager\Filterable\Filterable;

class PostFilterable extends Filterable
{
    protected array $filterable = [
        'title'                 => ['eq', 'like', 'ne'],
        'status'                => ['eq', 'in', 'nin'],
        'price'                 => ['eq', 'gt', 'gte', 'lt', 'lte', 'between', 'not_between'],
        'is_active',
        'published_at'          => ['null', 'not_null'],
        'category.name'         => ['eq', 'like'],
        'tags.name'             => ['eq', 'in'],
        'tags.pivot.weight'     => ['gte', 'lte'],
        'prices.price_type_id'  => ['eq', 'in'],
    ];

    protected array $sortable = ['id', 'title', 'price', 'created_at'];

    /**
     * Custom filter method — dispatched for the 'featured' key, which is
     * intentionally absent from $filterable.
     */
    protected function filterFeatured(Builder $query, mixed $value): void
    {
        if ($value) {
            $query->where('is_active', true)->where('price', '>', 100);
        }
    }
}
