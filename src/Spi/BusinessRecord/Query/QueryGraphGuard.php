<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Query;

use InvalidArgumentException;

/** Closed-type guard preventing forged filter implementations from claiming false bounds. @internal @since 0.2.0 */
final class QueryGraphGuard
{
    /**
     * Require one of the SDK's invariant-bearing final filter nodes.
     *
     * @param  RecordFilter  $filter  Candidate filter node.
     *
     * @since  0.2.0
     */
    public static function filter(RecordFilter $filter): void
    {
        if (
            !in_array($filter::class, [
            BooleanFilter::class,
            ComparisonFilter::class,
            NullFilter::class,
            RelationFilter::class,
            SetFilter::class,
            TextFilter::class,
            ], true)
        ) {
            throw new InvalidArgumentException('A business-record query contains a non-canonical filter node.');
        }
    }

    /** Block instantiation. @since 0.2.0 */
    private function __construct()
    {
    }
}
