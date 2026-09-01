<?php

declare(strict_types=1);

namespace Kumwe\Extension\Spi\BusinessRecord\Application;

use Kumwe\Extension\Spi\BusinessRecord\Query\RecordCursor;

/** Policy-admitted bounded page returned by the host business-record reader. @since 0.2.0 */
interface BusinessRecordPage
{
    /** @return list<BusinessRecordView> @since 0.2.0 */
    public function records(): array;

    /** @since 0.2.0 */
    public function nextCursor(): ?RecordCursor;

    /** @return array<string, int|string|null> @since 0.2.0 */
    public function aggregates(): array;
}
