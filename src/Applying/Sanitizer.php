<?php

namespace Jurager\Filterable\Applying;

/**
 * Applies field-level sanitizers to filter values.
 */
class Sanitizer
{
    /**
     * @param array $sanitizers Field → handler map. Handler is a callable, function name, or array of both.
     */
    public function __construct(private readonly array $sanitizers)
    {
    }

    /**
     * Apply sanitizers to a flat filter array.
     * @param array $filters
     * @return array
     */
    public function apply(array $filters): array
    {
        foreach ($this->sanitizers as $field => $handler) {
            if (isset($filters[$field])) {
                $filters[$field] = $this->sanitizeValue($filters[$field], $handler);
            }
        }

        return $filters;
    }

    /**
     * Apply sanitizers to each group in a groups array.
     * @param array $groups
     * @return array
     */
    public function applyToGroups(array $groups): array
    {
        return array_map($this->apply(...), $groups);
    }

    /**
     * Sanitize a single filter value through one or more handlers.
     * @param mixed $value
     * @param mixed $handler
     * @return mixed
     */
    private function sanitizeValue(mixed $value, mixed $handler): mixed
    {
        $handlers = is_array($handler) ? $handler : [$handler];

        $apply = function (string $v) use ($handlers): string {
            foreach ($handlers as $h) {
                $result = is_callable($h)
                    ? $h($v)
                    : (is_string($h) && function_exists($h) ? $h($v) : $v);

                if (is_string($result) || is_numeric($result)) {
                    $v = (string) $result;
                }
            }

            return $v;
        };

        if (is_string($value)) {
            return $apply($value);
        }

        if (is_array($value)) {
            return array_map(static fn ($v) => is_string($v) ? $apply($v) : $v, $value);
        }

        return $value;
    }
}
