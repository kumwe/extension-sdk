<?php

/** Package-owned UTC and IANA timezone conformance. @since 0.2.5 */
declare(strict_types=1);

namespace Kumwe\Extension\Tests\Case;

use InvalidArgumentException;
use Kumwe\Extension\Spi\BusinessRecord\Value\ZonedDateTimeValue;
use Kumwe\Extension\Tests\TestCase;

final class ZonedDateTimeValueTest extends TestCase
{
    public function testCanonicalExportRoundTripsAtPortableAndFractionalBoundaries(): void
    {
        foreach (['1000-01-01T00:00:00Z', '9999-12-31T23:59:59.999999+00:00',
            '2024-02-29T12:34:56.1Z', '2024-03-10T07:30:00Z'] as $instant) {
            $value = ZonedDateTimeValue::fromStrings($instant, 'America/New_York');
            $export = $value->toArray();
            $this->assertSame('America/New_York', $export['timezone'], 'The IANA rules identity is retained.');
            $this->assertSame('+00:00', $value->instant->format('P'), 'The stored instant is UTC.');
            $this->assertSame($export, ZonedDateTimeValue::fromStrings($export['instant'], $export['timezone'])->toArray(),
                'Parse/export/reparse is idempotent.');
        }
    }

    public function testSilentCalendarRepairAndOffsetOnlyZonesAreRefused(): void
    {
        foreach (['2023-02-29T00:00:00Z', '2024-04-31T00:00:00Z', '2024-01-01T24:00:00Z',
            '2024-01-01T00:00:60Z', '0999-12-31T00:00:00Z', '2024-01-01T00:00:00+02:00',
            '2024-01-01 00:00:00Z', '2024-01-01T00:00:00.1234567Z', "2024-01-01T00:00:00Z\n"] as $instant) {
            $this->assertThrows(static fn () => ZonedDateTimeValue::fromStrings($instant, 'UTC'),
                InvalidArgumentException::class, 'Noncanonical or silently repaired instant refuses.');
        }
        foreach (['+02:00', 'EST', 'Africa/Imaginary', 'utc', ''] as $zone) {
            $this->assertThrows(static fn () => ZonedDateTimeValue::fromStrings('2024-01-01T00:00:00Z', $zone),
                InvalidArgumentException::class, 'A zone must be an exact canonical IANA name.');
        }
    }
}
