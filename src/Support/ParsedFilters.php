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

    /**
     * Read a filter key as a list of positive integer IDs.
     *
     * @return array<int, int>
     */
    public static function ids(array $filter, string $key): array
    {
        $value = $filter[$key] ?? null;

        if (is_array($value) && ! array_is_list($value)) {
            $value = $value['in'] ?? $value['eq'] ?? null;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('intval', $items), static fn (int $id): bool => $id > 0));
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