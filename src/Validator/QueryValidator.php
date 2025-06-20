<?php

namespace Apicalypse\Validator;

use InvalidArgumentException;

class QueryValidator
{
    /**
     * @throws InvalidArgumentException
     **/
    public static function nonEmptyString(string $value, string $paramName = 'value'): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("$paramName cannot be empty or whitespace.");
        }
    }

    /**
     * @throws InvalidArgumentException
     **/
    public static function positiveInt(int $value, string $paramName = 'value'): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException("$paramName must be positive.");
        }
    }

    /**
     * @throws InvalidArgumentException
     **/
    public static function nonNegativeInt(int $value, string $paramName = 'value'): void
    {
        if ($value < 0) {
            throw new InvalidArgumentException("$paramName must be zero or positive.");
        }
    }

    /**
     * @throws InvalidArgumentException
     **/
    public static function validateEnum(string $value, array $allowedValues, string $paramName = 'value'): void
    {
        if (!in_array(strtolower($value), array_map('strtolower', $allowedValues), true)) {
            throw new InvalidArgumentException(
                sprintf("%s must be one of: %s", $paramName, implode(', ', $allowedValues))
            );
        }
    }
}
