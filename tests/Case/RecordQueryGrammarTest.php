<?php

/**
 * Proves the business-record query grammar stays closed, exact and bounded at construction.
 *
 * @since 0.2.4
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Kumwe\Conversion\Decimal\ExactDecimal;
use Kumwe\Extension\Spi\BusinessRecord\Query\BooleanFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\BooleanOperator;
use Kumwe\Extension\Spi\BusinessRecord\Query\ComparisonFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\ComparisonOperator;
use Kumwe\Extension\Spi\BusinessRecord\Query\NullFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\QueryValue;
use Kumwe\Extension\Spi\BusinessRecord\Query\SetFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\TextFilter;
use Kumwe\Extension\Spi\BusinessRecord\Query\TextOperator;
use Kumwe\Extension\Tests\TestCase;

/**
 * Behavioral checks for the typed literal set, boolean fan-out and canonical leaf exports.
 *
 * Every adapter that builds a query from caller input relies on these nodes refusing what the compiler
 * must never see, so the refusals are proven here, on the nodes, rather than once per consuming factory.
 *
 * @since  0.2.4
 */
final class RecordQueryGrammarTest extends TestCase
{
    /**
     * Approximate, structured and unbounded literals cannot enter an exact query.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testQueryValuesAreBoundedTypedScalars(): void
    {
        $float = $this->assertThrows(
            static fn () => QueryValue::assert(1.25),
            InvalidArgumentException::class,
            'A floating-point literal must be refused.',
        );
        $this->assertStringContains('cannot be a float', $float->getMessage(), 'The refusal names the rule.');
        $this->assertThrows(
            static fn () => QueryValue::assert(['draft', 'approved']),
            InvalidArgumentException::class,
            'A collection must be refused; membership is a set filter.',
        );
        $oversized = $this->assertThrows(
            static fn () => QueryValue::assert(str_repeat('x', 4097)),
            InvalidArgumentException::class,
            'A string beyond 4096 bytes must be refused.',
        );
        $this->assertStringContains('4096 bytes', $oversized->getMessage(), 'The refusal names the byte bound.');

        $this->assertSame(
            '10.00',
            QueryValue::canonical(ExactDecimal::fromString('10.00', 12, 2)),
            'A decimal canonicalises to its exact string.',
        );
        $this->assertSame(
            '2026-08-14T09:30:00.000000+00:00',
            QueryValue::canonical(new DateTimeImmutable('2026-08-14T09:30:00', new DateTimeZone('UTC'))),
            'An instant canonicalises to its offset-qualified six-digit form.',
        );
    }

    /**
     * A comparison refuses a float, a null and a malformed field handle, and exports its canonical shape.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testComparisonFilterRefusesApproximateAndNullLiterals(): void
    {
        $failure = $this->assertThrows(
            static fn (): ComparisonFilter => new ComparisonFilter('amount', ComparisonOperator::Equal, 1.25),
            InvalidArgumentException::class,
            'An approximate comparison value must be refused.',
        );
        $this->assertStringContains('cannot be a float', $failure->getMessage(), 'The refusal names the rule.');
        $this->assertThrows(
            static fn (): ComparisonFilter => new ComparisonFilter('amount', ComparisonOperator::Equal, null),
            InvalidArgumentException::class,
            'A null comparison must be spelled as an explicit null filter.',
        );
        $this->assertThrows(
            static fn (): ComparisonFilter => new ComparisonFilter('Amount', ComparisonOperator::Equal, 1),
            InvalidArgumentException::class,
            'An uppercase field handle must be refused.',
        );

        $comparison = new ComparisonFilter(
            'amount',
            ComparisonOperator::GreaterThanOrEqual,
            ExactDecimal::fromString('10.00', 12, 2),
        );
        $this->assertSame(
            ['type' => 'comparison', 'field' => 'amount', 'operator' => 'gte', 'value' => '10.00'],
            $comparison->toArray(),
            'The export tags the node and canonicalises the literal.',
        );
        $this->assertSame(1, $comparison->operationCount(), 'A comparison costs one operation.');
        $this->assertSame(1, $comparison->depth(), 'A comparison is a leaf.');
        $this->assertSame(0, $comparison->relationDepth(), 'A comparison crosses no relation.');
    }

    /**
     * A boolean group is bounded to one through sixteen children and exports them re-indexed in order.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testBooleanFilterBoundsItsFanOut(): void
    {
        $wide = $this->assertThrows(
            static fn (): BooleanFilter => new BooleanFilter(
                BooleanOperator::All,
                array_fill(0, 17, new NullFilter('reference')),
            ),
            InvalidArgumentException::class,
            'A group wider than the declared bound must be refused.',
        );
        $this->assertStringContains('between 1 and 16 children', $wide->getMessage(), 'The refusal names the bound.');
        $empty = $this->assertThrows(
            static fn (): BooleanFilter => new BooleanFilter(BooleanOperator::All, []),
            InvalidArgumentException::class,
            'An empty group must be refused.',
        );
        $this->assertStringContains('between 1 and 16 children', $empty->getMessage(), 'The refusal names the bound.');
        $this->assertThrows(
            static fn (): BooleanFilter => new BooleanFilter(
                BooleanOperator::Not,
                [new NullFilter('reference'), new NullFilter('archived_at')],
            ),
            InvalidArgumentException::class,
            'A NOT group must hold exactly one child.',
        );

        $full = new BooleanFilter(BooleanOperator::Any, array_fill(0, 16, new NullFilter('reference')));
        $this->assertSame(16, count($full->children), 'The declared bound is inclusive.');

        $group = new BooleanFilter(BooleanOperator::All, [
            5 => new ComparisonFilter('amount', ComparisonOperator::GreaterThanOrEqual, '10.00'),
            9 => new NullFilter('reference'),
        ]);
        $this->assertSame(
            [
                'type' => 'boolean',
                'operator' => 'all',
                'children' => [
                    ['type' => 'comparison', 'field' => 'amount', 'operator' => 'gte', 'value' => '10.00'],
                    ['type' => 'null', 'field' => 'reference', 'is_null' => true],
                ],
            ],
            $group->toArray(),
            'Children are re-indexed and exported in construction order.',
        );
        $negated = new BooleanFilter(BooleanOperator::Not, [$group]);
        $this->assertSame(3, $negated->depth(), 'Depth counts every group level down to the deepest leaf.');
        $this->assertSame(4, $negated->operationCount(), 'Cost counts every group and leaf beneath it.');
        $this->assertSame(0, $negated->relationDepth(), 'Grouping plain field filters crosses no relation.');
    }

    /**
     * Text, set and null leaves construct within their bounds and export canonically.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testTextSetAndNullFiltersExportCanonically(): void
    {
        $text = new TextFilter('name', TextOperator::StartsWith, 'A');
        $this->assertSame(
            ['type' => 'text', 'field' => 'name', 'operator' => 'starts_with', 'text' => 'A'],
            $text->toArray(),
            'A text match exports its handle, anchor and unescaped text.',
        );
        foreach (['', str_repeat('x', 513)] as $candidate) {
            $this->assertThrows(
                static fn (): TextFilter => new TextFilter('name', TextOperator::Contains, $candidate),
                InvalidArgumentException::class,
                'Empty or over-long search text must be refused.',
            );
        }

        $set = new SetFilter('status', [3 => 'draft', 7 => 'approved'], true);
        $this->assertSame(
            ['type' => 'set', 'field' => 'status', 'negated' => true, 'values' => ['draft', 'approved']],
            $set->toArray(),
            'Members are re-indexed and keep the order they were given in.',
        );
        foreach ([[], ['draft', null], [1.5], array_fill(0, 101, 'x')] as $members) {
            $this->assertThrows(
                static fn (): SetFilter => new SetFilter('status', $members),
                InvalidArgumentException::class,
                'An empty, null-bearing, approximate or over-wide set must be refused.',
            );
        }

        $absent = new NullFilter('reference');
        $this->assertSame(
            ['type' => 'null', 'field' => 'reference', 'is_null' => true],
            $absent->toArray(),
            'A null test defaults to matching absent values.',
        );
        $this->assertSame(
            ['type' => 'null', 'field' => 'reference', 'is_null' => false],
            (new NullFilter('reference', false))->toArray(),
            'A null test can be turned around to match present values.',
        );
        $this->assertSame(1, $absent->operationCount(), 'A null test costs one operation whatever its column count.');
    }
}
