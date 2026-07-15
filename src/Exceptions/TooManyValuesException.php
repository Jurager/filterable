<?php

namespace Jurager\Filterable\Exceptions;

final class TooManyValuesException extends FilterableException
{
    public function __construct(int $max)
    {
        parent::__construct("Filter list exceeds maximum of $max values.");
    }
}
