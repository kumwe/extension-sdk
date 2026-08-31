<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Query;

/** One typed business-record sort key. @since 0.2.0 */
interface BusinessRecordSort
{
    /** @since 0.2.0 */
    public function field(): string;

    /** @return array<string, mixed> @since 0.2.0 */
    public function toArray(): array;
}
