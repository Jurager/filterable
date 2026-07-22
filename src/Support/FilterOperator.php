<?php

declare(strict_types=1);

namespace Jurager\Filterable\Support;

/** Supported filter operators and their symbolic aliases. */
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

    /** Resolve a canonical or symbolic alias to its corresponding enum case. */
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

    /** Determine if a given string is a valid operator alias. */
    public static function isAlias(string $key): bool
    {
        return self::fromAlias($key) !== null;
    }

    /** Get all valid operator alias strings. */
    public static function aliases(): array
    {
        return [...array_column(self::cases(), 'value'), '=', '!=', '>', '>=', '<', '<='];
    }
}