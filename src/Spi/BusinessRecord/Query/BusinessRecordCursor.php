<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Query;

/** Opaque signed keyset cursor. @since 0.2.0 */
interface BusinessRecordCursor
{
    /** @since 0.2.0 */
    public function value(): string;
}
