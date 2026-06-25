<?php

namespace Jurager\Filterable\Support;

/**
 * Value object produced by FilterParser.
 */
readonly class ParsedFilters
{
    /**
     * @param array $filters  Plain field → value conditions.
     * @param array $orGroups  Groups of conditions joined with OR.
     * @param array $andGroups Groups of conditions joined with AND; conditions within each group are OR'd.
     * @param array $included  Extracted filter[included.*] → value map.
     * @param array $allowed   Resolved field → operator config from $filterable.
     */
    public function __construct(
        public array $filters,
        public array $orGroups,
        public array $andGroups,
        public array $included,
        public array $allowed,
    ) {
    }

    /**
     * Return a new instance with sanitized filter arrays.
     * @param array $filters
     * @param array $orGroups
     * @param array $andGroups
     * @return static
     */
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
}
