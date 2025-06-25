<?php

namespace Apicalypse;

use Apicalypse\Enum\ComparisonOperator;
use Apicalypse\Enum\LogicalOperator;
use Apicalypse\Validator\QueryValidator;
use Apicalypse\Enum\SortDirection;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Apicalypse-style query builder for IGDB and similar APIs.
 */
class QueryBuilder
{
    public const ERROR_CONDITION_WHERE_NOT_INITIAL = 'Initial where condition is already set.';
    public const ERROR_CONDITION_ORWHERE_FIRST = 'Cannot start conditions with orWhere.';
    private bool $strictMode;
    private array $fields = [];
    private array $exclude = [];
    private array $conditions = [];
    private array $sort = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private ?string $searchTerm = null;


    public function __construct(bool $strictMode = false)
    {
        $this->strictMode = $strictMode;
    }

    /**
     * Converts the query to a string.
     * If building fails and strict mode is off, logs the error and returns a fallback error message.
     *
     * @return string The built query string or an error message if building fails.
     * @throws Throwable
     */
    public function __toString(): string
    {
        try {
            return $this->build();
        } catch (Throwable $e) {
            if ($this->strictMode) {
                throw $e;
            }

            error_log('Error building query: ' . $e->getMessage());
            return '[ERROR] [INVALID __toString CALL]';
        }
    }


    // ───────────────────── Public API ─────────────────────

    public function isStrictModeEnabled(): bool
    {
        return $this->strictMode;
    }

