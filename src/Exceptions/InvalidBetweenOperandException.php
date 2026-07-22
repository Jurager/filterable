<?php

declare(strict_types=1);

namespace Jurager\Filterable\Exceptions;

/** Thrown when a between/not_between operator receives an invalid number of operands. */
final class InvalidBetweenOperandException extends FilterableException
{
    public function __construct(string $operator)
    {
        parent::__construct("Operator '{$operator}' requires exactly 2 values.");
    }
}