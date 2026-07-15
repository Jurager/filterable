<?php

namespace Jurager\Filterable\Exceptions;

final class TooManyFiltersException extends FilterableException
{
    public function __construct(int $count, int $max)
    {
        parent::__construct("Too many filter parameters: $count given, $max allowed.");
    }
}
