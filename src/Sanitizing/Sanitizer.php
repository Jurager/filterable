<?php

declare(strict_types=1);

namespace Jurager\Filterable\Sanitizing;

/** Transform filter values before they reach the query builder. */
class Sanitizer
{
    /** @var array<string, list<callable>> */
    private readonly array $sanitizers;

    /** Initialize with a map of fields to their sanitizer handlers. */
    public function __construct(array $sanitizers)
    {
        $normalized = [];

        foreach ($sanitizers as $field => $handler) {
            $handlers = [];

            foreach (is_array($handler) ? $handler : [$handler] as $item) {
                if (is_string($item) && ! is_callable($item) && class_exists($item)) {
                    $item = app($item);
                }

                if (is_callable($item)) {
                    $handlers[] = $item;
                }
            }

            if (! empty($handlers)) {
                $normalized[$field] = $handlers;
            }
        }

        $this->sanitizers = $normalized;
    }

    /** Apply sanitizers to a flat filter array. */
    public function apply(array $filters): array
    {
        foreach ($filters as $field => $value) {
            if (isset($this->sanitizers[$field])) {
                $filters[$field] = $this->sanitizeValue($value, $this->sanitizers[$field]);
            }
        }

        return $filters;
    }

    /** Apply sanitizers to each group in a groups array. */
    public function applyToGroups(array $groups): array
    {
        return array_map($this->apply(...), $groups);
    }

    /** Sanitize a single filter value through one or more handlers. */
    private function sanitizeValue(mixed $value, array $handlers): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->sanitizeValue($v, $handlers), $value);
        }

        if (! is_string($value)) {
            return $value;
        }

        foreach ($handlers as $handler) {
            $result = $handler($value);

            if (is_string($result) || is_numeric($result)) {
                $value = (string) $result;
            }
        }

        return $value;
    }
}