    public function enableStrictMode(bool $enabled = true): static
    {
        $this->strictMode = $enabled;
        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @throws InvalidArgumentException if fields[] is empty or one field is empty or blank.
     */
    public function fields(string ...$fields): static
    {
        if (empty($fields)) {
            throw new InvalidArgumentException("fields cannot be empty");
        }

        if (count($fields) === 1 && $fields[0] === '*') {
            $this->fields = ['*'];
            return $this;
        }

        foreach ($fields as $i => $field) {
            $fields[$i] = $this->validateAndTrimDotField($field);
        }

        $this->fields = $fields;
        return $this;
    }

    /**
     * Add fields to the existing fields list.
     *
     * Unlike fields(), which replaces the entire selection,
     * this method appends new fields to the current list,
     * ensuring no duplicates.
     *
     * @throws InvalidArgumentException if no fields are passed
     *         or any field is empty or blank.
     */
    public function addFields(string ...$fields): static
    {
        if (empty($fields)) {
            throw new InvalidArgumentException("fields cannot be empty");
        }

        foreach ($fields as $i => $field) {
            $fields[$i] = $this->validateAndTrimDotField($field);
        }

        $this->fields = array_unique([...$this->fields, ...$fields]);
        return $this;
    }

    public function getExcludedFields(): array
    {
        return $this->exclude;
    }

    /**
     * @throws InvalidArgumentException if fields[] is empty or one field is empty or blank.
     */
    public function exclude(string ...$fields): static
    {
        if (empty($fields)) {
            throw new InvalidArgumentException("fields cannot be empty");
        }

        foreach ($fields as $i => $field) {
            $fields[$i] = $this->validateAndTrimDotField($field);
        }

        $this->exclude = array_unique([...$this->exclude, ...$fields]);
        return $this;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Set initial where condition.
     *
     * @throws InvalidArgumentException if condition is empty or blank.
     * @throws LogicException if a where() condition is already set.
     */
    public function where(string $condition): static
    {
        $condition = $this->validateAndTrimString(value: $condition, paramName: 'where condition');

        if (!empty($this->conditions)) {
            throw new LogicException(self::ERROR_CONDITION_WHERE_NOT_INITIAL);
        }

        $this->conditions[] = ['operator' => null, 'condition' => $condition];
        return $this;
    }

    /**
     * Add AND where condition ('&').
     *
     * @throws InvalidArgumentException if condition is empty or blank.
     */
    public function andWhere(string $condition): static
    {
        $condition = $this->validateAndTrimString(value: $condition, paramName: 'andWhere condition');

        if (empty($this->conditions)) {
            // first condition: no operator, act like where()
            $this->conditions[] = ['operator' => null, 'condition' => $condition];
        } else {
            $this->conditions[] = ['operator' => LogicalOperator::AND, 'condition' => $condition];
        }

        return $this;
    }

    /**
     * Add OR where condition ('|'). This cannot be the first condition.
     *
     * @throws InvalidArgumentException if condition is empty or blank.
     * @throws LogicException if this is the first condition.
     */
    public function orWhere(string $condition): static
    {
        $condition = $this->validateAndTrimString(value: $condition, paramName: 'orWhere condition');

        if (empty($this->conditions)) {
            throw new LogicException(self::ERROR_CONDITION_ORWHERE_FIRST);
        }

        $this->conditions[] = ['operator' => LogicalOperator::OR, 'condition' => $condition];
        return $this;
    }

    public function getSortFields(): array
    {
        return $this->sort;
    }

    /**
     * Add sorting on a field.
     *
     * @throws InvalidArgumentException if field is empty or blank.
     */
    public function sort(string $field, SortDirection $direction = SortDirection::ASC): static
    {
        $field = $this->validateAndTrimString(value: $field, paramName: 'sort field');

        $this->sort[] = "$field $direction->value";
        return $this;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Limit results to return.
     *
     * @throws InvalidArgumentException if limit is not a positive integer.
     */
    public function limit(int $limit): static
    {
        QueryValidator::positiveInt($limit, 'limit');

        $this->limit = $limit;
        return $this;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * Set first result.
     *
     * @throws InvalidArgumentException if offset is not a non-negative integer.
     */
    public function offset(int $offset): static
    {
        QueryValidator::nonNegativeInt($offset, 'offset');

        $this->offset = $offset;
        return $this;
    }

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }


    /**
     * @throws InvalidArgumentException if term is empty or blank.
     */
    public function search(string $term): static
    {
        $term = $this->validateAndTrimString(value: $term, paramName: 'search term');

        $this->searchTerm = $term;
        return $this;
    }

    /**
     * Build a condition string from field, value, and operator.
     *
     * Supports:
     * - Scalars (string, int, float, bool)
     * - Arrays (for IN clauses)
     *
     * Examples:
     *   buildCondition('category', [1,2,3], ComparisonOperator::CONTAINS_ANY) => "category = (1, 2, 3)"
     *   buildCondition('name', 'Mario', ComparisonOperator::NEQ) => 'name != "Mario"'
     *   buildCondition('active', true) => 'active = 1'
     */
    public static function buildCondition(
        string $field,
        mixed $value,
        ComparisonOperator $operator = ComparisonOperator::EQ,
    ): string
    {
        $field = trim($field);

        if (empty($field)) {
            throw new InvalidArgumentException("Field cannot be empty");
        }

        if (in_array($operator, ComparisonOperator::arrayOperators(), true)) {
            return self::formatArrayCondition($field, $value, $operator);
        }

        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Value for operator '$operator->value' must be scalar type.");
        }

        if (is_string($value) && trim($value) === '') {
            throw new InvalidArgumentException("Value for operator '$operator->value' cannot be empty.");
        }

        return sprintf('%s %s %s', $field, $operator->value, self::formatValue($value));
    }

    /**
     * Build the WHERE clause from the conditions.
     *
     * @throws LogicException if an unexpected operator is found in conditions.
     */
    public function buildWhere(): ?string
    {
        if (empty($this->conditions)) {
            return null;
        }

        $queryParts = [];
        foreach ($this->conditions as $index => $entry) {
            $condition = $entry['condition'];
            $operator = $entry['operator'];

            if ($index === 0) {
                // first condition, no operator prefix
                $queryParts[] = $condition;
            } else {
                // for subsequent conditions, prepend operator as string
                if ($operator === LogicalOperator::AND) {
                    $queryParts[] = '& ' . $condition;
                } elseif ($operator === LogicalOperator::OR) {
                    $queryParts[] = '| ' . $condition;
                } else {
                    $operatorValue = $operator instanceof LogicalOperator ? $operator->value : (string)$operator;
                    throw new LogicException("Unexpected operator '$operatorValue' in conditions.");
                }
            }
        }

        return 'where ' . implode(' ', $queryParts) . ';';
    }

    /**
     * Build the complete query string.
     */
    public function build(): string
    {
        $parts = [];

        if (!empty($this->fields)) {
            $parts[] = 'fields ' . implode(',', $this->fields) . ';';
        }

        if (!empty($this->exclude)) {
            $parts[] = 'exclude ' . implode(',', $this->exclude) . ';';
        }

        if ($where = $this->buildWhere()) {
            $parts[] = $where;
        }

        if (!empty($this->sort)) {
            $parts[] = 'sort ' . implode(',', $this->sort) . ';';
        }

        if ($this->limit !== null) {
            $parts[] = "limit $this->limit;";
        }

        // Explicitly check for null to avoid not adding "offset " if set to 0
        if (!is_null($this->offset)) {
            $parts[] = "offset $this->offset;";
        }

        if ($this->searchTerm) {
            $parts[] = 'search ' . self::quote($this->searchTerm) . ';';
        }

        return implode(' ', $parts);
    }

    /**
     * Clear the current query state.
     * Resets all properties to their initial state.
     */
    public function clear(): static
    {
        $this->fields = $this->exclude = $this->conditions = $this->sort = [];
        $this->limit = $this->offset = $this->searchTerm = null;

        return $this;
    }

    // ───────────────────── Helpers ─────────────────────

    private static function formatArrayCondition(string $field, array $value, ComparisonOperator $operator): string
    {
        if (empty($value)) {
            throw new InvalidArgumentException("Value must be a non-empty array for operator '$operator->value'.");
        }

        foreach ($value as $i => $el) {
            if (!is_scalar($el)) {
                throw new InvalidArgumentException("Element $i for operator '$operator->value' must be scalar.");
            }
        }

        $formatted = implode(',', array_map(fn($el) => self::formatValue($el), $value));

        return match ($operator) {
            ComparisonOperator::CONTAINS_ALL      => sprintf('%s = [%s]', $field, $formatted),
            ComparisonOperator::NOT_CONTAINS_ALL  => sprintf('%s = ![%s]', $field, $formatted),
            ComparisonOperator::CONTAINS_ANY      => sprintf('%s = (%s)', $field, $formatted),
            ComparisonOperator::NOT_CONTAINS_ANY  => sprintf('%s = !(%s)', $field, $formatted),
            ComparisonOperator::CONTAINS_EXACTLY  => sprintf('%s = {%s}', $field, $formatted),
            default => throw new LogicException("Unsupported array operator '$operator->value'")
        };
    }

    /**
     * @throws InvalidArgumentException if the value is empty or blank.
     */
    private function validateAndTrimString(string $value, string $paramName): string
    {
        QueryValidator::nonEmptyString(value: $value, paramName: $paramName);
        return trim($value);
    }

    private function validateAndTrimDotField(string $field): string
    {
        $field = $this->validateAndTrimString($field, 'field');

        foreach (explode('.', $field) as $segment) {
            $this->validateAndTrimString($segment, "segment in '$field'");
        }

        return $field;
    }

    private static function quote(string $value): string
    {
        $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
        return "\"$value\"";
    }

    private static function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            return (string)$value;
        } else {
            return self::quote((string)$value);
        }
    }
}
