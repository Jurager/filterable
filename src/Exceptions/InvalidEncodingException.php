<?php

declare(strict_types=1);

namespace Jurager\Filterable\Exceptions;

/** Thrown when a filter key or string value is not valid UTF-8. */
final class InvalidEncodingException extends FilterableException
{
    public function __construct()
    {
        parent::__construct('Filter parameters must be valid UTF-8.');
    }
}
