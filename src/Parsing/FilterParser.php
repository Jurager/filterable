<?php

namespace Jurager\Filterable\Parsing;

use Jurager\Filterable\Support\FilterOperator;
use Jurager\Filterable\Support\ParsedFilters;

/**
 * Transforms a raw filter[] array into a structured ParsedFilters value object.
 */
class FilterParser
{
    /**
     * Parse raw filter input into a structured ParsedFilters instance.
     * @param array $raw
     * @param array $filterable
     * @return ParsedFilters
     */
    public function parse(array $raw, array $filterable): ParsedFilters
    {
        $orGroups  = $this->extractOrGroups($raw);
        $andGroups = $this->extractAndGroups($raw);
        $included  = $this->extractIncludedFilters($raw);

        $raw      = $this->removeEmptyValues($raw);
        $included = array_filter($included, static fn ($v) => $v !== null && $v !== '');

        $raw       = $this->flattenBracketFilters($raw);
        $orGroups  = array_values(array_filter(
            array_map(fn ($g) => $this->flattenBracketFilters($this->removeEmptyValues($g)), $orGroups),
            static fn ($g) => $g !== [],
        ));
        $andGroups = array_values(array_filter(
            array_map(fn ($g) => $this->flattenBracketFilters($this->removeEmptyValues($g)), $andGroups),
            static fn ($g) => $g !== [],
        ));

        return new ParsedFilters(
            filters:   $raw,
            orGroups:  $orGroups,
            andGroups: $andGroups,
            included:  $included,
            allowed:   $this->resolveAllowed($filterable),
        );
    }

    /**
     * Recursively remove null and empty-string values from a filter array.
     * @param array $filters
     * @return array
     */
    private function removeEmptyValues(array $filters): array
    {
        $result = [];

        foreach ($filters as $key => $value) {
            $cleaned = $this->cleanValue($value);

            if ($cleaned !== null) {
                $result[$key] = $cleaned;
            }
        }

        return $result;
    }

    /**
     * Clean a single filter value, returning null when it should be dropped.
     * @param mixed $value
     * @return mixed
     */
    private function cleanValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $cleaned = array_values(array_filter($value, static fn ($v) => $v !== null && $v !== ''));

                return $cleaned === [] ? null : $cleaned;
            }

            $cleaned = [];

            foreach ($value as $k => $v) {
                $cv = $this->cleanValue($v);

                if ($cv !== null) {
                    $cleaned[$k] = $cv;
                }
            }

            return $cleaned === [] ? null : $cleaned;
        }

        return $value;
    }

    /**
     * Flatten bracket-format relation filters to dot notation.
     * filter[categories][code]=123 → categories.code=123
     * filter[sku][like]=red stays unchanged (all inner keys are operators).
     * @param array $filters
     * @return array
     */
    private function flattenBracketFilters(array $filters): array
    {
        $result = [];

        foreach ($filters as $key => $value) {
            if (
                is_string($key) &&
                is_array($value) &&
                !array_is_list($value) &&
                !$this->allKeysAreOperators($value)
            ) {
                foreach ($value as $subKey => $subValue) {
                    $result[$key . '.' . $subKey] = $subValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Check whether all keys in an array are known filter operator aliases.
     * @param array $value
     * @return bool
     */
    private function allKeysAreOperators(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (!FilterOperator::isAlias((string) $key)) {
                return false;
            }
        }

        return $value !== [];
    }

    /**
     * Extract and remove OR groups from the raw filter array.
     * @param array $raw
     * @return array
     */
    private function extractOrGroups(array &$raw): array
    {
        if (!array_key_exists('or', $raw)) {
            return [];
        }

        $or = $raw['or'];
        unset($raw['or']);

        if (!is_array($or) || $or === []) {
            return [];
        }

        if (array_is_list($or)) {
            return array_values(array_filter($or, static fn ($g) => is_array($g) && $g !== []));
        }

        if (!array_diff(array_map('strval', array_keys($or)), FilterOperator::aliases())) {
            return [];
        }

        return array_map(static fn ($field, $value) => [(string) $field => $value], array_keys($or), $or);
    }

    /**
     * Extract and remove AND groups from the raw filter array.
     * @param array $raw
     * @return array
     */
    private function extractAndGroups(array &$raw): array
    {
        if (!array_key_exists('and', $raw)) {
            return [];
        }

        $and = $raw['and'];
        unset($raw['and']);

        if (!is_array($and) || $and === [] || !array_is_list($and)) {
            return [];
        }

        return array_values(array_filter($and, static fn ($g) => is_array($g) && $g !== []));
    }

    /**
     * Extract and remove included relation filters from the raw filter array.
     * @param array $raw
     * @return array
     */
    private function extractIncludedFilters(array &$raw): array
    {
        $included = [];

        foreach ($raw as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'included.')) {
                $included[substr($key, 9)] = $value;
                unset($raw[$key]);
            }
        }

        return $included;
    }

    /**
     * Resolve the allowed field map from the $filterable declaration.
     * @param array $filterable
     * @return array
     */
    private function resolveAllowed(array $filterable): array
    {
        $allowed = [];

        foreach ($filterable as $key => $config) {
            if (is_int($key)) {
                $allowed[$config] = ['eq'];
            } else {
                $allowed[$key] = $config;
            }
        }

        return $allowed;
    }
}
