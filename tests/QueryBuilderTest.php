<?php

namespace Apicalypse\Tests;

use Apicalypse\Enum\ComparisonOperator;
use Apicalypse\Enum\LogicalOperator;
use Apicalypse\Enum\SortDirection;
use Apicalypse\QueryBuilder;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Throwable;

class QueryBuilderTest extends TestCase
{
    public function testIsStrictModeEnabledDefault(): void
    {
        $builder = new QueryBuilder();
        $this->assertFalse($builder->isStrictModeEnabled());
    }

    public function testEnableStrictMode(): void
    {
        $builder = (new QueryBuilder())
            ->enableStrictMode();

        $this->assertTrue($builder->isStrictModeEnabled());
    }

    public function testStrictModeEnableInConstruct(): void
    {
        $builder = new QueryBuilder(true);

        $this->assertTrue($builder->isStrictModeEnabled());
    }

    public function testGetSelectedFieldsOnDefaultInit(): void
    {
        $builder = (new QueryBuilder());
        $this->assertEmpty($builder->getSelectedFields());
    }

    public function testSelect(): void
    {
        $builder = (new QueryBuilder())
            ->select('id', 'name', 'game.release', 'game.release.date.day');

        $this->assertSame(['id', 'name', 'game.release', 'game.release.date.day'], $builder->getSelectedFields());
    }

    public function testSelectOverride(): void
    {
        $builder = (new QueryBuilder())
            ->select('id')
            ->select('title');

        $this->assertSame(['title'], $builder->getSelectedFields());
    }

    public function testAddSelectAfterAddSelect(): void
    {
        $builder = (new QueryBuilder())
            ->addSelect('id', 'name')
            ->select('email', 'name');

        $this->assertSame(['email', 'name'], $builder->getSelectedFields());
    }

