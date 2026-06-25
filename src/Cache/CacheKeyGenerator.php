<?php

namespace Jurager\Filterable\Cache;

/**
 * Generates deterministic cache keys for filterable queries.
 */
class CacheKeyGenerator
{
    /**
     * @param string $prefix
     */
    public function __construct(private readonly string $prefix = 'filterable')
    {
    }

    /**
     * Build a deterministic cache key from class, table, filters, method, and args.
     * @param string $filterableClass
     * @param string $table
     * @param array $filters
     * @param string $method
     * @param array $args
     * @return string
     */
    public function generate(
        string $filterableClass,
        string $table,
        array $filters,
        string $method = 'get',
        array $args = [],
    ): string {
        return implode(':', [
            $this->prefix,
            md5($filterableClass . ':' . $table),
            md5(json_encode($this->deepSort($filters), JSON_THROW_ON_ERROR)),
            $method,
            md5(json_encode($args, JSON_THROW_ON_ERROR)),
        ]);
    }

    private function deepSort(array $arr): array
    {
        ksort($arr);

        foreach ($arr as &$value) {
            if (is_array($value)) {
                $value = $this->deepSort($value);
            }
        }

        return $arr;
    }
}
