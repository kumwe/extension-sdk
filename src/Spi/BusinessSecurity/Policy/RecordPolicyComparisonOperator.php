<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessSecurity\Policy;

/**
 * Portable comparisons admitted by the bounded record-policy language.
 *
 * @since  0.1.0
 */
enum RecordPolicyComparisonOperator: string
{
    /** Field and literal are equal. @since 0.1.0 */
    case Equal = 'equal';

    /** Field and literal differ; null remains unmatched. @since 0.1.0 */
    case NotEqual = 'not_equal';

    /** Field orders before the literal. @since 0.1.0 */
    case LessThan = 'less_than';

    /** Field orders before or with the literal. @since 0.1.0 */
    case LessThanOrEqual = 'less_than_or_equal';

    /** Field orders after the literal. @since 0.1.0 */
    case GreaterThan = 'greater_than';

    /** Field orders after or with the literal. @since 0.1.0 */
    case GreaterThanOrEqual = 'greater_than_or_equal';
}
