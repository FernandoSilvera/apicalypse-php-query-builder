<?php

namespace Apicalypse\Enum;

enum ComparisonOperator: string
{
    case EQ = '=';
    case NEQ = '!=';
    case GT = '>';
    case GTE = '>=';
    case LT = '<';
    case LTE = '<=';
    case CONTAINS_ALL = '[]';
    case NOT_CONTAINS_ALL = '![]';
    case CONTAINS_ANY = '()';
    case NOT_CONTAINS_ANY = '!()';
    case CONTAINS_EXACTLY = '{}';

    /**
     * Return all array-based operators
     * @return ComparisonOperator[]
     */
    public static function arrayOperators(): array
    {
        return [
            self::CONTAINS_ALL,
            self::NOT_CONTAINS_ALL,
            self::CONTAINS_ANY,
            self::NOT_CONTAINS_ANY,
            self::CONTAINS_EXACTLY,
        ];
    }
}