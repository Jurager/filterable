<?php

namespace Jurager\Filterable\Support;

/**
 * All supported filter operators.
 * Canonical names (enum values) match what users declare in $filterable config.
 * Symbolic aliases (=, !=, >, …) are resolved via fromAlias().
 */
enum FilterOperator: string
{
    case Eq         = 'eq';
    case Ne         = 'ne';
    case Gt         = 'gt';
    case Gte        = 'gte';
    case Lt         = 'lt';
    case Lte        = 'lte';
    case Like       = 'like';
    case In         = 'in';
    case Nin        = 'nin';
    case IsNull     = 'null';
    case IsNotNull  = 'not_null';
    case Between    = 'between';
    case NotBetween = 'not_between';
    case Tree       = 'tree';

    /**
     * Resolve any alias — canonical ('eq') or symbolic ('=') — to a case.
     * @param string $alias
     * @return static|null
     */
    public static function fromAlias(string $alias): ?self
    {
        return match ($alias) {
            '='  => self::Eq,
            '!=' => self::Ne,
            '>'  => self::Gt,
            '>=' => self::Gte,
            '<'  => self::Lt,
            '<=' => self::Lte,
            default => self::tryFrom($alias),
        };
    }

    /**
     * Check whether a string is a valid operator alias.
     * @param string $key
     * @return bool
     */
    public static function isAlias(string $key): bool
    {
        return self::fromAlias($key) !== null;
    }

    /**
     * Get all valid alias strings.
     * @return array
     */
    public static function aliases(): array
    {
        return [...array_column(self::cases(), 'value'), '=', '!=', '>', '>=', '<', '<='];
    }
}
