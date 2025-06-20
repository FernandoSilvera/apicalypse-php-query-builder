# Apicalypse PHP Query Builder

A lightweight, expressive query builder for Apicalypse-style APIs (like IGDB), written in pure PHP.

Build readable and validated API queries using a fluent interface. Supports field selection, filters, sorting, pagination, and search — with optional strict mode for better development error handling.

## Installation

Install via Composer:

    composer require apicalypse/php-query-builder

## Features

- Select and exclude fields
- AND/OR conditional chaining
- Comparison operator support
- Pagination (limit + offset)
- Search support
- Sort by any field (ASC/DESC)
- Optional strict mode for debugging
- Input validation for safe query generation

## Basic Usage

    use Apicalypse\QueryBuilder;
    use Apicalypse\Enum\ComparisonOperator;
    use Apicalypse\Enum\SortDirection;

    $query = new QueryBuilder();

    $query->select('name', 'release.date', 'game.platform.name')
        ->exclude('summary')
        ->where('rating > 80')
        ->andWhere(QueryBuilder::buildCondition('platforms', [6, 48], ComparisonOperator::CONTAINS_ANY))
        ->orWhere('(release.date < "2023-01-01" & release.date > "2022-01-01")')
        ->sort('release.date', SortDirection::DESC)
        ->limit(10)
        ->offset(0)
        ->search('Hollow Knight');
    
    echo $query->build();

Output:

    fields name,release.date,game.platform.name;
    exclude summary;
    where rating > 80 & platforms = (6, 48) | (release.date < "2023-01-01" & release.date > "2022-01-01");
    sort release.date desc;
    limit 10;
    offset 0;
    search "Hollow Knight";

### Strict Mode

Enable strict mode to throw exceptions during query building instead of returning a fallback string:

    $query = new QueryBuilder(strict: true);

In non-strict mode, `__toString()` catches build exceptions and returns:

    [ERROR] [INVALID __toString CALL]

### BuildCondition Examples

    QueryBuilder::buildCondition('name', 'Mario');
    // name = "Mario"
    
    QueryBuilder::buildCondition('rating', 90, ComparisonOperator::GT);
    // rating > 90
    
    QueryBuilder::buildCondition('tags', [1, 2], ComparisonOperator::CONTAINS_ANY);
    // tags = (1, 2)

## Public Methods

- select(string ...$fields)
- addSelect(string ...$fields)
- exclude(string ...$fields)
- where(string $condition)
- andWhere(string $condition)
- orWhere(string $condition)
- sort(string $field, SortDirection $direction = SortDirection::ASC)
- limit(int $count)
- offset(int $offset)
- search(string $term)
- buildCondition(...) — static utility for field/value/operator
- clear()
- build() — builds the full query string
- __toString() — safe string conversion with error fallback

## Testing

Run PHPUnit tests:

    vendor/bin/phpunit

## Contributing

Contributions are welcome! Please ensure:

- PSR-12 coding standards
- Tests cover new features or edge cases
- Public API changes are clearly documented
- 

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE) — Made with ❤️ for video game enthusiasts!