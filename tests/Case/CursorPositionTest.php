<?php

/**
 * Cursor positions must survive their signed JSON transport without type translation.
 *
 * @since 0.2.0
 */

declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Conversion\Decimal\ExactDecimal;
use Kumwe\Conversion\Value\MoneyValue;
use Kumwe\Conversion\Value\QuantityValue;
use Kumwe\Extension\Spi\BusinessRecord\Query\CursorPosition;
use Kumwe\Extension\Spi\BusinessRecord\Value\ZonedDateTimeValue;
use Kumwe\Extension\Tests\TestCase;

/** Exact cursor scalar and round-trip contract. @since 0.2.0 */
final class CursorPositionTest extends TestCase
{
    /** @since 0.2.0 */
    public function testJsonScalarPositionRoundTripsWithoutTranslation(): void
    {
        $position = new CursorPosition(
            str_repeat('a', 64),
            [null, false, 42, 'north'],
            '018f22e2-7c8b-7ab0-8f3a-88e8026bc101',
        );
        $encoded = json_encode($position->toArray(), JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 8, JSON_THROW_ON_ERROR);
        $this->assertTrue(is_array($decoded), 'A cursor payload decodes as an object.');
        $values = $decoded['values'] ?? null;
        $this->assertTrue(is_array($values) && array_is_list($values), 'Cursor values decode as a list.');

        $rebuilt = new CursorPosition(
            (string) ($decoded['specification'] ?? ''),
            $values,
            (string) ($decoded['record_key'] ?? ''),
        );
        $this->assertSame($position->toArray(), $rebuilt->toArray(), 'JSON decode preserves every cursor value.');
    }

    /** @since 0.2.0 */
    public function testCompositeValuesCannotEnterTheScalarCursorContract(): void
    {
        $decimal = ExactDecimal::fromString('12.50', 8, 2);
        $composites = [
            new MoneyValue($decimal, 'ZAR'),
            new QuantityValue($decimal, 'kg'),
            ZonedDateTimeValue::fromStrings('2026-08-29T10:00:00Z', 'Africa/Johannesburg'),
        ];
        foreach ($composites as $value) {
            $this->assertThrows(
                fn (): CursorPosition => new CursorPosition(
                    str_repeat('b', 64),
                    [$value],
                    '018f22e2-7c8b-7ab0-8f3a-88e8026bc101',
                ),
                InvalidArgumentException::class,
                'A composite query value must not enter a JSON-scalar cursor.',
            );
        }
    }

    /** @since 0.2.0 */
    public function testMalformedScalarListsAreRefused(): void
    {
        $this->assertThrows(
            fn (): CursorPosition => new CursorPosition(
                str_repeat('c', 64),
                [1 => 'not-zero-indexed'],
                '018f22e2-7c8b-7ab0-8f3a-88e8026bc101',
            ),
            InvalidArgumentException::class,
            'A cursor sort sequence must be a list.',
        );
        $this->assertThrows(
            fn (): CursorPosition => new CursorPosition(
                str_repeat('c', 64),
                [str_repeat('x', 4097)],
                '018f22e2-7c8b-7ab0-8f3a-88e8026bc101',
            ),
            InvalidArgumentException::class,
            'An oversized cursor string must be refused.',
        );
        $this->assertThrows(
            fn (): CursorPosition => new CursorPosition(
                str_repeat('c', 64),
                ["\xFF"],
                '018f22e2-7c8b-7ab0-8f3a-88e8026bc101',
            ),
            InvalidArgumentException::class,
            'A non-UTF-8 cursor string must be refused before JSON encoding.',
        );
    }
}