    public function testSelectThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->select();
    }

    public function testSelectThrowsExceptionOnEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->select('id', 'name', '');
    }

    public function testSelectWithWildcard(): void
    {
        $builder = (new QueryBuilder())
            ->select('*');

        $this->assertSame(['*'], $builder->getSelectedFields());
    }

    public function testAddSelect(): void
    {
        $builder = (new QueryBuilder())
            ->addSelect('id', 'name');

        $this->assertSame(['id', 'name'], $builder->getSelectedFields());
    }

    public function testAddSelectChaining(): void
    {
        $builder = (new QueryBuilder())
            ->addSelect('id', 'name')
            ->addSelect('email', 'phone');

        $this->assertSame(['id', 'name', 'email', 'phone'], $builder->getSelectedFields());
    }

    public function testAddSelectAppendsDuplicateFields(): void
    {
        $builder = (new QueryBuilder())
            ->select('id', 'name')
            ->addSelect('email', 'name');

        $this->assertSame(['id', 'name', 'email'], $builder->getSelectedFields());
    }

    public function testAddSelectThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->addSelect();
    }

    public function testAddSelectThrowsExceptionOnEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->addSelect('id', '');
    }

    public function testGetExcludedFieldsOnDefaultInit(): void
    {
        $builder = (new QueryBuilder());
        $this->assertEmpty($builder->getExcludedFields());
    }

    public function testExclude(): void
    {
        $builder = (new QueryBuilder())
            ->exclude('id', 'slug', 'game.release', 'game.release.date.day');

        $this->assertSame(['id', 'slug', 'game.release', 'game.release.date.day'], $builder->getExcludedFields());
    }

    public function testExcludeAppendsFieldsWithoutDuplicates(): void
    {
        $builder = (new QueryBuilder())
            ->exclude('id')
            ->exclude('slug', 'id');

        $this->assertSame(['id', 'slug'], $builder->getExcludedFields());
    }

    public function testExcludeThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->exclude();
    }

    public function testExcludeThrowsExceptionOnEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->exclude('name', '');
    }

    public function testExcludeTrimsWhitespace(): void
    {
        $builder = (new QueryBuilder())
            ->exclude('  id  ', "\tslug\n\n");

        $this->assertSame(['id', 'slug'], $builder->getExcludedFields());
    }

    public function testGetConditionsOnDefaultInit(): void
    {
        $builder = new QueryBuilder();
        $this->assertEmpty($builder->getConditions());
    }

    public function testWhereSetsInitialCondition(): void
    {
        $builder = (new QueryBuilder())
            ->where('name = "Mario"');

        $conditions = $builder->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertSame(['operator' => null, 'condition' => 'name = "Mario"'], $conditions[0]);
    }

    public function testWhereThrowsIfCalledMoreThanOnce(): void
    {
        $this->expectException(LogicException::class);

        (new QueryBuilder())
            ->where('name = "Mario"')
            ->where('age > 20');
    }

    public function testWhereThrowsIfConditionEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QueryBuilder())->where('  ');
    }

    public function testAndWhereAddsCondition(): void
    {
        $builder = (new QueryBuilder())
            ->where('age > 20')
            ->andWhere('name = "Mario"');

        $conditions = $builder->getConditions();

        $this->assertCount(2, $conditions);
        $this->assertSame([ 'operator' => null, 'condition' => 'age > 20' ], $conditions[0]);
        $this->assertSame([ 'operator' => LogicalOperator::AND, 'condition' => 'name = "Mario"' ], $conditions[1]);
    }

    public function testAndWhereBehavesLikeWhereIfNoPriorConditions(): void
    {
        $builder = (new QueryBuilder())
            ->andWhere('active = 1');

        $conditions = $builder->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertSame([ 'operator' => null, 'condition' => 'active = 1' ], $conditions[0]);
    }

    public function testAndWhereThrowsIfConditionEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QueryBuilder())->andWhere('   ');
    }

    public function testOrWhereAddsCondition(): void
    {
        $builder = (new QueryBuilder())
            ->where('age > 20')
            ->orWhere('name = "Luigi"');

        $conditions = $builder->getConditions();
        $this->assertCount(2, $conditions);

        $this->assertSame([ 'operator' => null, 'condition' => 'age > 20' ], $conditions[0]);
        $this->assertSame([ 'operator' => LogicalOperator::OR, 'condition' => 'name = "Luigi"' ], $conditions[1]);
    }

    public function testOrWhereThrowsIfCalledFirst(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(QueryBuilder::ERROR_CONDITION_ORWHERE_FIRST);

        (new QueryBuilder())->orWhere('active = 1');
    }

    public function testOrWhereThrowsIfConditionEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QueryBuilder())
            ->where('id = 5')
            ->orWhere('  ');
    }

    public function testGetSortFieldsOnDefaultInit(): void
    {
        $builder = new QueryBuilder();
        $this->assertSame([], $builder->getSortFields());
    }

    public function testSortAddsSingleFieldWithDefaultAscDirection(): void
    {
        $builder = (new QueryBuilder())
            ->sort('name');

        $this->assertSame(['name asc'], $builder->getSortFields());
    }

    public function testSortAddsSingleFieldWithExplicitDirection(): void
    {
        $builder = (new QueryBuilder())
            ->sort('date', SortDirection::DESC);

        $this->assertSame(['date desc'], $builder->getSortFields());
    }

    public function testSortAddsMultipleFields(): void
    {
        $builder = (new QueryBuilder())
            ->sort('name')
            ->sort('date', SortDirection::DESC);

        $this->assertSame(['name asc', 'date desc'], $builder->getSortFields());
    }

    public function testSortTrimsField(): void
    {
        $builder = (new QueryBuilder())
            ->sort('  title  ');

        $this->assertSame(['title asc'], $builder->getSortFields());
    }

    public function testSortThrowsExceptionOnEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->sort('');
    }

    public function testGetLimitOnDefaultInit(): void
    {
        $this->assertNull((new QueryBuilder())->getLimit());
    }

    public function testLimit(): void
    {
        $builder = (new QueryBuilder())
            ->limit(10);

        $this->assertSame(10, $builder->getLimit());
    }

    public function testLimitWithLargeValue(): void
    {
        $largeLimit = 1_000_000;
        $builder = (new QueryBuilder())->limit($largeLimit);
        $this->assertSame("limit $largeLimit;", $builder->build());
    }

    public function testLimitThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->limit(-5);
    }

    public function testLimitThrowsExceptionForZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->limit(0);
    }

    public function testGetOffsetOnDefaultInit(): void
    {
        $this->assertNull((new QueryBuilder())->getOffset());
    }

    public function testOffset(): void
    {
        $builder = (new QueryBuilder())
            ->offset(10);

        $this->assertSame(10, $builder->getOffset());
    }

    public function testOffsetAcceptsZero(): void
    {
        $builder = (new QueryBuilder())
            ->offset(0);

        $this->assertSame(0, $builder->getOffset());
    }

    public function testOffsetWithLargeValue(): void
    {
        $largeOffset = 1_000_000;
        $builder = (new QueryBuilder())->offset($largeOffset);
        $this->assertSame("offset $largeOffset;", $builder->build());
    }

    public function testOffsetThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueryBuilder())->offset(-5);
    }

    public function testGetSearchTermOnDefaultInit(): void
    {
        $builder = new QueryBuilder();
        $this->assertNull($builder->getSearchTerm());
    }

    public function testSearch(): void
    {
        $builder = (new QueryBuilder())
            ->search('mario');

        $this->assertSame('mario', $builder->getSearchTerm());
    }

    public function testSearchWithLargeString(): void
    {
        $largeString = str_repeat('x', 10000);
        $builder = (new QueryBuilder())->search($largeString);

        $this->assertSame('search "' . $largeString . '";', $builder->build());
    }

    public function testSearchTermEndsWithBackslash(): void
    {
        $builder = (new QueryBuilder())->search('ends with backslash\\');
        $this->assertSame('search "ends with backslash\\\\";', $builder->build());
    }

    public function testSearchWithSpecialCharacters(): void
    {
        $builder = (new QueryBuilder())
            ->search("test\t\"escape\\sequence\"");

        $this->assertSame("search \"test\t\\\"escape\\\\sequence\\\"\";", $builder->build());
    }

    public function testSearchThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QueryBuilder())
            ->search('   ');
    }

    public function testBuildConditionWithString(): void
    {
        $result = QueryBuilder::buildCondition('name', 'Mario');
        $this->assertSame('name = "Mario"', $result);
    }

    public function testBuildConditionWithInteger(): void
    {
        $result = QueryBuilder::buildCondition('age', 25);
        $this->assertSame('age = 25', $result);
    }

    public function testBuildConditionWithTrueBoolean(): void
    {
        $result = QueryBuilder::buildCondition('active', true);
        $this->assertSame('active = 1', $result);
    }

    public function testBuildConditionWithFalseBoolean(): void
    {
        $result = QueryBuilder::buildCondition('active', false);
        $this->assertSame('active = 0', $result);
    }

    public function testBuildConditionWithArray(): void
    {
        $result = QueryBuilder::buildCondition('category', [1, 2, 3], ComparisonOperator::CONTAINS_ANY);
        $this->assertSame('category = (1,2,3)', $result);
    }

    public function testBuildConditionWithLargeArray(): void
    {
        $largeArray = range(1, 1000);
        $result = QueryBuilder::buildCondition('ids', $largeArray, ComparisonOperator::CONTAINS_ANY);

        $this->assertSame('ids = (' . implode(',', $largeArray) . ')', $result);
    }

    public function testBuildConditionWithArrayContainingEscapeSequences(): void
    {
        $result = QueryBuilder::buildCondition('category', ["\tfood", "\nmaterials\""], ComparisonOperator::CONTAINS_ANY);

        $expected = "category = (\"\tfood\",\"\nmaterials\\\"\")";

        $this->assertSame($expected, $result);

        var_dump($result);
        var_dump($expected);
    }

    public function testBuildConditionWithEqualsOperator(): void
    {
        $result = QueryBuilder::buildCondition('name', 'Luigi');
        $this->assertSame('name = "Luigi"', $result);
    }

    public function testBuildConditionWithNotEqualsOperator(): void
    {
        $result = QueryBuilder::buildCondition('name', 'Luigi', ComparisonOperator::NEQ);
        $this->assertSame('name != "Luigi"', $result);
    }

    public function testBuildConditionWithGreaterThanOperator(): void
    {
        $result = QueryBuilder::buildCondition('age', 5, ComparisonOperator::GT);
        $this->assertSame('age > 5', $result);
    }

    public function testBuildConditionWithGreaterOrEqualThanOperator(): void
    {
        $result = QueryBuilder::buildCondition('age', 5, ComparisonOperator::GTE);
        $this->assertSame('age >= 5', $result);
    }

    public function testBuildConditionWithLessThanOperator(): void
    {
        $result = QueryBuilder::buildCondition('age', 5, ComparisonOperator::LT);
        $this->assertSame('age < 5', $result);
    }

    public function testBuildConditionWithLessOrEqualThanOperator(): void
    {
        $result = QueryBuilder::buildCondition('age', 5, ComparisonOperator::LTE);
        $this->assertSame('age <= 5', $result);
    }

    public function testBuildConditionWithContainsAllOperator(): void
    {
        $result = QueryBuilder::buildCondition('tags', ['action', 'rpg'], ComparisonOperator::CONTAINS_ALL);
        $this->assertSame('tags = ["action","rpg"]', $result);
    }

    public function testBuildConditionWithNotContainsAllOperator(): void
    {
        $result = QueryBuilder::buildCondition('tags', [1, 'rpg', true], ComparisonOperator::NOT_CONTAINS_ALL);
        $this->assertSame('tags = ![1,"rpg",1]', $result);
    }

    public function testBuildConditionWithContainsAnyOperator(): void
    {
        $result = QueryBuilder::buildCondition('tags', [1, 'rpg'], ComparisonOperator::CONTAINS_ANY);
        $this->assertSame('tags = (1,"rpg")', $result);
    }

    public function testBuildConditionWithNotContainsAnyOperator(): void
    {
        $result = QueryBuilder::buildCondition('tags', [1, 'rpg'], ComparisonOperator::NOT_CONTAINS_ANY);
        $this->assertSame('tags = !(1,"rpg")', $result);
    }

    public function testBuildConditionWithContainsExactlyOperator(): void
    {
        $result = QueryBuilder::buildCondition('tags', [1, 'rpg'], ComparisonOperator::CONTAINS_EXACTLY);
        $this->assertSame('tags = {1,"rpg"}', $result);
    }

    public function testBuildConditionThrowsExceptionForEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be a non-empty array');

        QueryBuilder::buildCondition('field', [], ComparisonOperator::CONTAINS_ANY);
    }

    public function testBuildConditionThrowsExceptionForEmptyField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Field cannot be empty');

        QueryBuilder::buildCondition('', 'value');
    }

    public function testBuildConditionThrowsExceptionForEmptyStringValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value for operator');

        QueryBuilder::buildCondition('field', '   ');
    }

    public function testBuildConditionThrowsExceptionForNonScalarValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value for operator');

        QueryBuilder::buildCondition('field', new stdClass());
    }

    public function testBuildConditionThrowsOnMultidimensionalArray(): void
    {
        $this->expectException(InvalidArgumentException::class);

        QueryBuilder::buildCondition('field', [['nested']], ComparisonOperator::CONTAINS_ANY);
    }

    public function testBuildWhereOnDefaultInit(): void
    {
        $builder = new QueryBuilder();
        $this->assertNull($builder->buildWhere());
    }

    public function testBuildWhereWithSingleCondition(): void
    {
        $builder = (new QueryBuilder())
            ->where('name = "Mario"');

        $this->assertSame('where name = "Mario";', $builder->buildWhere());
    }

    public function testBuildWhereWithMultipleAndConditions(): void
    {
        $builder = (new QueryBuilder())
            ->where('name = "Mario"')
            ->andWhere('age > 25')
            ->andWhere('active = 1');

        $this->assertSame('where name = "Mario" & age > 25 & active = 1;', $builder->buildWhere());
    }

    public function testBuildWhereWithMixedAndOrConditions(): void
    {
        $builder = (new QueryBuilder())
            ->where('name = "Mario"')
            ->orWhere('name = "Luigi"')
            ->andWhere('age > 25');

        $this->assertSame('where name = "Mario" | name = "Luigi" & age > 25;', $builder->buildWhere());
    }

    public function testBuildWhereThrowsExceptionForInvalidOperator(): void
    {
        $builder = new QueryBuilder();
        $reflection = new ReflectionClass($builder);
        $conditions = $reflection->getProperty('conditions');

        $conditions->setValue($builder, [
            ['operator' => null, 'condition' => 'field = 1'],
            ['operator' => 'invalid', 'condition' => 'field = 2']
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Unexpected operator 'invalid' in conditions.");

        $builder->buildWhere();
    }

    public function testBuildEmptyQuery(): void
    {
        $builder = new QueryBuilder();
        $this->assertSame('', $builder->build());
    }

    public function testBuildWithFields(): void
    {
        $builder = (new QueryBuilder())
            ->select('name', 'age');

        $this->assertSame('fields name,age;', $builder->build());
    }

    public function testBuildWithExclude(): void
    {
        $builder = (new QueryBuilder())
            ->exclude('created_at', 'updated_at');

        $this->assertSame('exclude created_at,updated_at;', $builder->build());
    }

    public function testBuildWithWhere(): void
    {
        $builder = (new QueryBuilder())
            ->where('age >= 18');

        $this->assertSame('where age >= 18;', $builder->build());
    }

    public function testBuildWithSort(): void
    {
        $builder = (new QueryBuilder())
            ->sort('name')
            ->sort('age', SortDirection::DESC);

        $this->assertSame('sort name asc,age desc;', $builder->build());
    }

    public function testBuildWithLimit(): void
    {
        $builder = (new QueryBuilder())
            ->limit(10);

        $this->assertSame('limit 10;', $builder->build());
    }

    public function testBuildWithOffset(): void
    {
        $builder = (new QueryBuilder())
            ->offset(20);

        $this->assertSame('offset 20;', $builder->build());
    }

    public function testBuildWithZeroOffset(): void
    {
        $builder = (new QueryBuilder())
            ->offset(0);

        $this->assertSame('offset 0;', $builder->build());
    }

    public function testBuildWithSearch(): void
    {
        $builder = (new QueryBuilder())
            ->search('mario');

        $this->assertSame('search "mario";', $builder->build());
    }

    public function testBuildWithSearchTermNeedingEscaping(): void
    {
        $builder = (new QueryBuilder())
            ->search('mario "world"');

        $this->assertSame('search "mario \"world\"";', $builder->build());
    }

    public function testBuildWithAllComponents(): void
    {
        $builder = (new QueryBuilder())
            ->select('name', 'age')
            ->exclude('created_at')
            ->where('age >= 18')
            ->andWhere('active = true')
            ->orWhere('name = "Luigi"')
            ->sort('name')
            ->limit(10)
            ->offset(20)
            ->search('mario');

        $expected = 'fields name,age; exclude created_at; where age >= 18 & active = true | name = "Luigi"; sort name asc; limit 10; offset 20; search "mario";';

        $this->assertSame($expected, $builder->build());
    }

    public function testClearOnDefaultInit(): void
    {
        $builder = (new QueryBuilder())
            ->clear();

        $this->assertBuilderIsEmpty($builder);
    }

    public function testClearResetsAllProperties(): void
    {
        $builder = (new QueryBuilder())
            ->select('name', 'age')
            ->exclude('created_at')
            ->where('age >= 18')
            ->andWhere('active = true')
            ->orWhere('name = "Luigi"')
            ->sort('name', SortDirection::DESC)
            ->limit(10)
            ->offset(20)
            ->search('mario');

        $builder->clear();

        $this->assertBuilderIsEmpty($builder);
    }

    public function testClearAllowsSettingNewValues(): void
    {
        $builder = (new QueryBuilder())
            ->select('name')
            ->where('age > 18');

        $builder->clear()
            ->select('email')
            ->addSelect('first_name', 'last_name')
            ->where('active = true');

        $this->assertSame(['email', 'first_name', 'last_name'], $builder->getSelectedFields());
        $this->assertSame([
            ['operator' => null, 'condition' => 'active = true']
        ], $builder->getConditions());
    }

    public function testMethodChainingReturnsSameInstance(): void
    {
        $builder = new QueryBuilder();

        $this->assertSame($builder, $builder->select('id')->addSelect('name')->where('id=1'));
    }

    /**
     * @throws Throwable
     */
    public function testToString(): void
    {
        $builder = (new QueryBuilder(true))
            ->select('valid');

        $this->assertSame('fields valid;', (string)$builder);
    }

    public function testToStringReturnsErrorMessageOnBuildFailure(): void
    {
        $builder = new QueryBuilder();
        $ref = new ReflectionClass($builder);
        $prop = $ref->getProperty('conditions');

        $prop->setValue($builder, [
            ['operator' => null, 'condition' => 'ok'],
            ['operator' => 'invalid', 'condition' => 'bad']
        ]);

        $this->assertSame('[ERROR] [INVALID __toString CALL]', (string)$builder);
    }

    public function testToStringFailOnStrictMode(): void
    {
        $this->expectException(Throwable::class);

        (new QueryBuilder(true))
            ->enableStrictMode()
            ->select('valid', '');
    }

    private function assertBuilderIsEmpty(QueryBuilder $builder): void
    {
        $this->assertEmpty($builder->getSelectedFields(), 'Selected fields should be empty.');
        $this->assertEmpty($builder->getExcludedFields(), 'Excluded fields should be empty.');
        $this->assertEmpty($builder->getConditions(), 'Conditions should be empty.');
        $this->assertEmpty($builder->getSortFields(), 'Sort fields should be empty.');
        $this->assertNull($builder->getLimit(), 'Limit should be null.');
        $this->assertNull($builder->getOffset(), 'Offset should be null.');
        $this->assertNull($builder->getSearchTerm(), 'Search term should be null.');
        $this->assertFalse($builder->isStrictModeEnabled(), 'Strict mode should be false.');
    }
}
