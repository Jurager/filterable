<?php

declare(strict_types=1);

namespace Jurager\Filterable\Exceptions;

/** Thrown when the number of filter parameters exceeds the configured maximum. */
final class TooManyFiltersException extends FilterableException
{
    public function __construct(int $count, int $max)
    {
        parent::__construct("Too many filter parameters: {$count} given, {$max} allowed.");
    }
}