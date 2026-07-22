<?php

declare(strict_types=1);

namespace Jurager\Filterable\Support;

/** Value object representing parsed filter conditions. */
readonly class ParsedFilters
{
    /** Prefix marking a filter key as an eager-load constraint. */
    public const string INCLUDED_PREFIX = 'included.';

    public function __construct(
        public array $filters,
        public array $orGroups,
        public array $andGroups,
        public array $included,
        public array $allowed,
    ) {
    }

    /** Return a new instance with sanitized filter arrays. */
    public function withSanitized(array $filters, array $orGroups, array $andGroups): static
    {
        return new static(
            filters:   $filters,
            orGroups:  $orGroups,
            andGroups: $andGroups,
            included:  $this->included,
            allowed:   $this->allowed,
        );
    }

    /** Extract included relation filters from an array, stripping the prefix. */
    public static function extractIncluded(array $filter): array
    {
        $included = [];

        foreach ($filter as $key => $value) {
            if (is_string($key) && str_starts_with($key, self::INCLUDED_PREFIX)) {
                $included[substr($key, strlen(self::INCLUDED_PREFIX))] = $value;
            }
        }

        return $included;
    }
}