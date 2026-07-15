<?php

namespace Jurager\Filterable\Exceptions;

final class InvalidBetweenOperandException extends FilterableException
{
    public function __construct(string $operator)
    {
        parent::__construct("Operator '$operator' requires exactly 2 values.");
    }
}
