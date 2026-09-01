<?php

/**
 * Proves record-policy comparisons bind only literals the calendar can realise.
 *
 * @since 0.2.4
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessSecurity\Policy\RecordPolicyComparison;
use Kumwe\Extension\Spi\BusinessSecurity\Policy\RecordPolicyComparisonOperator;
use Kumwe\Extension\Spi\BusinessSecurity\Policy\RecordPolicyValueType;
use Kumwe\Extension\Tests\TestCase;

/**
 * Behavioral checks for the temporal literal grammar of one policy comparison.
 *
 * @since  0.2.4
 */
final class RecordPolicyTest extends TestCase
{
    /**
     * A temporal literal that is well formed by shape but names no real calendar value is refused.
     *
     * The grammar admits `2026-13-40` by digit count alone, so the parse result has to be checked
     * against the calendar rather than assumed from the pattern.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testTemporalLiteralRejectsImpossibleCalendarValues(): void
    {
        foreach (['2026-13-40', '2026-02-30', '25:00:00'] as $literal) {
            $failure = $this->assertThrows(
                static fn (): RecordPolicyComparison => new RecordPolicyComparison(
                    'service_date',
                    RecordPolicyComparisonOperator::Equal,
                    RecordPolicyValueType::Temporal,
                    $literal,
                ),
                InvalidArgumentException::class,
                sprintf('The impossible temporal literal %s must be refused.', $literal),
            );
            $this->assertStringContains('not canonical', $failure->getMessage(), 'The refusal names the literal rule.');
        }
    }

    /**
     * A calendar-valid date and a six-digit UTC instant are accepted and exported exactly.
     *
     * @return  void
     *
     * @since   0.2.4
     */
    public function testTemporalLiteralAcceptsCanonicalInstants(): void
    {
        $date = new RecordPolicyComparison(
            'service_date',
            RecordPolicyComparisonOperator::Equal,
            RecordPolicyValueType::Temporal,
            '2026-08-14',
        );
        $this->assertSame('2026-08-14', $date->value, 'A real calendar date is retained as written.');

        $instant = new RecordPolicyComparison(
            'captured_at',
            RecordPolicyComparisonOperator::GreaterThanOrEqual,
            RecordPolicyValueType::Temporal,
            '2026-08-14T09:30:00.000000Z',
        );
        $this->assertSame(
            [
                'type' => 'comparison',
                'field' => 'captured_at',
                'operator' => 'greater_than_or_equal',
                'value_type' => 'temporal',
                'value' => '2026-08-14T09:30:00.000000Z',
            ],
            $instant->toArray(),
            'A canonical instant exports the deterministic comparison document.',
        );
        $this->assertSame(1, $instant->operationCount(), 'A comparison costs one policy operation.');
        $this->assertSame(1, $instant->depth(), 'A comparison is a one-level tree.');
    }
}
