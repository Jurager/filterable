<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Jurager\Filterable\Contracts\SortResolver;

class TitleLengthSortResolver implements SortResolver
{
    public function resolve(object $query, string $field, string $direction, Model $model, array $context = []): bool
    {
        if ($field !== 'title_length') {
            return false;
        }

        $query->orderByRaw('length(title) ' . $direction);

        return true;
    }
}
