<?php

/** Package-owned query identity, pagination and complexity boundary conformance. @since 0.2.5 */
declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessRecord\Query\AggregateFunction;
use Kumwe\Extension\Spi\BusinessRecord\Query\BooleanFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\BooleanOperator;
use Kumwe\Extension\Spi\BusinessRecord\Query\NullFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordAggregate;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordCursor;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordProjection;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordQuerySpecification;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordSearch;
use Kumwe\Extension\Spi\BusinessRecord\Query\RecordSort;
use Kumwe\Extension\Spi\BusinessRecord\Query\RelationFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\RelationQuantifier;
use Kumwe\Extension\Spi\BusinessRecord\Query\SortDirection;
use Kumwe\Extension\Tests\TestCase;

final class RecordQueryBoundaryTest extends TestCase
{
    public function testCursorBoundariesPreserveBytesWithoutClaimingAuthentication(): void
    {
        foreach ([32, 65536] as $length) {
            $token = str_repeat('a', $length - 2) . '.b';
            $cursor = RecordCursor::fromString($token);
            $this->assertSame($token, $cursor->value(), 'An opaque cursor preserves exact bytes.');
            $this->assertSame($token, (string) $cursor, 'String conversion preserves exact bytes.');
        }
        foreach ([str_repeat('a', 29) . '.b', str_repeat('a', 65535) . '.b',
            str_repeat('a', 32), str_repeat('a', 30) . '.b.c', str_repeat('a', 30) . '.b=',
            str_repeat('a', 30) . ".b\n"] as $token) {
            $this->assertThrows(static fn () => RecordCursor::fromString($token),
                InvalidArgumentException::class, 'Malformed or over-limit opaque cursor is refused.');
        }
    }

    public function testDigestExcludesOnlyCursorAndCanonicalizesSearchFieldOrder(): void
    {
        $first = new RecordQuerySpecification(search: new RecordSearch(' 50%_ ', ['name', 'code', 'name']));
        $next = new RecordQuerySpecification(search: new RecordSearch(' 50%_ ', ['code', 'name']),
            after: RecordCursor::fromString(str_repeat('a', 30) . '.b'));
        $this->assertSame($first->digest(), $next->digest(), 'Page position and field order do not change identity.');
        $this->assertSame(null, $next->toArray(false)['after'], 'Digest serialization excludes the opaque cursor.');
        $this->assertSame(' 50%_ ', $first->search?->term, 'The grammar preserves search bytes for the host binder.');
        foreach ([new RecordQuerySpecification(pageSize: 51), new RecordQuerySpecification(includeArchived: true),
            new RecordQuerySpecification(includeDeleted: true),
            new RecordQuerySpecification(sorts: [new RecordSort('name', SortDirection::Descending, false)]),
            new RecordQuerySpecification(projection: new RecordProjection(['name']))] as $changed) {
            $this->assertTrue((new RecordQuerySpecification())->digest() !== $changed->digest(),
                'Every result-affecting query choice changes identity.');
        }
    }

    public function testProjectionAndPageLimitsAreInclusiveAndDuplicatesCannotHideCost(): void
    {
        $this->assertSame(200, (new RecordQuerySpecification(pageSize: 200))->pageSize, 'Page bound is inclusive.');
        $this->assertSame(['name'], (new RecordProjection(['name', 'name']))->fields, 'Projection deduplicates handles.');
        $this->assertSame(64, count((new RecordProjection(array_map(
            static fn (int $i): string => 'f' . $i, range(1, 64))))->fields), 'Field bound is inclusive.');
        $count = new RecordAggregate('total', AggregateFunction::Count);
        $this->assertSame(['alias' => 'total', 'function' => 'count', 'field' => null], $count->toArray(),
            'Count does not nominate a field.');
        foreach ([static fn () => new RecordQuerySpecification(pageSize: 0),
            static fn () => new RecordQuerySpecification(pageSize: 201),
            static fn () => new RecordQuerySpecification(sorts: [new RecordSort('name'), new RecordSort('name')]),
            static fn () => new RecordQuerySpecification(sorts: array_fill(0, 6, new RecordSort('name'))),
            static fn () => new RecordProjection(array_fill(0, 65, 'name')),
            static fn () => new RecordProjection(includes: array_fill(0, 5, 'owner')),
            static fn () => new RecordProjection(aggregates: [$count, $count]),
            static fn () => new RecordAggregate('total', AggregateFunction::Count, 'amount'),
            static fn () => new RecordSearch(str_repeat('x', 257), ['name']),
            static fn () => new RecordSearch('name', [])] as $invalid) {
            $this->assertThrows($invalid, InvalidArgumentException::class, 'Invalid query bounds are refused.');
        }
    }

    public function testDepthRelationAndOperationBudgetsAreOwnedByTheGrammar(): void
    {
        $filter = new NullFilter('name');
        for ($i = 1; $i < 8; $i++) {
            $filter = new BooleanFilter(BooleanOperator::Not, [$filter]);
        }
        $this->assertSame(8, (new RecordQuerySpecification($filter))->filter?->depth(), 'Eight levels fit.');
        $this->assertThrows(static fn () => new RecordQuerySpecification(
            new BooleanFilter(BooleanOperator::Not, [$filter])), InvalidArgumentException::class, 'Nine levels refuse.');
        $relation = new RelationFilter('owner', RelationQuantifier::Any,
            new RelationFilter('manager', RelationQuantifier::All, new NullFilter('name')));
        $this->assertSame(2, (new RecordQuerySpecification($relation))->filter?->relationDepth(), 'Two hops fit.');
        $this->assertThrows(static fn () => new RecordQuerySpecification(
            new RelationFilter('parent', RelationQuantifier::None, $relation)),
            InvalidArgumentException::class, 'Three hops refuse.');
        $group = new BooleanFilter(BooleanOperator::All, array_fill(0, 16, new NullFilter('name')));
        $wide = new BooleanFilter(BooleanOperator::All, [$group, $group, $group, $group]);
        $this->assertSame(69, $wide->operationCount(), 'Repeated subtrees still count on each traversal.');
        $this->assertThrows(static fn () => new RecordQuerySpecification($wide),
            InvalidArgumentException::class, 'A query beyond 64 operations refuses.');
    }

    public function testForeignFilterCannotLieAboutItsComplexityOrExecuteCallbacks(): void
    {
        $foreign = new class implements RecordFilter {
            public function toArray(): array { throw new \LogicException('must not inspect'); }
            public function operationCount(): int { throw new \LogicException('must not inspect'); }
            public function depth(): int { throw new \LogicException('must not inspect'); }
            public function relationDepth(): int { throw new \LogicException('must not inspect'); }
        };
        foreach ([static fn () => new RecordQuerySpecification($foreign),
            static fn () => new BooleanFilter(BooleanOperator::Not, [$foreign]),
            static fn () => new RelationFilter('owner', RelationQuantifier::Any, $foreign)] as $invalid) {
            $this->assertThrows($invalid, InvalidArgumentException::class, 'Foreign executable nodes are refused first.');
        }
    }
}
