<?php

declare(strict_types=1);

namespace Jurager\Filterable\Exceptions;

/** Thrown when a filter list exceeds the maximum allowed number of values. */
final class TooManyValuesException extends FilterableException
{
    public function __construct(int $max)
    {
        parent::__construct("Filter list exceeds maximum of {$max} values.");
    }
}