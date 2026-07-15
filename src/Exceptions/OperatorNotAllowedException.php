<?php

namespace Jurager\Filterable\Exceptions;

final class OperatorNotAllowedException extends FilterableException
{
    public function __construct(string $operator)
    {
        parent::__construct("Filter operator '$operator' is not allowed for this field.");
    }
}
