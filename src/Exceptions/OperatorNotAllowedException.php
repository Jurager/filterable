<?php

declare(strict_types=1);

namespace Jurager\Filterable\Exceptions;

/** Thrown when a filter operator is not permitted for a specific field. */
final class OperatorNotAllowedException extends FilterableException
{
    public function __construct(string $operator)
    {
        parent::__construct("Filter operator '{$operator}' is not allowed for this field.");
    }
}