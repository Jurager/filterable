<?php

declare(strict_types=1);

namespace Jurager\Filterable\Parsing;

use Jurager\Filterable\Support\FilterOperator;
use Jurager\Filterable\Support\ParsedFilters;

/** Transform raw filter input into a structured ParsedFilters instance. */
class FilterParser
{
    /** Parse raw filter input into a structured ParsedFilters instance. */
    public function parse(array $raw, array $filterable): ParsedFilters
    {
        $orGroups  = $this->extractOrGroups($raw);
        $andGroups = $this->extractAndGroups($raw);
        $included  = $this->extractIncludedFilters($raw);

        $raw = $this->flattenBracketFilters($this->removeEmptyValues($raw));

        $cleanIncluded = [];
        foreach ($included as $key => $v) {
            if ($v !== null && $v !== '') {
                $cleanIncluded[$key] = $v;
            }
        }

        $cleanOrGroups = [];
        foreach ($orGroups as $group) {
            $cleaned = $this->flattenBracketFilters($this->removeEmptyValues($group));
            if (! empty($cleaned)) {
                $cleanOrGroups[] = $cleaned;
            }
        }

        $cleanAndGroups = [];
        foreach ($andGroups as $group) {
            $cleaned = $this->flattenBracketFilters($this->removeEmptyValues($group));
            if (! empty($cleaned)) {
                $cleanAndGroups[] = $cleaned;
            }
        }

        return new ParsedFilters(
            filters:   $raw,
            orGroups:  $cleanOrGroups,
            andGroups: $cleanAndGroups,
            included:  $cleanIncluded,
            allowed:   $this->resolveAllowed($filterable),
        );
    }

    /** Recursively remove null and empty-string values from a filter array. */
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

    /** Clean a single filter value, returning null when it should be dropped. */
    private function cleanValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $cleaned = [];

            if (array_is_list($value)) {
                foreach ($value as $v) {
                    if ($v !== null && $v !== '') {
                        $cleaned[] = $v;
                    }
                }

                return empty($cleaned) ? null : $cleaned;
            }

            foreach ($value as $k => $v) {
                $cv = $this->cleanValue($v);

                if ($cv !== null) {
                    $cleaned[$k] = $cv;
                }
            }

            return empty($cleaned) ? null : $cleaned;
        }

        return $value;
    }

    /** Flatten bracket-format relation filters to dot notation. */
    private function flattenBracketFilters(array $filters): array
    {
        $result = [];

        foreach ($filters as $key => $value) {
            if (is_string($key) && is_array($value) && ! array_is_list($value) && ! $this->allKeysAreOperators($value)) {
                foreach ($value as $subKey => $subValue) {
                    $result["{$key}.{$subKey}"] = $subValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /** Check whether all keys in an array are known filter operator aliases. */
    private function allKeysAreOperators(array $value): bool
    {
        if (empty($value)) {
            return false;
        }

        foreach ($value as $key => $_) {
            if (! FilterOperator::isAlias((string) $key)) {
                return false;
            }
        }

        return true;
    }

    /** Extract and remove OR groups from the raw filter array. */
    private function extractOrGroups(array &$raw): array
    {
        if (! isset($raw['or'])) {
            return [];
        }

        $or = $raw['or'];
        unset($raw['or']);

        if (! is_array($or) || empty($or)) {
            return [];
        }

        if (array_is_list($or)) {
            $result = [];
            foreach ($or as $group) {
                if (is_array($group) && ! empty($group)) {
                    $result[] = $group;
                }
            }
            return $result;
        }

        $allOperators = true;
        foreach ($or as $key => $_) {
            if (! in_array((string) $key, FilterOperator::aliases(), true)) {
                $allOperators = false;
                break;
            }
        }

        if ($allOperators) {
            return [];
        }

        $result = [];
        foreach ($or as $field => $value) {
            $result[] = [(string) $field => $value];
        }

        return $result;
    }

    /** Extract and remove AND groups from the raw filter array. */
    private function extractAndGroups(array &$raw): array
    {
        if (! isset($raw['and'])) {
            return [];
        }

        $and = $raw['and'];
        unset($raw['and']);

        if (! is_array($and) || ! array_is_list($and)) {
            return [];
        }

        $result = [];
        foreach ($and as $group) {
            if (is_array($group) && ! empty($group)) {
                $result[] = $group;
            }
        }

        return $result;
    }

    /** Extract and remove included relation filters from the raw filter array. */
    private function extractIncludedFilters(array &$raw): array
    {
        $included = ParsedFilters::extractIncluded($raw);

        foreach (array_keys($included) as $key) {
            unset($raw[ParsedFilters::INCLUDED_PREFIX . $key]);
        }

        return $included;
    }

    /** Resolve the allowed field map from the $filterable declaration. */
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