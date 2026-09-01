<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Application;

/**
 * Security purpose under which a record collection is evaluated and disclosed.
 *
 * @since  0.2.0
 */
enum BusinessRecordQueryPurpose: string
{
    /** Ordinary interactive collection browsing. @since 0.2.0 */
    case Browse = 'browse';

    /** Reporting, including grouped or aggregate output. @since 0.2.0 */
    case Report = 'report';

    /** Export disclosure intended to leave the interactive surface. @since 0.2.0 */
    case Export = 'export';
}
