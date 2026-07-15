<?php

namespace Jurager\Filterable\Applying;

class Sanitizer
{
    /**
     * Normalized sanitizers
     *
     * @var array<string, list<callable>>
     */
    private readonly array $sanitizers;

    public function __construct(array $sanitizers)
    {
        $normalized = [];

        foreach ($sanitizers as $field => $handler) {
            $handlers = [];

            foreach (is_array($handler) ? $handler : [$handler] as $item) {
                if (is_callable($item)) {
                    $handlers[] = $item;
                }
            }

            if ($handlers) {
                $normalized[$field] = $handlers;
            }
        }

        $this->sanitizers = $normalized;
    }

    /**
     * Apply sanitizers to a flat filter array.
     *
     * @param array $filters
     * @return array
     */
    public function apply(array $filters): array
    {
        foreach ($filters as $field => $value) {
            if ($handlers = $this->sanitizers[$field] ?? null) {
                $filters[$field] = $this->sanitizeValue($value, $handlers);
            }
        }

        return $filters;
    }

    /**
     * Apply sanitizers to each group in a groups array.
     *
     * @param array $groups
     * @return array
     */
    public function applyToGroups(array $groups): array
    {
        return array_map($this->apply(...), $groups);
    }

    /**
     * Sanitize a single filter value through one or more handlers.
     *
     * @param mixed $value
     * @param list<callable> $handlers
     * @return mixed
     */
    private function sanitizeValue(mixed $value, array $handlers): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->sanitizeValue($v, $handlers), $value);
        }

        if (!is_string($value)) {
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