<?php

declare(strict_types=1);

namespace Jurager\Filterable\Tests\Fixtures;

class UppercaseSanitizer
{
    public function __invoke(mixed $value): string
    {
        return strtoupper((string) $value);
    }
}
