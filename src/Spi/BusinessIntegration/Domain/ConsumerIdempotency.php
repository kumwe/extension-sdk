<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessIntegration\Domain;

/**
 * Durable duplicate key a consumer or outbound adapter promises to honour.
 *
 * @since  0.1.0
 */
enum ConsumerIdempotency: string
{
    /** Deduplicate the globally stable event UUID across retries and replay. @since 0.1.0 */
    case EVENT_ID = 'event_id';

    /** Deduplicate and order by consumer, aggregate identity and aggregate version. @since 0.1.0 */
    case AGGREGATE_VERSION = 'aggregate_version';
}
