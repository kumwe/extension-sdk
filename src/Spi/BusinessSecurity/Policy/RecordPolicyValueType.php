<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSecurity\Policy;

/**
 * Closed scalar types a record policy may compare without lossy coercion.
 *
 * @since  0.1.0
 */
enum RecordPolicyValueType: string
{
    /** Text compared byte-for-byte after the field codec normalizes it. @since 0.1.0 */
    case String = 'string';

    /** Whole number held as a native integer. @since 0.1.0 */
    case Integer = 'integer';

    /** Exact base-ten value carried as canonical text. @since 0.1.0 */
    case Decimal = 'decimal';

    /** Two-valued boolean admitting equality only. @since 0.1.0 */
    case Boolean = 'boolean';

    /** Canonical date, local-time, or UTC-instant text normalized by its field codec. @since 0.1.0 */
    case Temporal = 'temporal';
}
